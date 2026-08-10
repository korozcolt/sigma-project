---
phase: quick-260810-jma
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php
  - app/Filament/Resources/Coordinators/CoordinatorResource.php
  - app/Filament/Resources/Leaders/LeaderResource.php
  - app/Filament/Resources/Voters/VoterResource.php
  - app/Filament/Resources/Invitations/InvitationResource.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Admin sidebar 'Gestión' group lists Campañas, then Articuladores, then Coordinadores, then Líderes, then Votantes, then Invitaciones, in that order"
  artifacts:
    - path: "app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php"
      provides: "navigationSort = 2"
    - path: "app/Filament/Resources/Coordinators/CoordinatorResource.php"
      provides: "navigationSort = 3"
    - path: "app/Filament/Resources/Leaders/LeaderResource.php"
      provides: "navigationSort = 4"
    - path: "app/Filament/Resources/Voters/VoterResource.php"
      provides: "navigationSort = 5"
    - path: "app/Filament/Resources/Invitations/InvitationResource.php"
      provides: "navigationSort = 6"
  key_links: []
---

<objective>
Reorder the "Gestión" Filament admin navigation group so that Articuladores (`AreaCoordinatorResource`) appears directly below Campañas and above Coordinadores.

Purpose: Purely visual/navigation ordering fix requested by the user — Articuladores sits above Coordinadores in the org hierarchy and the menu should reflect that.
Output: Updated `navigationSort` values on 5 of the 6 "Gestión" group resources (Campañas stays at 1).
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@CLAUDE.md

Current `navigationSort` values (all in the "Gestión" Filament navigation group):

| Resource | File | Current Sort | New Sort |
|---|---|---|---|
| CampaignResource | app/Filament/Resources/Campaigns/CampaignResource.php | 1 | 1 (unchanged) |
| AreaCoordinatorResource | app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php | 6 | 2 |
| CoordinatorResource | app/Filament/Resources/Coordinators/CoordinatorResource.php | 2 | 3 |
| LeaderResource | app/Filament/Resources/Leaders/LeaderResource.php | 3 | 4 |
| VoterResource | app/Filament/Resources/Voters/VoterResource.php | 4 | 5 |
| InvitationResource | app/Filament/Resources/Invitations/InvitationResource.php | 5 | 6 |

Each resource declares the property as:
```php
protected static ?int $navigationSort = N;
```
</context>

<tasks>

<task type="auto">
  <name>Task 1: Renumber navigationSort on all 5 affected Gestión resources</name>
  <files>app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php, app/Filament/Resources/Coordinators/CoordinatorResource.php, app/Filament/Resources/Leaders/LeaderResource.php, app/Filament/Resources/Voters/VoterResource.php, app/Filament/Resources/Invitations/InvitationResource.php</name>
  <action>
    Update the `protected static ?int $navigationSort = N;` line in each of the 5 files to its new value:
    - `AreaCoordinatorResource.php`: 6 -> 2
    - `CoordinatorResource.php`: 2 -> 3
    - `LeaderResource.php`: 3 -> 4
    - `VoterResource.php`: 4 -> 5
    - `InvitationResource.php`: 5 -> 6

    `CampaignResource.php` is NOT touched (already 1, correct position). No other property, method, or line in any of these files should change. This is a single-line numeric edit per file.
  </action>
  <verify>
    <automated>grep -n "navigationSort" app/Filament/Resources/Campaigns/CampaignResource.php app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php app/Filament/Resources/Coordinators/CoordinatorResource.php app/Filament/Resources/Leaders/LeaderResource.php app/Filament/Resources/Voters/VoterResource.php app/Filament/Resources/Invitations/InvitationResource.php</automated>
  </verify>
  <done>All 6 files show navigationSort values 1, 2, 3, 4, 5, 6 respectively for Campaign, AreaCoordinator, Coordinator, Leader, Voter, Invitation.</done>
</task>

<task type="auto">
  <name>Task 2: Lint and confirm no other behavior changed</name>
  <files>app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php, app/Filament/Resources/Coordinators/CoordinatorResource.php, app/Filament/Resources/Leaders/LeaderResource.php, app/Filament/Resources/Voters/VoterResource.php, app/Filament/Resources/Invitations/InvitationResource.php</name>
  <action>
    Run `vendor/bin/pint --dirty` to format the 5 touched files per project convention. Then run `git diff` on the 5 files and confirm each diff is exactly a single-line numeric change to `$navigationSort` (no other lines touched, no accidental whitespace/formatting changes to unrelated code).
  </action>
  <verify>
    <automated>vendor/bin/pint --dirty; git diff --stat -- app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php app/Filament/Resources/Coordinators/CoordinatorResource.php app/Filament/Resources/Leaders/LeaderResource.php app/Filament/Resources/Voters/VoterResource.php app/Filament/Resources/Invitations/InvitationResource.php</automated>
  </verify>
  <done>Pint reports no style issues in the 5 files; each file's diff is a single-line numeric change to $navigationSort with no unrelated modifications.</done>
</task>

</tasks>

<verification>
1. `grep -n "navigationSort" app/Filament/Resources/{Campaigns/CampaignResource,AreaCoordinators/AreaCoordinatorResource,Coordinators/CoordinatorResource,Leaders/LeaderResource,Voters/VoterResource,Invitations/InvitationResource}.php` shows values 1, 2, 3, 4, 5, 6 in that resource order.
2. `git diff --stat` on the 5 modified files shows only the expected files touched, no unrelated changes.
3. (Manual, optional) Load the admin panel in a browser and confirm the "Gestión" sidebar group renders: Campañas, Articuladores, Coordinadores, Líderes, Votantes, Invitaciones — per standing project preference, browser-verify UI changes before considering fully done, though this is a low-risk cosmetic change.
</verification>

<success_criteria>
- All 6 "Gestión" group resources have navigationSort values that produce the order: Campañas (1), Articuladores (2), Coordinadores (3), Líderes (4), Votantes (5), Invitaciones (6).
- No functional/business logic changed — only the navigationSort integer property on 5 files.
- Pint passes clean on all touched files.
</success_criteria>

<output>
After completion, create `.planning/quick/260810-jma-a-nivel-visual-en-menu-los-articuladores/260810-jma-SUMMARY.md`
</output>
