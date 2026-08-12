---
phase: quick-260812-abw
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Filament/Widgets/DuplicatesReportTable.php
  - tests/Feature/WidgetDrillThroughTest.php
autonomous: true
requirements: [ABW-DUP-DRILL]
must_haves:
  truths:
    - "Clicking a duplicates-report row whose voter belongs to the active campaign navigates to that voter's detail view page"
    - "A duplicates-report row whose voter belongs to a different campaign (D-06 cross-campaign sibling exception) remains non-clickable (no recordUrl)"
    - "The set of duplicate document_number groups shown, and their cross-campaign sibling rows, is unchanged — only clickability changed"
  artifacts:
    - path: "app/Filament/Widgets/DuplicatesReportTable.php"
      provides: "conditional recordUrl gated on campaign_id match against the active campaign"
      contains: "recordUrl"
    - path: "tests/Feature/WidgetDrillThroughTest.php"
      provides: "test coverage for both the own-campaign drill-through and the other-campaign no-drill-through case"
      contains: "getRecordUrl"
  key_links:
    - from: "app/Filament/Widgets/DuplicatesReportTable.php"
      to: "VoterResource view page"
      via: "->recordUrl() closure gated on $record->campaign_id === $activeCampaign->id"
      pattern: "getUrl\\('view'"
---

<objective>
Make DuplicatesReportTable rows clickable, but ONLY when the row's voter belongs to the currently active campaign — rows belonging to a different campaign (a disputed cédula's cross-campaign sibling, the D-06 isolation exception) must stay non-clickable.

Purpose: Every other report widget (Jurisdiction, Rejections, ApoyosLideresCoordinadores) already drills through to the voter's Filament view page. DuplicatesReportTable was deliberately left fully static because some of its rows can belong to a campaign other than the active one — routing to those via the campaign-scoped VoterResource would either 404 or, if scope were bypassed, leak cross-campaign data into a navigable detail page. The fix is per-row conditional clickability, not a blanket recordUrl.

Output: `DuplicatesReportTable::table()` gets a conditional `->recordUrl()`; `WidgetDrillThroughTest.php`'s single "no drill-through, by design" test is replaced with two explicit tests (own-campaign row -> real url, other-campaign row -> null). No change to the widget's query, columns, headerActions, or the row-SET campaign filtering (D-06) — only the SIBLINGS-WITHIN-A-GROUP clickability changes.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

# The widget being modified — read its class docblock carefully before editing.
# The docblock's warning is about the QUERY (row set / siblings-within-a-group),
# NOT about recordUrl — do not let it stop you from adding a conditional link.
@app/Filament/Widgets/DuplicatesReportTable.php

# The established recordUrl pattern to copy for the "own campaign" branch:
@app/Filament/Widgets/JurisdictionReportTable.php

# Reference test file — the test to split lives here (~line 242):
@tests/Feature/WidgetDrillThroughTest.php

# Fixture pattern for creating a cross-campaign duplicate pair in tests
# (session-switch-create-switch-back) — copy this exact pattern:
@tests/Feature/Filament/DuplicatesReportTableTest.php

<interfaces>
<!-- Verified facts the executor should rely on directly — no re-exploration needed. -->

VoterResource (App\Filament\Resources\Voters\VoterResource):
  VoterResource::getUrl('view', ['record' => $voter])   // or ['record' => $voter->id]

CampaignContext::currentCampaign(): ?Campaign — already called once at the top of
DuplicatesReportTable::table() and stored in `$activeCampaign`. Reuse that same
variable inside the new recordUrl closure via `use ($activeCampaign)` — do not
call CampaignContext::currentCampaign() a second time.

Existing recordUrl pattern (JurisdictionReportTable / RejectionsReportTable /
ApoyosLideresCoordinadoresTable — all unconditional, chained after ->headerActions([...])):
  ->recordUrl(fn (Voter $record) => VoterResource::getUrl('view', ['record' => $record]));

Cross-campaign duplicate fixture pattern (from DuplicatesReportTableTest.php,
copy this exact session-switch approach — HasCampaignContext forces campaign_id
from the session at creation time, so you must switch context per Voter::create):
  $voterX = Voter::factory()->create([
      'campaign_id' => $this->campaign->id,
      'municipality_id' => $this->municipality->id,
      'document_number' => '5551111111',
      'duplicate_sequence' => 0,
  ]);

  $campaignB = Campaign::factory()->create(['status' => 'active']);
  Session::put('campaign_context.campaign_id', $campaignB->id);
  Session::put('campaign_context.mode', 'single');

  $voterY = Voter::factory()->create([
      'campaign_id' => $campaignB->id,
      'municipality_id' => $this->municipality->id,
      'document_number' => '5551111111',
      'duplicate_sequence' => 1,
      'status' => VoterStatus::DUPLICATE,
  ]);

  Session::put('campaign_context.campaign_id', $this->campaign->id);
  Session::put('campaign_context.mode', 'single');

