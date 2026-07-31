---
phase: quick-260730-tsk
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Services/HablameSmsService.php
  - tests/Feature/HablameSmsServiceTest.php
  - resources/views/filament/components/saldos-badge.blade.php
  - app/Services/SaldoColorResolver.php
  - tests/Feature/SaldoColorResolverTest.php
autonomous: true
requirements: [QUICK-260730-TSK]
user_setup: []

must_haves:
  truths:
    - "getAccountInfo() returns the real availableBalance (COP) as balance for a live v5 account response, not null"
    - "getAccountInfo() returns accountId, billingType, and a derived active/blocked status matching the real v5 payLoad shape"
    - "The saldos-badge dropdown renders via Filament's native dropdown/icon-button/badge components with the same open/close behavior as the topbar user menu"
    - "A super_admin still sees the saldos-badge in the admin topbar; a non-super-admin still does not"
    - "The two balance badges color correctly (success/warning/danger/gray) using Filament color names, thresholds unchanged"
  artifacts:
    - path: "app/Services/HablameSmsService.php"
      provides: "getAccountInfo() reading payLoad.accountId / payLoad.billing.availableBalance / payLoad.billing.billingType / payLoad.createdAt + derived status from payLoad.blockStatus"
      contains: "availableBalance"
    - path: "resources/views/filament/components/saldos-badge.blade.php"
      provides: "Native Filament dropdown/icon-button/badge restyle, @php logic block unchanged, id=saldos-badge preserved"
      contains: "x-filament::dropdown"
    - path: "app/Services/SaldoColorResolver.php"
      provides: "hablame()/twoCaptcha() returning Filament color-name strings (success/warning/danger/gray)"
      contains: "success"
    - path: "tests/Feature/HablameSmsServiceTest.php"
      provides: "getAccountInfo test using the real v5 JSON shape"
      contains: "availableBalance"
    - path: "tests/Feature/SaldoColorResolverTest.php"
      provides: "Threshold-to-Filament-color-name coverage for both resolvers"
      contains: "SaldoColorResolver"
  key_links:
    - from: "app/Services/HablameSmsService.php"
      to: "billing.availableBalance"
      via: "getAccountInfo balance mapping"
      pattern: "billing.*availableBalance"
    - from: "resources/views/filament/components/saldos-badge.blade.php"
      to: "App\\Services\\SaldoColorResolver"
      via: "x-filament::badge :color prop"
      pattern: "SaldoColorResolver::(hablame|twoCaptcha)"
---

<objective>
Fix two bugs found while browser-verifying the super_admin-only "saldos-badge" topbar widget (shipped in quick task 260730-ofo, already on main):

1. `HablameSmsService::getAccountInfo()` reads JSON paths that do not exist in the real Hablame v5 `/account/v5/info` response, so `balance` (and account_id / billing_type / status) always come back null — the Hablame badge always shows N/D.
2. `saldos-badge.blade.php` is hand-rolled Tailwind/Alpine that does not match Filament's native topbar dropdown look. Restyle it with Filament's shipped Blade UI components.

Purpose: The badge must show the real live Hablame balance and look native (client reference: the topbar user-avatar dropdown).
Output: Corrected field mapping + tests; presentation-only restyle using `filament/support` components + a color-contract adjustment on `SaldoColorResolver`.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@app/Services/HablameSmsService.php
@app/Console/Commands/TestHablameSms.php
@resources/views/filament/components/saldos-badge.blade.php
@app/Services/SaldoColorResolver.php
@tests/Feature/HablameSmsServiceTest.php
@tests/Feature/Filament/SaldosBadgeTest.php

<interfaces>
<!-- Real Hablame v5 GET {api_url}/account/v5/info response (confirmed live this session): -->
```json
{"payLoad":{"accountId":10011897,"billing":{"availableBalance":25228,"billingAccount":99910011897,"billingCountry":null,"billingType":"prepaid","monthlyCreditLimit":0,"monthlyUsage":null,"taxId":null},"blockStatus":{"billing":false,"fraud":false,"general":false},"createdAt":"2018-04-19T23:57:54-05:00","currency":"","email":null,"paymentUrl":null},"responseTime":8.62,"statusCode":200,"statusMessage":"OK","timeStamp":"2026-07-30T21:25:10-05:00"}
```
Note: `currency` came back as "" (empty) for this account — do NOT assume it is populated. SaldoColorResolver::hablame() already treats the balance as COP; keep that assumption.

<!-- Current buggy getAccountInfo() mapping (HablameSmsService.php ~lines 272-279): -->
'account_id'   => $data['payLoad']['account_id'] ?? null,      // real path: payLoad.accountId
'status'       => $data['payLoad']['status'] ?? null,          // no flat field; derive from payLoad.blockStatus
'balance'      => $data['payLoad']['balance'] ?? null,         // real path: payLoad.billing.availableBalance
'billing_type' => $data['payLoad']['billing_type'] ?? null,    // real path: payLoad.billing.billingType
'created_at'   => $data['payLoad']['created_at'] ?? null,      // real path: payLoad.createdAt

