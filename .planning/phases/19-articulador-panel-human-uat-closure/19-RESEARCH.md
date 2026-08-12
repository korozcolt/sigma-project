# Phase 19: Articulador Panel Human-UAT Closure - Research

**Researched:** 2026-08-11
**Domain:** Pest v4 Browser testing (real Chromium) for Livewire Volt + Filament panel UI behaviors
**Confidence:** HIGH

## Summary

This phase does not build new product behavior — it closes 3 `pending` human-verification items from `15-HUMAN-UAT.md` by replacing manual browser clicking with genuine Pest v4 Browser (real Chromium) tests. All the application code under test already exists and is unchanged by this phase; the deliverable is purely `tests/Browser/*.php` files plus the `15-HUMAN-UAT.md` frontmatter/body update.

The codebase already has exactly one precedent for real-browser Pest testing: `tests/Browser/RegistraduriaPollingResilienceTest.php` (quick task `260731-g7h`). It proves the single hardest fact this phase needs: `Pest\Browser\Drivers\LaravelHttpServer` dispatches every browser request through a genuine `HttpKernel::handle()` call using the browser's real cookies, so `actingAs()` never authenticates the real browser session — only a real `/login` form submission does. However, **the `loginRealBrowserUser()` helper is defined as a local top-level function inside that one test file, not in `tests/Pest.php` or a shared helper file** — it is not currently reusable and must be re-declared (ideally promoted to a shared location) for this phase's 3 new test files.

