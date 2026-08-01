# Deferred Items — Quick Task 260801-hvd

## Volt's `layout()` global helper has no effect on class-based full-page Volt components

**Found during:** Task 3 (public self-registration page for invited leaders)

**Issue:** Every existing `Volt::route(...)` page in this codebase declares its layout with
`use function Livewire\Volt\layout; layout('components.layouts::xxx', ['title' => '...']);`
at the top of a class-based SFC (`new class extends Component { ... }`). Empirically confirmed
(via a debug dump of `PageComponentConfig` inside `vendor/livewire/livewire`, and cross-checked
with `php artisan view:clear` to rule out caching) that this call has **zero effect** in the
installed `livewire/volt` version for class-based components — every single page silently falls
back to Livewire's default layout (`config('livewire.layout')` = `components.layouts.app`),
regardless of what `layout()` specifies.

Confirmed reproducible on:
- `resources/views/livewire/leader/register-voter.blade.php` (`layout('components.layouts::leader', ...)` → actually renders with `components.layouts.app`)
- `resources/views/livewire/coordinator/leaders.blade.php` (wants `app`, so the bug is invisible there — the default happens to match)

**Why this wasn't caught before:** every existing page that specifies a non-`app` layout
(`leader`, `auth`, etc.) is only ever visited by an *authenticated* user, so the `app` layout's
sidebar (`components/layouts/app/sidebar.blade.php`, which calls `auth()->user()->hasRole(...)`)
still renders without error — just with the wrong chrome. This task's new page
(`public.register-leader`, `registro-lider/{token}`) is the **first genuinely unauthenticated**
full-page Volt route in the app, so the same silent fallback to the `app` layout produced a hard
crash (`Call to a member function hasRole() on null`) instead of a cosmetic mismatch.

**Fix applied (scoped to this task only):** `public.register-leader` uses Livewire's native
`#[Livewire\Attributes\Layout('components.layouts.public', [...])]` PHP attribute on the
anonymous class instead of Volt's `layout()` helper — confirmed via the same debug method to
correctly resolve `components.layouts.public`.

**Not fixed (out of scope):** every other existing page still uses the ineffective `layout()`
helper and therefore still renders with `components.layouts.app` regardless of its stated
intent (e.g. `leader.register-voter`, `leader.dashboard`, `leader.my-voters` all likely render
with the admin/coordinator sidebar instead of a leader-specific one, if `components.layouts.leader`
differs meaningfully from `components.layouts.app`). Recommended follow-up: either (a) upgrade
`livewire/volt` and re-verify, or (b) migrate every `layout('...')` call in
`resources/views/livewire/**/*.blade.php` to the `#[Layout(...)]` attribute pattern proven here.