<!-- Sandbox branch (getAccountInfo ~lines 248-256) already returns status 'active' — keep the derived
     live status a matching lowercase string ('active' | 'blocked') so TestHablameSms table output stays sensible. -->

<!-- TestHablameSms.php consumes getAccountInfo()['account_id'|'status'|'balance'|'billing_type'] for --check-account
     table display only (no specific string asserted). Confirm no other consumer expects a specific 'status' value:
     the only other consumers are validateApiKey() (reads ['success'] only) and the saldos-badge blade (reads ['balance']). -->

<!-- Filament badge component contract (vendor/filament/support/.../badge.blade.php): -->
<!-- @props(['color' => 'primary', ...]) then ->color(BadgeComponent::class, $color) -->
<!-- => :color accepts Filament color NAMES ('success'|'warning'|'danger'|'gray'|'primary'), NOT raw Tailwind classes. -->
<!-- Dropdown component (vendor/filament/support/.../dropdown/index.blade.php): x-data="filamentDropdown", -->
<!-- named `trigger` slot + default content slot, root div merges passed attributes (so id="saldos-badge" survives). -->
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Fix getAccountInfo() field mapping to the real Hablame v5 response shape + tests</name>
  <files>app/Services/HablameSmsService.php, tests/Feature/HablameSmsServiceTest.php</files>
  <behavior>
    - Given the real live JSON (see &lt;interfaces&gt;), getAccountInfo() returns success=true, account_id=10011897, balance=25228, billing_type='prepaid'.
    - status is derived from payLoad.blockStatus: all of billing/fraud/general false ⇒ 'active'; any true ⇒ 'blocked'.
    - created_at reads payLoad.createdAt.
    - Sandbox branch unchanged (still success/account_id='sandbox_account'/status='active'/balance=999.99/billing_type='prepaid').
    - A blocked-account fake (e.g. blockStatus.general=true) ⇒ status='blocked'.
  </behavior>
  <action>
    In `getAccountInfo()` (the `$response->successful()` branch, ~lines 269-280) replace the four wrong paths with the real ones per &lt;interfaces&gt;:
    - `account_id` → `$data['payLoad']['accountId'] ?? null`
    - `balance` → `$data['payLoad']['billing']['availableBalance'] ?? null`
    - `billing_type` → `$data['payLoad']['billing']['billingType'] ?? null`
    - `created_at` → `$data['payLoad']['createdAt'] ?? null`
    - `status` → derive from `$data['payLoad']['blockStatus'] ?? []`: compute a local `$blockStatus` array, then set `'status'` to `'active'` when none of `billing`/`fraud`/`general` are truthy, else `'blocked'`. Use explicit boolean checks (`(bool) ($blockStatus['general'] ?? false)` etc.), curly braces if you introduce any control structure, and a short PHPDoc line over the derivation if not self-evident (no inline `//` narration). Keep the method's explicit `: array` return type and existing error/exception branches untouched.
    Do NOT change `SaldoColorResolver`'s COP assumption or any currency handling. This is a pre-existing bug affecting every consumer of `getAccountInfo()`.

    Then update the existing `test('can get real account info', ...)` in tests/Feature/HablameSmsServiceTest.php (~line 294) so its `Http::fake()` returns the real v5 shape from &lt;interfaces&gt; (payLoad.accountId + payLoad.billing.availableBalance/billingType + payLoad.blockStatus + payLoad.createdAt) and assert `account_id === 10011897`, `balance === 25228`, `billing_type === 'prepaid'`, `status === 'active'`. Add one more test asserting a `blockStatus.general = true` fake yields `status === 'blocked'`. Leave the sandbox-mode test unchanged. Use `use function Pest\Laravel\...` only if a sibling test already does; otherwise match the file's existing `Http::fake()` + `app(HablameSmsService::class)` style.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/HablameSmsServiceTest.php --filter="account info"</automated>
  </verify>
  <done>getAccountInfo() returns real balance/account_id/billing_type from the v5 shape and a derived active/blocked status; sandbox path unchanged; new + updated tests green.</done>
</task>

