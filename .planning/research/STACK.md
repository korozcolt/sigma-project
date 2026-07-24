# Stack Research

**Domain:** Registraduría polling-place lookup resiliency (captcha-solving for a live source + large census-snapshot fallback + scheduled reconciliation) inside an existing Laravel 12 / Filament 4 app
**Researched:** 2026-07-24
**Confidence:** MEDIUM-HIGH — CSV import and scheduling are HIGH (established, verified against the repo); reCAPTCHA Enterprise feasibility is the one genuine unknown and is honestly MEDIUM (provider *supports* it, but token *acceptance* by `wsp.registraduria.gov.co` cannot be confirmed without a live spike)

## TL;DR for the roadmap

Almost nothing new needs to be *installed*. All three capabilities are served by infrastructure already in the codebase:

- **Captcha (live source):** keep the existing Python microservice (Flask + Playwright + 2captcha) and the existing `RegistraduriaService.php` HTTP wrapper. The only change is a captcha-*type* change (Enterprise instead of v2) plus a new target URL/sitekey — a code change to `registraduria-service/app.py`, not a new dependency. 2captcha already supports Enterprise via a single extra `enterprise=1` parameter on the same `userrecaptcha`/`grecaptcha` method.
- **CSV fallback import:** use MySQL-native `LOAD DATA LOCAL INFILE` into a staging table, then an `INSERT … SELECT` join against the already-seeded `polling_places` table. No import package. Encoding handled once with PHP's built-in `mbstring`/`iconv`. This is a one-off/occasional artisan command, not a user-facing upload.
- **Reconciliation job:** Laravel's built-in scheduler (`Schedule::command()` in `routes/console.php`, already used twice in this repo) + a `ShouldQueue` job following the exact `FinalizeElectionEvent` pattern (`handle()`, dotted `Log::` events, `chunkById`, `failed()` hook). No new queue system.

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| 2captcha reCAPTCHA Enterprise API | current (service, no version) | Solve the `wsp.registraduria.gov.co/censo/consulta/` "reCAPTCHA Enterprise" checkbox to automate the live lookup | Already the campaign's captcha vendor; Enterprise support is a documented, one-parameter delta (`enterprise=1`) on the exact `userrecaptcha` flow `app.py` already uses. No new account, no new SDK. $1–$2.99 / 1,000 solves. |
| MySQL `LOAD DATA LOCAL INFILE` | MySQL 8 (bundled with app's `sigma_sincelejo` DB) | Bulk-load the 216,528-row census snapshot | Fastest possible path (single statement, C-level loader) and lowest PHP memory for an 18 MB file. Orders of magnitude faster than row-by-row Eloquent. Native — nothing to install. |
| PHP `mbstring` / `iconv` | bundled with PHP 8.4 | One-shot ISO-8859-1 → UTF-8 conversion of the census file before load | Built into PHP; `mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1')` (or the `iconv` CLI on the whole file) is the standard, zero-dependency fix for the "Malformed UTF-8" error this file will otherwise throw. |
| Laravel Scheduler (`Illuminate\Support\Facades\Schedule`) | Laravel 12 (installed) | Recurring reconciliation trigger | Already the repo's scheduling mechanism — see `routes/console.php` (`Schedule::command('messages:send-birthdays')->dailyAt('09:00')`). Laravel 12 defines schedules in `routes/console.php`, not a Kernel. |
| Laravel Queue + `ShouldQueue` job | Laravel 12 (installed, in production use) | Run the reconciliation work off the request cycle, matching `FinalizeElectionEvent` | The established background-job pattern in this codebase (real queue, structured logs, `failed()` hook, `chunkById`). Reuse it verbatim. |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Playwright (Python) | already installed in `registraduria-service` | Drive the `wsp` page, inject the solved Enterprise token into `g-recaptcha-response`, submit the consulta form | Already present. Enterprise checkbox flow is the *same* browser-automation shape as the current v2 flow — solve token, inject, submit. No upgrade needed for the captcha-type change itself. |
| Guzzle (via Laravel `Http` facade) | bundled with Laravel 12 | `RegistraduriaService.php` ↔ Python service HTTP calls | Already the transport (`Http::timeout()->post('/lookup')`, `->get('/result/{id}')`). No change. |
| `league/csv` (`CharsetConverter`) | ^9.16 | *Optional* alternative to hand-rolled `mbstring` streaming if you want a tested CSV reader with built-in charset conversion | Only if you prefer a library over `fgetcsv` + `mb_convert_encoding`. Not required — the native path is enough for a semicolon-delimited, known-schema file. Adds one Composer dependency. |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| `php artisan make:command` | Create the import command and the reconciliation command | Both new commands live in `app/Console/Commands/` (auto-registered in Laravel 12). Always `--no-interaction`. |
| `php artisan make:job` | Create the reconciliation job | Model it on `app/Jobs/FinalizeElectionEvent.php` — same `ShouldQueue` + `failed()` + dotted-log conventions. |
| `iconv` (CLI) | One-time bulk transcode of the census file | `iconv -f ISO-8859-1 -t UTF-8 in.csv > out.csv` — simplest if you convert the file once at import rather than per-line in PHP. |

## Installation

```bash
# Core: NOTHING new is required. All three capabilities use installed infrastructure.

# OPTIONAL — only if you choose the league/csv reader over native fgetcsv+mbstring:
composer require league/csv

# Verify MySQL LOCAL INFILE is enabled (needed once, both client and server side):
#   SHOW GLOBAL VARIABLES LIKE 'local_infile';   -> should be ON
#   PDO must set PDO::MYSQL_ATTR_LOCAL_INFILE => true on the connection
```

## The reCAPTCHA Enterprise feasibility question (do NOT hand-wave this)

This is the single highest-risk unknown in the milestone. Honest assessment:

**What is confirmed (HIGH):**
- 2captcha, Anti-Captcha, and CapMonster Cloud all publicly document reCAPTCHA **Enterprise** support, not just v2/v3.
- For 2captcha specifically, Enterprise is **the same method you already use** with **one extra parameter**: `enterprise=1`. You interact with the API "the same way it is done when solving v2 or v3." So the existing 2captcha integration in `app.py` is ~90% reusable.
- Pricing is $1–$2.99 / 1,000 solves — same order of magnitude as the v2 flow, marginally higher at the top end.

**What changes vs the current v2 flow (MEDIUM — must be coded/spiked):**
1. **New sitekey.** The current hardcoded `6Lc9DmgrAAAAAJAjWVhjDy1KSgqzqJikY5z7I9SV` is for the dead domain. You must extract the Enterprise sitekey from `wsp.registraduria.gov.co/censo/consulta/` (in the page's `grecaptcha.enterprise.render`/`execute` call or the `data-sitekey` attribute).
2. **`enterprise=1` param** added to the 2captcha task.
3. **Possible `action` and `data-s` params.** Enterprise deployments frequently pass an `action` (from `grecaptcha.enterprise.execute(..., {action: '...'})`) and sometimes a `data-s` string. If `wsp` uses them, they must be scraped from the page and forwarded to 2captcha, or the returned token will be rejected.
4. **Token injection target.** The token goes into the form's `g-recaptcha-response` field and is submitted with the consulta POST — *not* used as a Bearer header like the old `infovotantes` API flow. This is a meaningfully different Playwright step than the current implementation.

**The real risk (be explicit):** reCAPTCHA Enterprise is **risk-score-based on the server side**. Even a validly-solved token can be *rejected* by the site's backend `createAssessment` call if the overall request looks bot-like (IP reputation, headers, behavioral signals). A solver returning a token is **necessary but not sufficient** — acceptance is decided by Registraduría's server, which we don't control.

**Mitigating signal (encouraging):** the UI shows a **visible "No soy un robot" checkbox** labeled "reCAPTCHA Enterprise." A visible checkbox challenge is the **v2-style Enterprise variant**, which is the *more* solvable case — the token carries an explicit human-challenge result rather than relying purely on an invisible v3 score. This is a good omen but not a guarantee.

**Verdict:** MEDIUM feasibility. The provider capability exists and integration effort is small (one param + new sitekey + injection change). Whether `wsp` *accepts* solved tokens end-to-end can only be answered by the feasibility spike that is already the first target feature of this milestone. **Recommend: time-box a spike that solves + submits one real cédula through `wsp` before committing to the live-source path.** The census-snapshot fallback is what makes this risk acceptable — the milestone still delivers value if the live source proves unsolvable.

## CSV import approach (216K rows, ISO-8859-1, semicolon-delimited)

**Recommended: staging table + `LOAD DATA LOCAL INFILE` + SQL join enrichment.**

1. **Convert encoding once.** The file is ISO-8859-1/CRLF. Transcode to UTF-8 up front — either `iconv -f ISO-8859-1 -t UTF-8` on the file, or stream line-by-line with `mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1')`. Do this *before* MySQL sees it to avoid "Malformed UTF-8" corruption on names like "CHOCHO"/accented municipality names.
2. **`LOAD DATA LOCAL INFILE`** the UTF-8 file into a raw staging table matching the CSV's 13 columns (`divipol;codificado;cedula;dpto;cero;cero;mcpio;ref1;zona;ref2;puesto;nombre;mesa`), `FIELDS TERMINATED BY ';'`, `IGNORE 1 LINES`. This loads all 216K rows in seconds.
3. **Enrich via `INSERT … SELECT` join** against the already-seeded `polling_places` table (keyed on `dane_department_code`/`dane_municipality_code`/`zone_code`/`place_code` = census `dpto`/`mcpio`/`zona`/`puesto`). This reconstructs the department/municipality names + address the census file lacks — no new reference data needed (per milestone context).
4. **Index on `cedula`.** The final lookup table is cédula-keyed; put a B-tree index (unique if cédula is unique in the snapshot, otherwise plain) on the `cedula` column, stored as `BIGINT UNSIGNED`. At 216K rows this is trivial for MySQL — sub-millisecond point lookups.

**Why not chunked Eloquent / LazyCollection?** It works and is more flexible for validation, but it's ~10–50× slower and heavier for a fixed-schema 216K-row snapshot you control. Use it only if you need per-row PHP validation you can't express in SQL. Given the enrichment is a clean 4-column join, SQL wins.

**Why not `maatwebsite/excel`?** It's installed and correct for *user-facing* Filament/admin CSV uploads (the existing Apoyo bulk import), but it's memory-heavy and slow for a 216K-row server-side snapshot load. Wrong tool for this job — keep it for the admin upload feature it already serves.

**Wrap the import in an artisan command** (`php artisan make:command ImportCensusSnapshot`) so it's repeatable and can be re-run when a newer census dump arrives.

## Reconciliation job (matches FinalizeElectionEvent)

- **Trigger:** add one line to `routes/console.php`, e.g. `Schedule::command('census:reconcile-live')->hourly()->withoutOverlapping();` — same style as the existing `birthday:dispatch-webhooks` entry (which already uses `->withoutOverlapping()`).
- **Command dispatches a queued job** (or the command *is* thin and dispatches per-batch jobs). The job:
  - `implements ShouldQueue`
  - Queries voters whose polling place is currently flagged `source = 'local-snapshot-fallback'`, `chunkById(500, …)` (exact `FinalizeElectionEvent` pattern).
  - For each, calls `RegistraduriaService::startLookup()` / `getResult()` (the live path). On success, updates the record and flips the source flag to `live`.
  - Structured dotted logs: `Log::info('census.reconcile.started', […])`, `…completed`, `…skipped_*`.
  - `failed(\Throwable $e)` hook logging `census.reconcile.failed` — mirrors `FinalizeElectionEvent::failed()`.
- **`withoutOverlapping()`** is important: if the live source is slow/flaky, an hourly run must not stack on a still-running previous run.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| 2captcha Enterprise (`enterprise=1`) | CapMonster Cloud or Anti-Captcha Enterprise | If the spike shows 2captcha's Enterprise success rate against `wsp` is too low. CapMonster documents Enterprise support and a similar REST API; it's the natural second try. Keep the swap behind the existing `RegistraduriaService` seam so the Laravel side doesn't care which solver wins. |
| `LOAD DATA LOCAL INFILE` + SQL join | LazyCollection chunked upsert | If you later need per-row PHP validation/transform that's awkward in SQL, or if `local_infile` cannot be enabled in the target environment. |
| Native `mbstring`/`iconv` conversion | `league/csv` `CharsetConverter` | If you want a tested reader abstraction and don't mind one added Composer dep. |
| Laravel Scheduler + `ShouldQueue` job | (none) | This is the house pattern; do not introduce anything else. |

## What NOT to Use / Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| A new job/queue system (external cron runner, separate worker stack, etc.) | Laravel queue + scheduler are already in production use (`FinalizeElectionEvent`, `messages:send-birthdays`, `birthday:dispatch-webhooks`) | Reuse `Schedule::command()` + `ShouldQueue` verbatim |
| A new scraping framework (Puppeteer node service, Selenium, Scrapy) | Playwright is already installed and working in `registraduria-service` | Extend the existing `app.py` Playwright flow for the Enterprise checkbox + form-submit |
| `maatwebsite/excel` for the census load | Memory-heavy / slow for a 216K-row server-side snapshot | `LOAD DATA LOCAL INFILE` (keep maatwebsite for user-facing admin uploads) |
| A brand-new captcha vendor account | 2captcha already integrated; Enterprise is a one-param delta | Add `enterprise=1` to the existing 2captcha call |
| `utf8_decode()` / `utf8_encode()` (deprecated in PHP 8.2+) | Removed/deprecated, silently mangles non-Latin-1 bytes | `mb_convert_encoding(…, 'UTF-8', 'ISO-8859-1')` or `iconv` |
| Rewriting `RegistraduriaService.php`'s HTTP contract | The async `/lookup` + `/result/{id}` polling contract still fits; only the Python side's target changes | Keep the Laravel wrapper; change the Python service internals |

## Stack Patterns by Variant

**If the Enterprise spike SUCCEEDS (token accepted by `wsp` end-to-end):**
- Live source = updated `registraduria-service/app.py` targeting `wsp.registraduria.gov.co/censo/consulta/` with the new sitekey + `enterprise=1`.
- Census snapshot = fallback only, surfaced when the live call fails/times out.
- Reconciliation job actively re-verifies snapshot-served voters against the now-working live source.

**If the Enterprise spike FAILS (tokens consistently rejected):**
- Census snapshot becomes the *primary* source, not just fallback.
- Reconciliation job still ships but is effectively dormant / retries on a long interval in case Registraduría's posture changes.
- The data-source flag (`live` vs `local-snapshot`) still matters for auditability — it just skews to `local-snapshot`.
- Milestone still delivers: the fallback + provenance + scheduled retry are the resilient core; the live source is the upside.

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| PHP 8.4.14 | `mb_convert_encoding` / `iconv` | Both bundled; `utf8_encode/decode` deprecated — do not use |
| Laravel 12 | `Schedule::command()` in `routes/console.php` | Confirmed against this repo's existing usage; no Kernel-based scheduling in L12 |
| MySQL 8 (`sigma_sincelejo`) | `LOAD DATA LOCAL INFILE` | Requires `local_infile=ON` (server) + `PDO::MYSQL_ATTR_LOCAL_INFILE=true` (client); verify in target env |
| 2captcha API | existing Python 2captcha flow | Enterprise = same endpoint + `enterprise=1` (+ possibly `action`/`data-s`) |
| `league/csv` ^9.16 | PHP 8.4 | Only if chosen over native reader |

## Sources

- [2captcha reCAPTCHA Enterprise solver](https://2captcha.com/p/recaptcha_enterprise) — HIGH: confirms Enterprise support, `enterprise=1` param, optional `action`/`data-s`, $1–2.99/1k pricing, "same as v2/v3" integration
- [CapMonster Cloud — reCAPTCHA v2/v3/Enterprise](https://capmonster.cloud/en/blog/recaptcha-v2-vs-v3-vs-enterprise/) — MEDIUM: confirms viable alternative solver with Enterprise + REST API
- [MojoAuth — reCAPTCHA v2/v3/Enterprise differences](https://mojoauth.com/blog/recaptcha-vs-captcha-versions-v2-v3-enterprise) — MEDIUM: Enterprise is server-side risk-score-based (the core acceptance risk)
- [uCaptcha — Solving reCAPTCHA Enterprise guide](https://ucaptcha.net/blog/recaptcha-enterprise-guide/) — LOW/MEDIUM: Enterprise solving nuances, token-vs-acceptance distinction
- [Medium — Lightning-Fast Laravel CSV Imports with LOAD DATA INFILE](https://medium.com/@techsolver/lightning-fast-laravel-csv-imports-with-load-data-infile-b403ec8bd532) — MEDIUM: LOAD DATA INFILE in Laravel, `local_infile` requirement
- [Magecomp — Laravel 12 Import Large CSV](https://magecomp.com/blog/laravel-12-import-large-csv-file-into-database/) — LOW/MEDIUM: chunked-insert baseline for comparison
- [league/csv CharsetConverter](https://csv.thephpleague.com/9.0/converter/charset/) — HIGH: mbstring-based ISO-8859-1→UTF-8 conversion (optional library path)
- [PHP manual — mb_convert_encoding](https://www.php.net/manual/en/function.mb-convert-encoding.php) — HIGH: native encoding conversion
- Repo verification (HIGH): `routes/console.php` (Schedule pattern), `app/Jobs/FinalizeElectionEvent.php` (job pattern), `app/Services/RegistraduriaService.php` (HTTP contract), `.env`/`config/database.php` (MySQL `sigma_sincelejo`), census CSV header + row sample

---
*Stack research for: Registraduría polling-place lookup resiliency (live captcha source + census-snapshot fallback + scheduled reconciliation)*
*Researched: 2026-07-24*
