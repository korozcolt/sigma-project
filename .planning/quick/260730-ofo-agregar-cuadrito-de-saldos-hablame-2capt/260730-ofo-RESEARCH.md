# Quick Task 260730-ofo: Cuadrito de saldos (Hablame + 2captcha) en topbar admin — Research

**Researched:** 2026-07-30
**Domain:** External balance APIs (2captcha, Hablame) + Filament topbar renderHook + daily-spend derivation
**Confidence:** HIGH on both API contracts; MEDIUM on the exact Hablame `payLoad` field names

## Summary

Two external balances need to surface in a super-admin-only topbar badge. Both API contracts are now verified:

- **2captcha** balance is fetched via `POST https://api.2captcha.com/getBalance` with JSON `{"clientKey":"<key>"}`, returning `{"errorId":0,"balance":0.93958}`. **The balance is in USD** — so the plan's proposed thresholds (green > $5, yellow $1–$5, red < $1) are valid as-is.
- **Hablame** `getAccountInfo()` already exists (`GET {api_url}/account/v5/info`) and returns `payLoad.balance`. **This balance is in COP (Colombian Pesos)**, confirmed by Hablame's docs showing a `"currency": "COP"` field in the account response. The USD-style thresholds do NOT transfer — Hablame thresholds must be expressed in COP (a Colombian SMS costs roughly COP $20–40, so a healthy prepaid balance is tens of thousands of COP, not single digits).

**Primary recommendation:** Reuse the existing `HablameSmsService::getAccountInfo()` for the Hablame side. Add a small `TwoCaptchaBalanceService` (or a method on an existing captcha-related service) that calls the modern JSON endpoint. **Do NOT call either external API synchronously inside the topbar renderHook on every page load** — cache the values (or read from a daily snapshot row) so a slow/down provider never blocks or crashes the topbar for every super_admin page render.

## Task Context (no CONTEXT.md exists)

No `*-CONTEXT.md` in the task directory — this research is not constrained by locked decisions. The task statement: super-admin-only topbar badge showing Hablame + 2captcha balances, plus a **daily-average 2captcha spend** derived from balance snapshots.

## Finding 1 — 2captcha getBalance API contract (HIGH confidence)

### Recommended (modern v2 JSON) method
```
POST https://api.2captcha.com/getBalance
Content-Type: application/json

{ "clientKey": "4b39eeff961da32b2b960a58a0ba7c3e" }
```

Success response:
```json
{ "errorId": 0, "balance": 0.93958 }
```

- `balance` is a top-level float, **in USD** (confirmed via 2captcha docs + multiple wrapper libraries).
- `errorId: 0` means success. Non-zero `errorId` signals an error; the body also carries an `errorCode` string and `errorDescription`.

### Error handling (graceful-degradation requirement)
For a bad/expired/zero-balance key the client MUST NOT throw uncaught into the topbar. Relevant error codes:

| errorCode | id | Meaning |
|-----------|----|---------|
| `ERROR_KEY_DOES_NOT_EXIST` | 1 | API key incorrect/expired |
| `ERROR_ZERO_BALANCE` | 10 | No funds (note: `getBalance` itself typically still returns the balance; this code appears on solve calls) |
| `ERROR_IP_NOT_ALLOWED` | 11 | Request IP not in the trusted-IP allowlist |

**Implementation guard:** wrap the HTTP call in try/catch + `Http::timeout(10)`, treat any non-2xx / `errorId != 0` / exception as "saldo no disponible" and render a neutral/gray state — mirror the existing `HablameSmsService::getAccountInfo()` shape that already returns `['success' => false, 'error' => ...]` on failure. The badge should show a dash/"N/D" rather than error out.

### Legacy method (still works, not recommended)
The old `GET https://2captcha.com/res.php?key=<key>&action=getbalance&json=1` returning `{"status":1,"request":"0.93958"}` is still supported historically, but 2captcha's current docs present the `api.2captcha.com/getBalance` JSON POST as the canonical method. **Use the modern JSON POST.**

### Per-solve cost (for the alternative daily-spend cross-check, below)
reCAPTCHA v2 solves — 2captcha's default rate is ~$2.99 per 1000 solves ≈ **$0.00299 per lookup**. Useful only if the plan wants a count-based cross-check; the snapshot-diff approach (below) does not need it.