<task type="auto">
  <name>Task 2: Restyle saldos-badge with native Filament components + switch SaldoColorResolver to Filament color names</name>
  <files>resources/views/filament/components/saldos-badge.blade.php, app/Services/SaldoColorResolver.php, tests/Feature/SaldoColorResolverTest.php</files>
  <action>
    Presentation-only restyle. Keep the entire `@php ... @endphp` block (lines 1-23) EXACTLY as-is — snapshot read, 1h Hablame cache, `TwoCaptchaDailyCostService::lastDays(7)`, `CampaignContext::isSuperAdmin()` early-return gate. Do NOT touch `CampaignContext::isSuperAdmin()`.

    Markup (lines 25-86) — rebuild with Filament's shipped Blade UI components (all ship via `filament/support`, no new Composer deps):
    - Replace the hand-rolled `<div x-data="{open:false}" @click.outside>` wrapper + raw panel div with `<x-filament::dropdown>`. Pass `id="saldos-badge"` on the dropdown root (it merges onto the component's root div — the `saldos-badge` string MUST remain in the rendered HTML so SaldosBadgeTest's `assertSee('saldos-badge')` keeps passing). Use a `width="xs"` (or similar small named width) for a compact panel.
    - Trigger: `<x-slot name="trigger"><x-filament::icon-button icon="heroicon-o-banknotes" label="Saldos" /></x-slot>` (replaces the raw `<button>` + heroicon).
    - Default slot (panel content): keep the same three sections (Saldo Hablame row, Saldo 2captcha row, "Costo promedio 2captcha (últimos 7 días)" 7-day list). For the two balance values use `<x-filament::badge :color="SaldoColorResolver::hablame($hablameBalance)">…</x-filament::badge>` and `…twoCaptcha($captchaBalance)…` — the badge `:color` prop takes Filament color NAMES (confirmed from the component's `@props`), so the resolver must now return names, not Tailwind classes. Preserve the exact value formatting: Hablame `number_format($hablameBalance, 0, ',', '.') . ' COP'` or `N/D`; 2captcha `'$' . number_format($captchaBalance, 5)` or `N/D`. Keep the 7-day `@foreach` list and its `DailyCaptchaCostStatus` Computed/RechargeDetected/— branches unchanged in logic (free-form content in the default slot is fine; do NOT force rows into dropdown.list.item — those are for clickable actions). Add small section labels with plain text/`<x-filament::text>` as needed for the native padded look.
    - Follow CLAUDE.md: no namespace-alias `use` inside blade; keep the existing explicit `use` imports in the `@php` block.

    In `app/Services/SaldoColorResolver.php`: change the four color constants from Tailwind class strings to Filament color-name strings — `GREEN => 'success'`, `YELLOW => 'warning'`, `RED => 'danger'`, `GRAY_UNAVAILABLE => 'gray'`. Keep both methods' `: string` return type, the `?float` params, the null→gray guard, and ALL numeric threshold constants (TWOCAPTCHA_GREEN_MIN_USD/…, HABLAME_GREEN_MIN_COP/… and their `// TODO ajustar` provisional note) unchanged — only the returned representation changes.

    Add tests/Feature/SaldoColorResolverTest.php (Pest, `php artisan make:test --pest SaldoColorResolverTest` then fill in — or write directly matching sibling style): assert `twoCaptcha(null)` and `hablame(null)` return `'gray'`; a value ≥ green threshold returns `'success'`; a mid value returns `'warning'`; a below-yellow value returns `'danger'`, for both methods. Datasets encouraged for the threshold rows.
  </action>
  <verify>
    <automated>vendor/bin/pint --dirty && php artisan test tests/Feature/SaldoColorResolverTest.php tests/Feature/Filament/SaldosBadgeTest.php</automated>
  </verify>
  <done>saldos-badge renders via x-filament::dropdown/icon-button/badge with unchanged @php logic and preserved `saldos-badge` id; SaldoColorResolver returns Filament color names; resolver tests + existing SaldosBadgeTest gating tests green; Pint clean.</done>
</task>

</tasks>

<verification>
- `php artisan test tests/Feature/HablameSmsServiceTest.php tests/Feature/SaldoColorResolverTest.php tests/Feature/Filament/SaldosBadgeTest.php` all green.
- `vendor/bin/pint --dirty` reports no outstanding issues.
- Manual (post-execution, per the user's browser-verify-before-prod rule — NOT part of automated done): as super_admin on `/admin`, open the Saldos dropdown, confirm it looks native (same padding/radius/open-close as the user menu) and both badges show real non-null values (Hablame ~25.228 COP, 2captcha ~$29.18) with correct colors.
</verification>

<success_criteria>
- getAccountInfo() maps to payLoad.accountId / payLoad.billing.availableBalance / payLoad.billing.billingType / payLoad.createdAt and a derived active|blocked status; sandbox branch unchanged.
- saldos-badge uses native Filament dropdown/icon-button/badge; @php data/gating logic byte-for-byte unchanged; `saldos-badge` id preserved.
- SaldoColorResolver returns Filament color names with unchanged numeric thresholds.
- All listed tests pass; Pint clean; no new Composer dependencies.
</success_criteria>

<output>
After completion, create `.planning/quick/260730-tsk-fix-hablamesmsservice-getaccountinfo-fie/260730-tsk-SUMMARY.md`
</output>
