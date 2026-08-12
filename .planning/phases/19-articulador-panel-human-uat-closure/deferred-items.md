# Deferred Items — Phase 19

## `php artisan test --testsuite=Browser` reports "No tests found" (pre-existing, out of scope)

**Found during:** Plan 19-02, Task 2

**Issue:** `phpunit.xml`'s `<testsuites>` block only defines `Unit` and `Feature` — there is no `Browser` (or `E2E`) testsuite entry, even though `tests/Pest.php` groups tests into `->in('Browser')` / `->in('E2E')` for Pest-level configuration (RefreshDatabase binding). Pest's `->in()` grouping is unrelated to PHPUnit's `<testsuites>` XML, which is what `--testsuite=Browser` actually reads. As a result, `php artisan test --testsuite=Browser` silently exits 0 with "No tests found" instead of running `tests/Browser/*`.

**Not fixed:** `phpunit.xml` is not in this plan's `files_modified` list and this gap predates this plan (it already existed before 19-02's changes; the plan's Task 2 verification command just happens to rely on it). Fixing it (adding a `<testsuite name="Browser"><directory>tests/Browser</directory></testsuite>` entry, and possibly one for `E2E`) is a one-line, low-risk fix but touches shared test-suite configuration that other phases/plans may also depend on — deferred rather than auto-fixed under this plan's narrow scope.

**Workaround used for this plan's actual verification:** Ran `php artisan test tests/Browser/RegistraduriaPollingResilienceTest.php` directly, which correctly discovered and ran both tests (2 passed, 2 assertions, no PHP fatal / no "Cannot redeclare" error), satisfying the plan's actual `<done>` criteria.