## Finding 2 — Hablame balance currency (HIGH on currency, MEDIUM on field names)

- `HablameSmsService::getAccountInfo()` already returns `balance` = `data['payLoad']['balance']`. **Currency is COP** (Hablame is a Colombian provider; docs expose `"currency": "COP"` in the account response).
- The send path (`HablameSmsService::send()`) sums a per-message `price` field into `cost`; `TestHablameSms.php:172` renders it as `'$'.$result['cost']` — a COP amount (Colombian SMS prices are per-message pesos), reinforcing that Hablame money values throughout this codebase are COP, not USD.
- **Caveat (MEDIUM):** the exact sub-field names the current `getAccountInfo()` maps (`account_id`, `status`, `billing_type`, `created_at`) look partly guessed and may not all exist in the real `payLoad`. Only `balance` is load-bearing for this task and it is present. If the plan wants to display the currency label dynamically, read `payLoad.currency` (expected `"COP"`) rather than hard-coding.

**Hablame threshold guidance:** thresholds MUST be in COP. Without the client's real recharge cadence, treat any hard COP thresholds as **provisional/placeholder** in the plan and flag them for the user to confirm. A reasonable placeholder given ~COP $20–40/SMS: red < ~COP $10,000 (~250–500 SMS left), yellow < ~COP $50,000, green above. Mark these explicitly as needing user sign-off.

## Finding 3 — Daily-average spend from balance snapshots (pitfalls)

The task wants a **daily-average 2captcha spend**. The intended approach is snapshotting the 2captcha balance over time and diffing. Key pitfalls:

### Pitfall A — Recharge (top-up) days produce negative deltas
`spend = prev_balance − curr_balance` goes **negative** whenever the account is recharged between snapshots. A naive average silently corrupts. **Mitigation:** clamp per-day deltas to `max(0, prev − curr)`, or detect an increase and treat that day's spend as "unknown"/skip it from the average. Document which you choose.

### Pitfall B — Day boundary: app timezone is UTC, operators are UTC-5
`config/app.php` sets `'timezone' => 'UTC'`. Colombia is **America/Bogota (UTC-5)**. A "daily" bucket computed on UTC midnight will split a Colombian operational day ~5 hours off from what the user expects. **Mitigation:** define the day boundary explicitly in America/Bogota when bucketing snapshots/lookups (e.g. `now()->timezone('America/Bogota')->startOfDay()`). **DST is a non-issue** — Colombia does not observe DST, so UTC-5 is stable year-round.

### Pitfall C — First day / cold start (no prior snapshot)
With snapshot-diffing, the very first snapshot has no predecessor to diff against, so the average is undefined. **Mitigation:** render "—"/"N/D" (not `0`, which reads as "no spend") until at least two snapshots across a day boundary exist. Same applies to any trailing-N-day average before N days of history accumulate.

### Pitfall D — Where the snapshots come from
There is **no existing snapshot table or balance cache** in the codebase (verified: no `*balance_snapshot*` migration, no relevant `Cache::remember`). The plan must add:
1. A snapshots table (e.g. `service_balance_snapshots` with `provider`, `balance`, `captured_at`) or a lightweight per-provider daily row.
2. A scheduled command to capture the balance daily — mirror the existing `routes/console.php` `Schedule::command(...)` pattern (e.g. `Schedule::command('balances:snapshot')->dailyAt('...')->withoutOverlapping()`). Sibling jobs there use `->hourly()->withoutOverlapping(10)`.

### Alternative / cross-check — count-based spend (no balance diff)
`registraduria_lookups` rows are created (via `PollingPlaceResolver::persistPermanentLookup()` → `updateOrCreate` keyed by `document_number`) once per genuinely-new live lookup, and each new live lookup corresponds to ~one paid 2captcha solve. So `count(registraduria_lookups where created_at::bogota-day = today) × $0.00299` gives a spend estimate that **works on day 1 with no new table**. Caveats: (1) a **force-refresh** (D-10) of an already-cached cédula re-incurs a 2captcha cost but only `updateOrCreate`-updates the existing row — `created_at` is unchanged — so this method **undercounts** force-refreshes; (2) it assumes the reCAPTCHA-v2 per-solve rate. The snapshot-diff method captures real dollars including force-refreshes; the count method is a decent sanity cross-check. Recommend snapshot-diff as primary, per the task framing.