Investigation of the actual widget source code surfaced an important, code-verified nuance for criterion 1: of the three dashboard widgets, only `TopLeadersTable` is scoped per-articulador (via `User::teamCoordinatorUserIds()`, added in Phase 13/18 for `AUTHZ-01`). `CampaignStatsOverview` and `TerritorialDistributionChart` have no `AREA_COORDINATOR` branch in their scoping logic at all — an articulador sees the same full-campaign totals an `admin_campaign`/`super_admin` would see, by design (confirmed against `REQUIREMENTS.md`'s literal `AUTHZ-01` wording, which names only `TopLeadersTable`/`TopLeadersExport`/`LeadersExportController`). The browser test for criterion 1 must assert this actual mixed-scoping behavior, not a uniform "team-only" behavior — see Open Questions.

**Primary recommendation:** Reuse the `RegistraduriaPollingResilienceTest.php` real-login pattern verbatim (promoted into a shared, single-declaration helper), reuse `ArticuladorTeamResolutionTest.php`'s two-articulador/two-coordinador fixture pattern for criterion 1's multi-tenant setup, and assert the widgets' actual mixed scoping (campaign-wide for 2 widgets, team-scoped for 1) rather than a uniform "no cross-articulador leakage" claim that the current code does not implement for `CampaignStatsOverview`/`TerritorialDistributionChart`.

<phase_requirements>
## Phase Requirements

No new REQ-IDs. This phase closes tech-debt items recorded in `.planning/v1.2-MILESTONE-AUDIT.md` under `tech_debt` for `phase: 15-articulador-self-service-panel` and mirrored in `15-HUMAN-UAT.md`. Traceability is to that tech-debt entry, not to `REQUIREMENTS.md`.

| Tech-debt item | Description | Research Support |
|----|-------------|------------------|
| 1 | Articulador panel dashboard widget data scoping (no browser confirmation of no cross-campaign/cross-articulador leakage) | Widget source code read directly (`CampaignStatsOverview.php`, `TerritorialDistributionChart.php`, `TopLeadersTable.php`); existing Feature-test fixture patterns (`OwnershipScopedWidgetsTest.php`, `ArticuladorTeamResolutionTest.php`) identified as the exact multi-tenant setup to mirror in a Browser test |
| 2 | Cédula autofill lock/unlock on create-coordinador form | `create-coordinator.blade.php` read line-by-line and diffed against `create-leader.blade.php`; confirmed identical `updatedDocumentNumber()`/`unlockName()`/`nameLocked` pattern; existing Feature-level Livewire test (`CreateLeaderIdentityLookupTest.php`) identified as the assertion pattern to mirror in a Browser test |
| 3 | Navigation click-through (Dashboard → Coordinadores → Día D + Filament nav item) | `sidebar.blade.php` and Flux's `navlist/item.blade.php` + `button-or-link.blade.php` read directly; confirmed the "current" indicator is a `data-current` attribute (not `aria-current`), and Pest v4 Browser's `assertDataAttribute()` method matches it exactly |

</phase_requirements>

## Standard Stack

### Core (already installed, versions verified from `composer show`)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| pestphp/pest | v4.1.3 (released 2025-10-29) | Test framework | Project standard, `tests/Pest.php` already configures `Feature`, `Browser`, `E2E` suites |
| pestphp/pest-plugin-browser | v4.1.1 (released 2025-09-29) | Real-Chromium browser testing via Playwright | Project's established mechanism for UI behaviors that Feature/Livewire tests structurally cannot verify (visual state, real cookies, `wire:navigate`) |
| pestphp/pest-plugin-laravel | v4.0 | Laravel-specific Pest helpers | Already a project dependency |

No new packages need to be installed. `tests/Pest.php` already binds `RefreshDatabase` to the `Browser` suite (`pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in('Browser')`).

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Playwright Chromium binary | 1.58.2 (npm), browser cached locally | Real browser instance driven by pest-plugin-browser | Already installed at `~/Library/Caches/ms-playwright` on this machine (`chromium-1234` present) — no `npx playwright install chromium` needed here, but future worktrees/CI may need it (see Common Pitfalls) |

**Version verification performed:**
```
composer show pestphp/pest              # v4.1.3, released 2025-10-29
composer show pestphp/pest-plugin-browser  # v4.1.1, released 2025-09-29
npx playwright --version                 # 1.58.2
```

### Alternatives Considered

None — the project has exactly one established convention for this class of test (Pest v4 Browser, real Chromium, real login), set by quick task `260731-g7h` and referenced explicitly in this phase's own description. No alternative (Dusk, Playwright standalone, Cypress) is in the stack or should be introduced.

## Architecture Patterns

### Recommended file layout
```
tests/Browser/
├── RegistraduriaPollingResilienceTest.php   # existing — do not modify
├── ArticuladorDashboardWidgetScopingTest.php   # NEW — criterion 1
├── ArticuladorCreateCoordinatorAutofillTest.php # NEW — criterion 2
└── ArticuladorNavigationClickThroughTest.php    # NEW — criterion 3
```

One file per human-UAT item mirrors the 1:1 mapping already implied by `15-HUMAN-UAT.md`'s 3 numbered tests, and keeps each file's `beforeEach`/fixture scope narrow (multi-tenant fixtures for criterion 1 are heavier than criteria 2/3 need).

### Pattern 1: Real browser login helper (MUST be de-duplicated, not copy-pasted 3x)

**What:** A function that performs a genuine `/login` form submission (not `actingAs()`) so the real browser's session cookie is authenticated.

**Why it must not simply be copy-pasted into 3 new files:** PHP fatal-errors on duplicate function declarations in the same process. Pest can (depending on run order/parallelization) load multiple `Browser` test files into the same PHP process. `RegistraduriaPollingResilienceTest.php` already declares a **global** `function loginRealBrowserUser(): void`. If any new test file in this phase re-declares the same top-level function name, running the whole `Browser` suite in one process risks `Cannot redeclare loginRealBrowserUser()`. This was not previously hit because only one Browser test file existed.

**Recommended fix (pick one, plan should choose explicitly):**
1. **Promote to `tests/Pest.php`** inside the Browser-suite closure, so it is declared exactly once and available to every file in `tests/Browser/`. This is the pattern the project's own `tests/Pest.php` file structure (`Functions` section, currently only a placeholder `something()`) was designed for.
2. Or extract to a `tests/Browser/Concerns/LoginsRealBrowserUser.php` trait/helper file with a differently-named function per test file (`loginRealBrowserUser()` already exists — a rename or namespacing is needed either way).

**Verified source (existing precedent, read directly):**
```php
// tests/Browser/RegistraduriaPollingResilienceTest.php (current, file-local — needs promotion)
function loginRealBrowserUser(): void
{
    User::factory()->create([
        'email' => 'browser-test@example.com',
        'password' => 'password',
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $page = visit('/login');
    $page->type('email', 'browser-test@example.com');
    $page->type('password', 'password');
    $page->click('Ingresar');
    $page->wait(1);
}
```

**Improvement available:** `database/factories/UserFactory.php` already has a `withoutTwoFactor()` state (`->state(fn () => ['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])`). The existing helper sets the two 2FA fields manually instead of calling this state — the new/promoted helper should use `User::factory()->withoutTwoFactor()->create([...])` for consistency with the rest of the test suite, and to also null `two_factor_recovery_codes` (the existing helper's raw field list omits it, harmlessly since it's not confirmed, but the factory state is the more correct/DRY choice).

**This phase's tests need distinct roles**, so the helper needs a `User $user` parameter rather than always creating its own throwaway user — e.g. `loginRealBrowserUser(User $user, string $password = 'password'): void`, called after the caller has built the exact articulador/coordinador fixture it needs (with `withoutTwoFactor()` applied and the known password set).

### Pattern 2: Multi-tenant fixture for widget scoping (criterion 1)

**What:** Two articuladores (optionally in the same campaign, to prove per-articulador and not just per-campaign scoping) each with their own coordinador(es) and líder(es) and voters, mirroring `tests/Feature/ArticuladorTeamResolutionTest.php`'s exact fixture shape.

**Verified working pattern (existing Feature test, read directly, in-process `actingAs()`/`Session::put()` — NOT directly reusable in a Browser test, see below):**
```php
$this->campaignA = Campaign::factory()->create(['status' => 'active']);

$this->areaCoordinator = User::factory()->create();
$this->areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
$this->areaCoordinator->campaigns()->attach($this->campaignA->id);

$this->coordinatorX = User::factory()->create(['area_coordinator_user_id' => $this->areaCoordinator->id]);
$this->coordinatorX->assignRole(UserRole::COORDINATOR->value);
$this->coordinatorX->campaigns()->attach($this->campaignA->id);

$this->leaderX1 = User::factory()->create(['coordinator_user_id' => $this->coordinatorX->id]);
$this->leaderX1->assignRole(UserRole::LEADER->value);
$this->leaderX1->campaigns()->attach($this->campaignA->id);

Voter::factory()->count(2)->create(['campaign_id' => $this->campaignA->id, 'registered_by' => $this->leaderX1->id]);
```

**Critical adaptation needed for a real-browser test — `CampaignContext` cannot be pre-seeded via `Session::put()`:**

`ArticuladorTeamResolutionTest.php` and `OwnershipScopedWidgetsTest.php` both call `Session::put('campaign_context.campaign_id', ...)` **before** `actingAs()`/before the request — this works only because Feature tests share the PHP process with the assertion code. A real Pest v4 Browser test's HTTP requests go through the browser's actual cookies/session via `LaravelHttpServer`, so in-process `Session::put()` before `visit()` has no effect on the browser's real session.

**Resolution (verified via `app/Services/CampaignContext.php` source):** for a non-super-admin user with no session campaign context set, `CampaignContext::currentCampaignId()` falls back to `$user->campaigns()->orderBy('campaign_user.assigned_at')->orderBy('campaigns.id')->value('campaigns.id')` — i.e., the user's **first attached campaign**. Give each test articulador exactly one attached campaign and the correct campaign resolves automatically with zero special session setup. Do not attempt to fake `campaign_context` session state in the Browser tests.

### Pattern 3: Cédula autofill/lock browser interaction (criterion 2)

**Confirmed identical implementation in `create-coordinator.blade.php` and `create-leader.blade.php`:**
```php
public bool $nameLocked = false;

public function updatedDocumentNumber(): void
{
    $this->nameLocked = false;
    if (blank($this->document_number)) { return; }

    $identity = app(IdentityLookupService::class)->findByDocumentNumber($this->document_number);
    if ($identity) {
        $this->name = preg_replace('/\s+/', ' ', trim("{$identity->nombre1} {$identity->nombre2} {$identity->apellido1} {$identity->apellido2}"));
        $this->nameLocked = true;
    }
}

public function unlockName(): void { $this->nameLocked = false; }
```
Blade: `wire:model.blur="document_number"` triggers the update on blur; the name `flux:input` has `:disabled="$nameLocked"`; an unlock `flux:button` (`wire:click="unlockName"`) is conditionally rendered `@if($nameLocked)`.

`IdentityLookupService::findByDocumentNumber()` is a plain `NationalIdentityRecord::query()->where('cedula', ...)->first()` DB lookup — **no external HTTP call**, so no `Http::fake()` is needed for this criterion (unlike the Registraduría polling test).

**Existing Feature-level (non-browser) test to mirror the assertions from:** `tests/Feature/Coordinator/CreateLeaderIdentityLookupTest.php` — tests the same pattern on `create-leader` via `Volt::test(...)->set('document_number', ...)->assertSet('nameLocked', true)`. No equivalent Feature or Browser test exists yet for `articulador.create-coordinator` at either level — this phase (or an earlier untracked gap) is the first coverage of it in any form.

**Browser-specific interaction sequence:**
```php
NationalIdentityRecord::factory()->create([
    'cedula' => '1053006255', 'nombre1' => 'Lanna', 'nombre2' => 'Javiana',
    'apellido1' => 'Contreras', 'apellido2' => 'Ortega',
]);

$page = visit(route('articulador.coordinadores.create'));
$page->type('document_number', '1053006255');
$page->keys('document_number', 'Tab'); // triggers the native blur event that wire:model.blur listens for
$page->assertValue('name', 'Lanna Javiana Contreras Ortega');
$page->assertDisabled('name');
$page->click('¿Nombre incorrecto? Editar manualmente'); // the unlock button's visible text
$page->assertEnabled('name');
```
**Note:** pest-plugin-browser's `InteractsWithElements` has no dedicated `blur()` method (confirmed via `grep` on the plugin source). Use `keys($selector, 'Tab')` to move focus off the field (fires a real blur), or `click()` a different field/label immediately after `type()`.

### Pattern 4: Navigation click-through + "current" assertion (criterion 3)

**Confirmed via `vendor/livewire/flux/stubs/resources/views/flux/button-or-link.blade.php`:** Flux's `:current` prop on `flux:navlist.item` renders as a `data-current` HTML attribute (via `$attributes->merge(['data-current' => $current])`), **not** `aria-current`. `sidebar.blade.php`'s three `area_coordinator` nav items already pass explicit `:current="request()->routeIs(...)"` / `:current="request()->is(...)"` booleans (lines 47-49), so the attribute reflects real route-matching state, not Flux's own auto-detection fallback.

Pest v4 Browser ships a purpose-built assertion for this: `Webpage::assertDataAttribute(string $selector, string $attribute, string|int|float $value): Webpage` (confirmed in `vendor/pestphp/pest-plugin-browser/src/Api/Concerns/MakesElementAssertions.php`), which internally calls `assertAttribute($selector, 'data-'.$attribute, $value)`.

```php
$page = visit(route('filament.area_coordinator.pages.dashboard'));
$page->assertDataAttribute('a[href="'.route('filament.area_coordinator.pages.dashboard').'"]', 'current', '1');

$page->click('Coordinadores');
$page->assertUrlIs(route('articulador.coordinadores'));
$page->assertDataAttribute('a[href="'.route('articulador.coordinadores').'"]', 'current', '1');

$page->click('Día D');
$page->assertPathIs('/articulador/dia-d');
```
(Boolean `true`/`false` PHP values cast to `"1"`/`""` when rendered as HTML attribute strings by Laravel's `ComponentAttributeBag` — assert `'1'` for the active item; do not assert an exact `'0'`/`''` value for inactive items, assert absence or a different selector instead, since the empty-string cast is a Blade implementation detail worth avoiding coupling a test to.)

`assertRoute(string $route, array $parameters = [])` (also in `MakesUrlAssertions.php`) is a cleaner alternative to `assertUrlIs(route(...))` — it resolves the route internally and compares paths.

**`wire:navigate` handling:** No special Pest Browser API is needed — real browser clicks on `wire:navigate` links behave like real SPA-style navigations in a real Chromium tab; `assertUrlIs`/`assertPathIs`/`assertRoute` after a `click()` naturally wait for the navigation via Pest's underlying Playwright-driven page (the existing Registraduría test's `$page->wait(...)` polling loop pattern is for async Alpine state, not needed here — a direct post-`click()` assertion should suffice, but add one `$page->wait(1)` after each nav click if flakiness appears, matching the existing precedent's conservative style).

### Anti-Patterns to Avoid
- **Copy-pasting `loginRealBrowserUser()` verbatim into 3 new files:** causes a PHP fatal redeclare error the moment the whole `Browser` suite runs in one process. Promote/share it (see Pattern 1).
- **Seeding `campaign_context` via `Session::put()` before a Browser test's `visit()` call:** silently has no effect — the real browser's session is separate from the test process's in-memory session. Give each fixture user exactly one campaign instead.
- **Registering scratch test routes (`Route::get('/__test/...')`) for criteria 2 and 3:** unlike the Registraduría precedent, these criteria test *real, already-routed* pages (`route('articulador.coordinadores.create')`, `route('filament.area_coordinator.pages.dashboard')`, etc.) — no scratch route or `Blade::render()`-inside-closure workaround is needed. That workaround was specific to testing a raw Blade partial in isolation.
- **Asserting a uniform "no cross-articulador leakage" across all 3 dashboard widgets:** the actual code only enforces this for `TopLeadersTable`. See Open Questions.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Authenticating a real Playwright browser session | A custom cookie/session injection shim | The project's established `loginRealBrowserUser()` real-`/login`-form pattern | Already proven working against this exact stack (Fortify + Livewire + real HTTP kernel dispatch); a shim would fight `LaravelHttpServer`'s real-kernel-dispatch design |
| Detecting the "current" nav item | Custom JS `document.querySelector` + manual class-string parsing via `$page->script(...)` | `Webpage::assertDataAttribute($selector, 'current', '1')` | Purpose-built Pest Browser API for exactly this attribute-assertion shape; avoids brittle JS-in-PHP-string test code |
| Cédula-exists identity data | Manually inserting into `national_identity_records` via raw DB calls | `NationalIdentityRecord::factory()->create([...])` | Existing, already-used-8x-in-the-suite factory; guarantees fillable-field correctness |

**Key insight:** every piece of infrastructure this phase needs (real login, multi-tenant fixtures, identity records, data-attribute assertions) already has a working precedent somewhere in the codebase or the Pest Browser plugin itself — this phase is assembly and adaptation, not invention.

## Common Pitfalls

### Pitfall 1: `actingAs()` does nothing for a real Browser test
**What goes wrong:** Test appears to run but every page redirects to `/login`, or the widget/form renders as if logged out.
**Why it happens:** `Pest\Browser\Drivers\LaravelHttpServer` dispatches every browser request through a fresh `HttpKernel::handle()` call using the browser's actual cookie jar; `actingAs()` only mutates the current PHP test process's in-memory Auth guard, which the separately-dispatched kernel call never sees.
**How to avoid:** Always use the real-login helper (Pattern 1) before any `visit()` call that requires authentication.
**Warning signs:** Assertions about authenticated content fail, or `$page->script(...)` shows an unauthenticated/guest state.

### Pitfall 2: Duplicate global function declaration across Browser test files
**What goes wrong:** `Cannot redeclare loginRealBrowserUser()` fatal error, likely only surfacing when the full `Browser` suite runs together (not necessarily when running a single new file in isolation, depending on Pest's process/parallelization strategy).
**Why it happens:** PHP has no per-file function namespacing at the global-function level; two files both declaring `function loginRealBrowserUser()` in the global namespace collide if loaded in the same process.
**How to avoid:** Declare the helper exactly once — in `tests/Pest.php`'s `Browser`-suite closure, or in a single shared included file — not per-test-file.
**Warning signs:** Tests pass individually (`--filter`) but fail when the whole suite runs.

### Pitfall 3: Playwright Chromium binary missing/outdated in a fresh worktree or CI
**What goes wrong:** `PlaywrightOutdatedException` on first Browser test run in an environment that hasn't run `npx playwright install chromium` before.
**Why it happens:** The `playwright` npm package can be present/current while the actual browser binary (cached outside the repo, under `~/Library/Caches/ms-playwright` on macOS) is missing — this is exactly what happened during quick task `260731-g7h`'s worktree.
**How to avoid:** Confirmed already installed on this machine (`chromium-1234` present, `npx playwright --version` → 1.58.2) — no action needed for a same-machine run. If phase execution happens in a fresh worktree/CI, run `npx playwright install chromium` first.
**Warning signs:** Browser test setup throws before any assertion runs.

### Pitfall 4: `wire:model.blur` requiring a real blur event, not just a value set
**What goes wrong:** Typing into `document_number` via `$page->type(...)` alone never fires `updatedDocumentNumber()`, so `name`/`nameLocked` never update, and the test times out waiting for autofill.
**Why it happens:** `wire:model.blur` (as opposed to `wire:model.live`) only syncs to the server on the native DOM `blur` event, which `type()` alone does not trigger unless focus moves away from the field.
**How to avoid:** Follow `type()` with `keys($selector, 'Tab')` or a `click()` on another element to force a real blur before asserting on `name`/`nameLocked`.
**Warning signs:** `assertValue('name', ...)` fails with an empty string even though the cédula exists in `NationalIdentityRecord`.

### Pitfall 5: Assuming uniform per-articulador scoping across all 3 dashboard widgets
**What goes wrong:** Writing an assertion that `CampaignStatsOverview`/`TerritorialDistributionChart` differ between two articuladores in the same campaign — this assertion will fail (correctly, per current code) because those two widgets have no `AREA_COORDINATOR` scoping branch.
**Why it happens:** `AUTHZ-01` (the requirement that added transitive-team resolution for the articulador tier) named exactly 3 surfaces to update — `TopLeadersTable`, `TopLeadersExport`, `LeadersExportController` — and `CampaignStatsOverview`/`TerritorialDistributionChart` were never in that scope. `scopedVoterQuery()` in `CampaignStatsOverview.php` and the equivalent `when()` chain in `TerritorialDistributionChart.php` only special-case `LEADER` and `COORDINATOR`; every other role (including `AREA_COORDINATOR`, `ADMIN_CAMPAIGN`, `SUPER_ADMIN`) falls through to the unfiltered, full-campaign query.
**How to avoid:** See Open Questions — confirm the intended assertion shape with the human/planner before writing criterion 1's test.
**Warning signs:** A test asserting "articulador A's total ≠ articulador B's total" for `CampaignStatsOverview` in the same campaign will fail against current code, not because the test is wrong, but because that's not how the widget is built.

## Code Examples

### Real login (to be promoted into `tests/Pest.php`, adapted to take a `User`)
```php
// Source: tests/Browser/RegistraduriaPollingResilienceTest.php (existing, file-local)
function loginRealBrowserUser(): void
{
    User::factory()->create([
        'email' => 'browser-test@example.com',
        'password' => 'password',
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $page = visit('/login');
    $page->type('email', 'browser-test@example.com');
    $page->type('password', 'password');
    $page->click('Ingresar');
    $page->wait(1);
}
```

### Data-attribute assertion for active nav state
```php
// Source: vendor/pestphp/pest-plugin-browser/src/Api/Concerns/MakesElementAssertions.php:462
public function assertDataAttribute(string $selector, string $attribute, string|int|float $value): Webpage
{
    $value = (string) $value;
    return $this->assertAttribute($selector, 'data-'.$attribute, $value);
}
```

### Widget scoping ground truth (why criterion 1's test must be asymmetric)
```php
// Source: app/Filament/Widgets/TopLeadersTable.php:46-49 — DOES scope AREA_COORDINATOR
->when(
    $user?->hasAnyRole([UserRole::COORDINATOR->value, UserRole::AREA_COORDINATOR->value]),
    fn ($query) => $query->whereIn('coordinator_user_id', $user->teamCoordinatorUserIds())
)

// Source: app/Filament/Widgets/CampaignStatsOverview.php:211-225 — does NOT scope AREA_COORDINATOR
private function scopedVoterQuery(Campaign $campaign): Builder
{
    $user = Auth::user();
    $query = Voter::where('campaign_id', $campaign->id);
    if ($user?->hasRole(UserRole::LEADER->value)) { return $query->where('registered_by', $user->id); }
    if ($user?->hasRole(UserRole::COORDINATOR->value)) { return $query->whereIn('registered_by', $user->leaders()->pluck('id')); }
    return $query; // AREA_COORDINATOR falls through here, same as admin_campaign/super_admin
}
```

## Runtime State Inventory

Not applicable — this phase is test-authoring only. No renamed identifiers, no stored data migration, no live service config, no OS-registered state, no secrets, no build artifacts affected.

## Open Questions

1. **What should criterion 1's test actually assert about `CampaignStatsOverview`/`TerritorialDistributionChart` for an articulador?**
   - What we know: current code gives an articulador the same full-campaign totals an admin sees for these 2 widgets (no per-articulador filtering), while `TopLeadersTable` is genuinely team-scoped. `15-VERIFICATION.md`'s `why_human` note says scoping is "inherited from shared widget classes and CampaignMembershipScope" — i.e., campaign-level isolation, not necessarily articulador-level. `REQUIREMENTS.md`'s `AUTHZ-01` text names only 3 surfaces for team-scoping, and neither of these 2 widgets is one of them.
   - What's unclear: whether this is intentional product behavior (these 2 widgets are meant to be "whole campaign" KPIs regardless of role, matching admin's view) or an overlooked gap that should have been closed alongside `AUTHZ-01`/Phase 18.
   - Recommendation: write the test to assert the **actual, current, code-verified behavior** (campaign-wide for 2 widgets — assert cross-*campaign* isolation only for those two; team-scoped for `TopLeadersTable` — assert cross-*articulador* isolation for that one), and have the phase's `15-HUMAN-UAT.md` closure note explicitly document this asymmetry in plain language, so a human reviewer can catch it if it's actually wrong. Do not silently assert "no leakage" in a way that would pass today but mask a real scoping gap if the original human-UAT wording ("do not leak another articulador's ... data") was meant literally for all 3 widgets. If the planner/user wants the literal reading enforced, that requires a **code change** to add `AREA_COORDINATOR` branches to `scopedVoterQuery()`/`TerritorialDistributionChart::getData()` — which is arguably out of this phase's stated scope ("None new — closes outstanding tech debt... not a new REQ-ID") and should be flagged back to the human rather than silently implemented.

2. **Where should the promoted `loginRealBrowserUser()` helper live?**
   - What we know: it currently exists only as a file-local function in `RegistraduriaPollingResilienceTest.php`; `tests/Pest.php` has a `Functions` section already designed for exactly this kind of shared helper (currently only a placeholder `something()` function).
   - What's unclear: whether the plan should modify `tests/Pest.php` (touching a shared config file) or add a new `tests/Browser/Concerns/*.php` helper file that avoids touching the existing Browser test.
   - Recommendation: promote into `tests/Pest.php`'s `Functions` section since that is the file's documented, existing purpose, and update `RegistraduriaPollingResilienceTest.php` to call the shared version instead of its own copy (removing the duplicate) — but only if the plan explicitly scopes this as a small refactor; otherwise, a same-named-once file (e.g. `tests/Browser/helpers.php`, autoloaded via `composer.json`'s `autoload-dev` files, or a `beforeEach` trait) also works without touching the existing precedent file at all.

3. **Exact `15-HUMAN-UAT.md` closure format — no precedent exists in this codebase.**
   - What we know: `15-HUMAN-UAT.md` is the only `HUMAN-UAT.md` file in `.planning/phases/` — no other phase has gone through this exact closure step before, so there is no established "before/after" example to copy from.
   - What's unclear: whether `status: partial` in frontmatter should become `status: resolved`, `status: passed`, or something else; whether the `## Gaps` section (currently empty) needs new content.
   - Recommendation: follow the file's own internal schema literally — flip each of the 3 items' `result: [pending]` to `result: passed`, update `## Summary` (`passed: 3, pending: 0`), and set frontmatter `status: partial` → `status: resolved` (mirroring the "resolved" vocabulary already used in `15-VERIFICATION.md`'s own `re_verification.gaps_remaining: []` / `gaps_closed` pattern for the same phase). This is a LOW-confidence inference (no direct precedent), so the plan should treat the exact frontmatter key/value as a small, low-risk judgment call rather than something requiring further research.

## Sources

### Primary (HIGH confidence — direct source reads in this repository)
- `tests/Browser/RegistraduriaPollingResilienceTest.php` — real-login pattern, only existing Browser test
- `.planning/quick/260731-g7h-fix-voterpolicy-multi-role-exclusion-bug/260731-g7h-SUMMARY.md` — documented pitfalls from building the precedent
- `app/Filament/Widgets/CampaignStatsOverview.php`, `TerritorialDistributionChart.php`, `TopLeadersTable.php` — widget scoping ground truth
- `resources/views/livewire/articulador/create-coordinator.blade.php`, `resources/views/livewire/coordinator/create-leader.blade.php` — autofill/lock pattern, confirmed identical
- `resources/views/components/layouts/app/sidebar.blade.php`, `vendor/livewire/flux/stubs/resources/views/flux/navlist/item.blade.php`, `.../flux/button-or-link.blade.php` — `data-current` attribute mechanism
- `vendor/pestphp/pest-plugin-browser/src/Api/Concerns/MakesElementAssertions.php`, `MakesUrlAssertions.php`, `InteractsWithElements.php` — available assertion/interaction API surface (`assertDataAttribute`, `assertRoute`, `keys`, `type`, `click`, no `blur()`)
- `app/Services/CampaignContext.php` — campaign resolution fallback to first attached campaign
- `tests/Feature/ArticuladorTeamResolutionTest.php`, `tests/Feature/OwnershipScopedWidgetsTest.php`, `tests/Feature/Coordinator/CreateLeaderIdentityLookupTest.php`, `tests/Feature/Articulador/ArticuladorNavigationTest.php` — existing fixture/assertion patterns to mirror
- `database/factories/UserFactory.php` — `withoutTwoFactor()` state
- `app/Services/IdentityLookupService.php` — confirms plain DB lookup, no external HTTP call
- `routes/web.php` — confirmed route names (`articulador.coordinadores`, `articulador.coordinadores.create`, `filament.area_coordinator.pages.dashboard`, `/articulador/dia-d`)
- `composer show pestphp/pest`, `composer show pestphp/pest-plugin-browser`, `npx playwright --version` — verified current versions
- `.planning/REQUIREMENTS.md`, `.planning/phases/15-articulador-self-service-panel/15-HUMAN-UAT.md`, `15-VERIFICATION.md`, `.planning/v1.2-MILESTONE-AUDIT.md`, `.planning/STATE.md` — phase history and the exact 3 human-UAT items

### Secondary / Tertiary
None used — every finding in this document is backed by a direct read of project source, vendor source, or a `composer`/`npx` version check.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — versions verified directly via `composer show`/`npx`, no new dependencies
- Architecture (real-login pattern, fixture patterns, `data-current` mechanism): HIGH — all read directly from source, not inferred
- Widget scoping asymmetry (Open Question 1): HIGH confidence in the code fact, LOW/needs-human-decision on what the test *should* assert
- HUMAN-UAT closure format (Open Question 3): LOW confidence — no precedent exists in this codebase to verify against

**Research date:** 2026-08-11
**Valid until:** Stable — this phase's dependencies (Pest v4, pest-plugin-browser, the widget/form/sidebar code under test) are all pinned/frozen by Phase 15's completion; re-verify only if Phase 15's code is touched again before this phase executes.