WidgetDrillThroughTest.php already imports Session, Voter, VoterStatus, VoterResource,
DuplicatesReportTable, and its own beforeEach() sets $this->campaign (active) and
$this->municipality — reuse these, do not redeclare a new beforeEach.
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Conditional recordUrl on DuplicatesReportTable, gated on active-campaign match</name>
  <files>app/Filament/Widgets/DuplicatesReportTable.php, tests/Feature/WidgetDrillThroughTest.php</files>
  <behavior>
    - Voter row whose campaign_id equals the active campaign's id (CampaignContext::currentCampaign()->id) -> getTable()->getRecordUrl($voter) returns VoterResource::getUrl('view', ['record' => $voter->id]).
    - Voter row whose campaign_id belongs to a different campaign (D-06 cross-campaign sibling) -> getTable()->getRecordUrl($voter) returns null.
  </behavior>
  <action>
    First, update tests/Feature/WidgetDrillThroughTest.php: replace the test named
    'duplicates report table rows have no drill-through, by design' (~line 242) with
    two tests, using the cross-campaign duplicate fixture pattern from the
    <interfaces> block above (create $voterX under $this->campaign with
    document_number e.g. '5559999999' and duplicate_sequence 0; switch session to a
    freshly-created $campaignB; create $voterY sharing that document_number with
    duplicate_sequence 1 and status VoterStatus::DUPLICATE under $campaignB; switch
    session back to $this->campaign before instantiating Livewire::test(DuplicatesReportTable::class)):
      1. 'duplicates report table rows link to the voter view page when the record belongs to the active campaign' —
         assert $component->instance()->getTable()->getRecordUrl($voterX) equals
         VoterResource::getUrl('view', ['record' => $voterX->id]).
      2. 'duplicates report table rows have no drill-through when the record belongs to a different campaign (D-06 exception)' —
         assert $component->instance()->getTable()->getRecordUrl($voterY) is null.

    Then implement the widget: in app/Filament/Widgets/DuplicatesReportTable.php,
    add `use App\Filament\Resources\Voters\VoterResource;` to the existing alphabetical
    `use` block. Chain a `->recordUrl(...)` closure onto the returned $table,
    immediately after the existing `->headerActions([...])` call, reusing the
    `$activeCampaign` variable already computed at the top of table() (do NOT call
    CampaignContext::currentCampaign() a second time):

      ->recordUrl(function (Voter $record) use ($activeCampaign): ?string {
          // D-06: sibling rows within a shown duplicate group can belong to a
          // DIFFERENT campaign than the active one — only link when this specific
          // row's own campaign matches, never route into another campaign's data.
          if (! $activeCampaign || $record->campaign_id !== $activeCampaign->id) {
              return null;
          }

          return VoterResource::getUrl('view', ['record' => $record]);
      });

    Do NOT change the widget's ->query(), ->columns(), ->headerActions(), $documentNumbers
    derivation, or the class-level docblock above the class (it correctly describes the
    ROW SET / cross-campaign query exception, which this task does not touch).
  </action>
  <verify>
    <automated>php artisan test --filter=WidgetDrillThroughTest tests/Feature/WidgetDrillThroughTest.php</automated>
  </verify>
  <done>DuplicatesReportTable exposes a conditional recordUrl; the two new/updated Pest cases (own-campaign row -> non-null url to the correct voter view page, other-campaign sibling row -> null url) pass.</done>
</task>

<task type="auto">
  <name>Task 2: Pint + regression check (drill-through suite and D-06 row-set suite both green)</name>
  <files>app/Filament/Widgets/DuplicatesReportTable.php, tests/Feature/WidgetDrillThroughTest.php</files>
  <action>
    Run `vendor/bin/pint --dirty` to format the two files changed in Task 1 (PSR-12,
    alphabetical `use` imports, curly braces per CLAUDE.md). Then run the full
    duplicates-related regression: tests/Feature/Filament/DuplicatesReportTableTest.php
    (proves the row SET / D-06 cross-campaign query exception is still completely
    unmodified — same rows shown as before) together with
    tests/Feature/WidgetDrillThroughTest.php (proves every widget's drill-through,
    including the new conditional one on DuplicatesReportTable, still passes). Confirm
    the diff touches only app/Filament/Widgets/DuplicatesReportTable.php and
    tests/Feature/WidgetDrillThroughTest.php (`git status --short`).
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Filament/DuplicatesReportTableTest.php tests/Feature/WidgetDrillThroughTest.php</automated>
  </verify>
  <done>Pint clean on both files; both test files pass in full with zero failures; `git status --short` shows changes confined to the two files in this plan's files_modified.</done>
</task>

</tasks>

<verification>
- `php artisan test --filter=WidgetDrillThroughTest` — all pre-existing cases (jurisdiction, rejections, apoyos-plana, top-leaders, top-coordinators, territorial, polling places, campaign stats) plus the two new duplicates cases are green.
- `php artisan test tests/Feature/Filament/DuplicatesReportTableTest.php` — the three existing D-06 row-set tests (cross-campaign visibility, unrelated-campaign exclusion, no-siblings exclusion) remain green, proving the query itself was not touched.
- `vendor/bin/pint --dirty` clean.
- Manual browser check (per user's browser-verify-before-prod rule, before any deploy): on the reports panel's "Informe de Duplicados" widget, with an active campaign that has a disputed cédula, confirm the row belonging to the active campaign is clickable and opens the voter view page, and the sibling row from another campaign renders with no link/cursor change.
</verification>

<success_criteria>
- DuplicatesReportTable rows belonging to the active campaign are clickable and open the correct voter's Filament view page.
- DuplicatesReportTable rows belonging to a different campaign (D-06 sibling) remain non-clickable — no recordUrl, no regression in cross-campaign data exposure.
- The widget's row-SET query (which document_number groups are shown, D-06's own campaign-touch filter) is unchanged.
- New Pest cases cover both branches; full drill-through + duplicates test suites pass; Pint clean.
</success_criteria>

<output>
After completion, create `.planning/quick/260812-abw-hacer-clicables-las-filas-del-widget-dup/260812-abw-SUMMARY.md`.
</output>