## Implementation Pitfalls (topbar wiring)

- **Gating:** replicate `campaign-context-switcher.blade.php`'s guard exactly — `if (! CampaignContext::isSuperAdmin()) { return; }` at the top of the badge view. Register via a second `->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.components.saldos-badge'))` in `AdminPanelProvider` (the switcher already uses `TOPBAR_END`; both can coexist).
- **No synchronous external calls in the hook:** the renderHook fires on every admin page load. Read cached/snapshotted values, not live HTTP. If a live read is unavoidable, `Cache::remember('saldo_2captcha', now()->addMinutes(10), ...)` and always tolerate failure.
- **2captcha config:** the key currently lives only as `TWO_CAPTCHA_KEY` in `.env` (value `4b39eeff...`). Add a `config/services.php` entry (e.g. `'twocaptcha' => ['api_key' => env('TWO_CAPTCHA_KEY'), 'api_url' => env('TWO_CAPTCHA_API_URL', 'https://api.2captcha.com')]`) and read via `config()` — never `env()` outside config per CLAUDE.md. Note the STATE.md blocker: production `sigma-registraduria` container still runs old hardcoded-key code, but that's the Python service, unrelated to this Laravel-side badge.

## Project Constraints (from CLAUDE.md)

- Explicit `use` statements only — never namespace aliases or inline `\App\...` paths (runtime-error rule).
- New service in `app/Services/`, `handle()`/`execute()` style, thin controllers; log via `Log::` and only catch+log if continuing.
- `nyquist_validation` is **false** in `.planning/config.json` — Validation Architecture section intentionally omitted. Tests still required per CLAUDE.md test-enforcement (`php artisan make:test --pest`).
- `vendor/bin/pint --dirty` before finalizing.
- User standing preferences (from memory): **respond in Spanish**; **browser-verify UI in a real browser before deploying to prod** — a Pest/Livewire test is not sufficient for this topbar badge.

## Sources

### Primary (HIGH)
- https://2captcha.com/api-docs/get-balance — endpoint, method, `{"errorId":0,"balance":...}` shape
- https://2captcha.com/api-docs/error-codes — `ERROR_KEY_DOES_NOT_EXIST` (1), `ERROR_ZERO_BALANCE` (10), `ERROR_IP_NOT_ALLOWED` (11)
- https://docs.hablame.co/reference/informacion-general — account response includes `"currency": "COP"`
- Codebase: `app/Services/HablameSmsService.php` (getAccountInfo, price/cost handling), `config/app.php` (timezone UTC), `config/services.php` (hablame block; no twocaptcha block yet), `.env` (`TWO_CAPTCHA_KEY`), `resources/views/filament/components/campaign-context-switcher.blade.php` (super-admin gate + TOPBAR_END pattern), `routes/console.php` (Schedule patterns), `database/migrations/2026_07_26_170000_create_registraduria_lookups_table.php`

### Secondary (MEDIUM)
- 2captcha balance = USD, and legacy `res.php?action=getbalance&json=1` still supported — corroborated across multiple third-party wrapper libraries (GitHub Zaczero/2Captcha, ruby-2captcha, Perl WebService::2Captcha) + WebSearch consensus.
- reCAPTCHA v2 rate ~$2.99/1000 (~$0.00299/solve) — 2captcha public pricing.

## Metadata

**Confidence breakdown:**
- 2captcha endpoint/method/response/USD: HIGH — official docs + multiple libs agree.
- 2captcha error codes: HIGH for code list; MEDIUM on exact JSON error field names beyond `errorId`/`errorCode`.
- Hablame balance = COP: HIGH.
- Hablame `payLoad` sub-field names beyond `balance`: MEDIUM — some fields in existing code look guessed.
- Daily-spend snapshot pitfalls: HIGH (derived from codebase + timezone config).

**Research date:** 2026-07-30
**Valid until:** ~2026-08-29 (API contracts stable; re-verify if 2captcha ships an API v3)
