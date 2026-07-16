# Handbook AI next-version requirements

## Taxonomy migrations, reading-path drafts, recommendations, and human authorization

**Component:** `local_handbook`  
**Target:** Moodle 5.2.1  
**Repository:** `dimitrieggenberger/handbook`  
**Status:** implementation specification for the next Handbook AI expansion  
**Primary authority rule:** Handbook AI prepares proposals; authorized humans review, approve, and apply them in Moodle.

## 1. Purpose

This document defines the technical work required for Handbook AI to prepare complete taxonomy migrations and reading-path proposals without requiring a human to enter, move, order, or copy data manually.

The intended workflow is:

1. Handbook AI reads the current published handbook, metadata, relations, taxonomy, reading paths, and existing drafts.
2. Handbook AI analyzes the requested outcome and produces a read-only preview.
3. After explicit authorization, Handbook AI creates or updates one coordinated change set.
4. The change set contains every affected category, page placement, reading path, relation, and supported metadata proposal.
5. Handbook AI submits the complete change set only after another explicit instruction.
6. An authorized human reviews the final state in Moodle and authorizes the complete change set.
7. Moodle validates and applies the authorized set atomically.

The MCP server must not expose approval, application, publication, permission-management, or direct database-write functions.

## 2. Governing principles

### 2.1 Published content remains authoritative

Every proposal begins from the current published page, category tree, reading path, and typed relations. Drafts do not silently replace published state.

### 2.2 One coordinated change set

Changes involving several pages, categories, relations, reading paths, or metadata fields belong in one coordinated change set. The reviewer must be able to understand the complete proposed final state before authorization.

### 2.3 Optimistic concurrency

Every proposal carries the applicable published revision, modification time, category-tree version, path version, or other concurrency token. A mismatch creates a structured conflict.

### 2.4 Atomic application

Moodle validates the complete set before application. If one item fails, the transaction rolls back and no part of the change set becomes published.

### 2.5 Article-level completion

Reading completion belongs to a user and a published article revision. It is shared across every reading path containing that article. Reading the same published revision once satisfies it everywhere, subject to renewed-acknowledgement rules.

### 2.6 Recommendations remain advisory

The system may recommend adding, removing, reordering, or replacing reading-path items. Recommendations never alter an active path automatically.

## 3. Current implementation baseline

The current plugin already provides:

- published pages and immutable published revisions;
- working revisions and optimistic concurrency for page content;
- polymorphic change-set items;
- metadata, relation, category, archive, restore, and new-page proposals;
- reading paths with name, slug, description, school year, audience, active status, sections, ordering, required flags, and optional quiz links;
- audience visibility through cohorts and system roles;
- article acknowledgements keyed uniquely by user and published revision;
- path progress calculated from article acknowledgement status;
- human-controlled approval and publication.

The acknowledgement key `userid + revisionid` already allows one confirmation to count across several paths. This behavior must be preserved.

Current limitations include:

- no individual page-move proposal;
- no empty-category dissolution proposal;
- no complete change-set preview or final-state simulation;
- no atomic whole-change-set application;
- no reading-path read or proposal functions in the external API or MCP server;
- reading paths are edited directly in Moodle;
- path progress counts only items whose page is also globally marked `requiredreading`;
- no structured reading-path recommendation workflow;
- no individual, self-enrolled, or situational path assignment;
- no path draft, path revision, or path comparison model.

## 4. Taxonomy migration requirements

### 4.1 Individual page moves

Add a change-item kind:

```text
page_move
```

Add an external function:

```text
local_handbook_upsert_changeset_page_move
```

Add an MCP tool:

```text
handbook_upsert_change_set_page_move
```

Required parameters:

```text
changesetid
identifier
targetcategoryid OR targetcategorytempkey
expectedcategoryid
expectedpagetimemodified
changesummary (optional)
```

The proposed payload should retain:

```json
{
  "sourcecategoryid": 53,
  "targetcategoryid": 20,
  "targetcategorytempkey": "",
  "expectedpagetimemodified": 1784078728
}
```

Application updates:

- `local_handbook_page.categoryid`;
- `timemodified`;
- `modifiedby`.

Moving a page preserves its ID, slug, revisions, acknowledgements, files, and typed relations. The plugin records a page-moved event and the before-and-after category paths.

The proposal tool uses `local/handbook:apiproposetaxonomy`. Application remains human-gated.

### 4.2 Empty-category dissolution

Add a category operation:

```text
delete_empty
```

The operation applies only when:

- the category contains no pages;
- the category contains no subcategories;
- no pending change-set item depends on it;
- it is not the target of another proposed operation;
- its concurrency token matches;
- no governed entity still requires the category.

The review interface labels this operation as **Dissolve empty category** or **Delete empty category**. It should not be presented as a merge.

### 4.3 Temporary references for proposed categories

Add a general temporary-reference table:

```text
local_handbook_tempref
- id
- changesetid
- tempkey
- entitytype
- entityid
- timecreated
```

Required uniqueness:

```text
UNIQUE (changesetid, tempkey)
```

Category proposals accept:

- `parentid` or `parenttempkey`;
- `newparentid` or `newparenttempkey`;
- `targetid` or `targettempkey`.

Page moves and new-page proposals accept:

- `targetcategoryid` or `targetcategorytempkey`.

When a proposed category is created, the service records its real ID against the temporary key before dependent items are applied.

### 4.4 Category slug updates and aliases

The current MCP schema accepts a category slug during update, but the server-side validator and apply method ignore it. Implement the change fully or remove the field until supported.

Recommended alias table:

```text
local_handbook_categoryalias
- id
- categoryid
- oldslug
- timecreated
- createdby
```

A category slug change must:

1. validate uniqueness;
2. preserve the category ID;
3. store the old slug as an alias;
4. keep old links resolvable;
5. show the impact to the reviewer.

### 4.5 Taxonomy concurrency

Category update, move, merge, and dissolution proposals carry:

```text
expectedtimemodified
```

Page moves carry:

```text
expectedcategoryid
expectedpagetimemodified
```

Merges additionally carry expected source and target state:

```text
expectedsourceparentid
expectedtargetparentid
expectedsourcepagecount
expectedsourcechildcount
```

A tree-level version is recommended:

```text
categorytreeversion
expectedcategorytreeversion
```

The tree version increments when a category is created, updated, moved, merged, dissolved, archived, or restored.

### 4.6 Category-merge auditing

Current merges modify page category IDs through bulk field updates. The improved implementation must update affected page timestamps and attribution and record every affected page in the change-set impact and audit trail.

Explicit `page_move` items are preferred for planned migrations because they show the destination of every page.

### 4.7 Declarative taxonomy plans

Add read-only and proposal tools:

```text
handbook_preview_taxonomy_plan
handbook_upsert_taxonomy_plan
```

A taxonomy plan describes the desired final state:

```json
{
  "categories": [
    {
      "id": 1,
      "tempkey": "",
      "name": "Identidad institucional",
      "slug": "identidad-institucional",
      "parentid": 0,
      "description": "Presenta el modelo, las fortalezas y las prioridades que definen la identidad y orientación estratégica de EuropaSchule.",
      "sortorder": 10
    }
  ],
  "pageassignments": [
    {
      "identifier": "modelo-educativo",
      "targetcategoryid": 1
    }
  ],
  "retirecategories": [
    {
      "categoryid": 53,
      "operation": "delete_empty"
    }
  ]
}
```

The service translates the plan into individual auditable change items. It should reuse suitable existing category IDs where doing so reduces unnecessary movement and preserves stable links.

## 5. Complete change-set validation and application

### 5.1 Read-only validation tools

Add:

```text
local_handbook_validate_changeset
local_handbook_preview_changeset
local_handbook_get_changeset_impact
```

MCP equivalents:

```text
handbook_validate_change_set
handbook_preview_change_set
handbook_get_change_set_impact
```

For taxonomy changes, the preview returns:

- current and proposed trees;
- final category count;
- page count per proposed category;
- every page's current and proposed path;
- categories created, renamed, moved, merged, or dissolved;
- orphaned pages;
- empty categories;
- cycles;
- hidden or required-reading impact;
- stale concurrency tokens;
- affected category links and aliases;
- errors and warnings.

For reading paths, the preview returns the information defined in section 9.

### 5.2 Change-item dependencies

Add a dependency model:

```text
local_handbook_changeitem_dependency
- itemid
- dependsonitemid
```

The service topologically sorts items and rejects circular dependencies.

Typical order:

1. category creation and retained-category updates;
2. temporary-ID resolution;
3. page moves;
4. relations and metadata;
5. reading-path creation or updates;
6. empty-category dissolution and lifecycle changes.

### 5.3 Atomic whole-change-set application

Add human-gated service methods:

```text
changeset_service::validate_all()
changeset_service::approve_all()
changeset_service::publish_all()
```

`publish_all()` must:

1. lock the change set;
2. validate every item against current state;
3. resolve dependencies and temporary references;
4. simulate the final taxonomy, paths, and entity state;
5. begin one database transaction;
6. apply all items in dependency order;
7. update audit records and statuses;
8. commit only after every item succeeds.

Any failure rolls back the complete set.

There must be no external API or MCP route to these methods.

### 5.4 Human authorization interface

For a person holding both approval and publication capabilities, provide:

```text
Approve and apply entire change set
```

Where responsibilities are separated, retain:

```text
Approve entire change set
Apply approved change set
```

Per-item return, rejection, approval, and application may remain available for exceptional cases.

## 6. Reading-path product model

### 6.1 Purpose

A reading path is a focused, ordered learning sequence for a defined purpose, moment, role, or situation. It should help staff find the relevant institutional knowledge without requiring everyone to complete one excessively long path.

Examples include:

- **Antes de iniciar el año lectivo:** essential institutional reading before teaching begins;
- **Semana diagnóstica:** procedures and expectations relevant during diagnostic week;
- **Tengo un estudiante con necesidades educativas especiales:** guidance for a teacher supporting a student who needs individualized measures;
- **Soy docente guía:** responsibilities, family communication, attendance, group follow-up, and escalation;
- **Supervisión de recreos:** the canonical procedure, quick guide, incident reporting, and supporting form;
- **Preparar y conducir reuniones con familias:** communication standards, meeting procedure, quick guide, and template;
- **Evaluación y cierre de parcial:** evaluation, grading, publication, syllabi, and Moodle expectations;
- **Modalidad homeschool e híbrida:** articles needed by teachers working with these modalities.

### 6.2 Path size and focus

Reading paths should remain practical and focused.

Recommended configurable warnings:

- warn when a path exceeds 10 required articles;
- warn when estimated reading time exceeds 45 minutes;
- recommend dividing paths that combine several distinct purposes;
- allow optional supporting articles without counting them toward mandatory completion;
- allow a main path to declare prerequisite or follow-up paths.

These values should be configurable rather than hard-coded.

### 6.3 Path types

Add a controlled `pathtype` vocabulary:

```text
onboarding
calendar_phase
role_based
situational
refresher
compliance
```

Examples:

- beginning-of-year reading: `onboarding` or `calendar_phase`;
- diagnostic week: `calendar_phase`;
- special educational needs: `situational`;
- docente guía: `role_based`;
- emergency procedures: `compliance` or `refresher`.

### 6.4 Visibility and assignment are separate

Audience visibility determines who may see a path. Assignment determines who is expected to complete it.

Keep existing cohort and system-role visibility and add assignment modes:

```text
available
self_enrol
manager_assigned
automatic_role
automatic_cohort
```

Recommended assignment table:

```text
local_handbook_pathassign
- id
- pathid
- userid
- source
- required
- datestart
- duedate
- timeassigned
- assignedby
- timestarted
- timecompleted
```

Group assignments may be materialized to users or stored through a separate assignment rule table.

The situational special-needs path can be available to all teachers, self-enrolled by a teacher, or assigned by Consejería Estudiantil or Coordinación Académica.

### 6.5 Path metadata

A proposed path should support:

```text
name
slug
description
pathtype
purpose
schoolyear
audiencejson
assignmentmode
active
datestart
dateend
estimatedminutes
quizcmid
prerequisitepathids
tags
owneruserid
reviewdate
```

Owner assignment remains human-controlled unless the identity comes from an authorized lookup.

### 6.6 Sections and items

A path revision contains ordered sections. Each item supports:

```text
pageid OR pagetempkey
sectionname
sortorder
required
estimatedminutes
quizcmid
rationale
learningoutcome
```

The rationale explains why the article belongs in the path. It is visible during review and may optionally be shown to readers.

Typed relations remain the principal explanation of canonical relationships. For example, a quick guide and template remain beside their canonical procedure and retain `quickguidefor` or `templatefor`.

## 7. Reading-path drafts and change sets

### 7.1 Change-item kinds

Add:

```text
reading_path_create
reading_path_revision
reading_path_deactivate
reading_path_restore
```

`reading_path_revision` should contain the complete proposed path snapshot. A complete snapshot makes ordering, additions, removals, section changes, required flags, and metadata reviewable as one coherent proposal.

### 7.2 Path revisions

Recommended schema:

```text
local_handbook_pathrevision
- id
- pathid
- versionnumber
- status
- baserevisionid
- proposeddatajson
- changesummary
- contenthash
- timecreated
- timemodified
- createdby
- modifiedby
- reviewedby
- approvedby
- publishedby
- timeapproved
- timepublished
```

Add to `local_handbook_path`:

```text
publishedrevisionid
workingrevisionid, optional if not derived
```

Alternatively, the first implementation may store the complete proposed snapshot in a polymorphic change-set item. A dedicated path-revision model is preferred because it provides history, comparison, restoration, and annual evidence.

### 7.3 Path proposal payload

Example:

```json
{
  "tempkey": "newpath:semana-diagnostica-2026-2027",
  "name": "Semana diagnóstica 2026-2027",
  "slug": "semana-diagnostica-2026-2027",
  "description": "Lecturas necesarias para planificar, ejecutar y documentar la semana diagnóstica.",
  "pathtype": "calendar_phase",
  "schoolyear": "2026-2027",
  "audiencejson": {
    "roles": [5],
    "cohorts": []
  },
  "assignmentmode": "automatic_role",
  "active": true,
  "sections": [
    {
      "name": "Planificación y evidencias",
      "sortorder": 10,
      "items": [
        {
          "pageid": 13,
          "required": true,
          "sortorder": 10,
          "rationale": "Orienta la planificación de actividades y evidencias diagnósticas."
        }
      ]
    }
  ]
}
```

### 7.4 Concurrency

Path updates carry:

```text
expectedpublishedrevisionid
expectedtimemodified
expectedcontenthash
```

If the path has been edited or published since it was read, the proposal returns a conflict.

### 7.5 Path lifecycle

Annual or obsolete paths should be deactivated or archived through a proposal. Historical paths retain:

- their published composition;
- school year;
- assignments;
- completion evidence;
- quizzes and links;
- audit history.

Deletion should be reserved for empty, unpublished mistakes.

## 8. Shared article completion across paths

### 8.1 Required behavior

When the same published article revision appears in several paths:

1. the user reads and confirms it once;
2. every path recognizes the same confirmation;
3. the article appears as **Already read** or **Completed** in later paths;
4. each path progress total includes the completed item;
5. a materially changed revision may require one renewed confirmation, which then satisfies every path containing the new revision.

### 8.2 Preserve the canonical acknowledgement key

Keep:

```text
UNIQUE (userid, revisionid)
```

Path progress must determine completion by user and applicable published revision, independently of the path where the acknowledgement was first made.

### 8.3 Fix path-required versus globally required reading

The current progress service counts an item only when both conditions are true:

```text
pathitem.required = 1
page.requiredreading = 1
```

This prevents a path from requiring an article that is not globally mandatory.

The corrected meaning should be:

- `page.requiredreading`: globally or institutionally required reading for its applicable audience;
- `pathitem.required`: required for completion of this specific path;
- optional path items may still be marked as read but do not block path completion.

Path progress should count every published, non-archived item with `pathitem.required = 1`, regardless of the page's global `requiredreading` value.

### 8.4 Completion record options

Preferred long-term model:

```text
local_handbook_readreceipt
- id
- userid
- pageid
- revisionid
- timecompleted
- completionmethod
- confirmationversion
```

Keep compliance acknowledgement separately when a page is globally required:

```text
local_handbook_ack
```

This distinguishes ordinary reading-path completion from formal required-reading acknowledgement.

Minimum viable alternative:

- allow `ack_service::acknowledge()` for a required path item even when the page is not globally required;
- update `get_status()` and path progress accordingly;
- retain the unique user-and-revision key.

The separate read-receipt model is clearer and recommended.

### 8.5 Path context history

The current acknowledgement stores one `pathid`, usually the path where the user first confirmed the article. Because one acknowledgement serves several paths, a single path field cannot represent every context.

If path-level attribution is needed, add:

```text
local_handbook_readcontext
- id
- receiptid
- pathid
- timefirstseen
- timecompleted
```

Completion itself remains article-level.

### 8.6 Reader interface

Each item should show one of:

```text
Not started
In progress
Completed in this path
Already completed in another path
Updated article: confirmation required again
Optional
```

The path header should show:

- required items completed;
- required items remaining;
- optional items read;
- estimated remaining reading time;
- next recommended article.

## 9. Reading-path preview and human review

The Moodle review interface should show:

### 9.1 Metadata

- current and proposed name;
- description and purpose;
- path type;
- audience and assignment mode;
- school year and dates;
- owner and review date;
- activation or deactivation.

### 9.2 Composition

- current and proposed sections;
- pages added and removed;
- pages reordered;
- required and optional status changes;
- canonical relations among included pages;
- archived or unpublished pages;
- duplicate pages;
- unresolved temporary page references.

### 9.3 Size and overlap

- required article count;
- optional article count;
- estimated reading time;
- configurable length warnings;
- overlap with other paths;
- percentage of the target audience that has already completed each article, shown only to authorized managers and only as appropriate aggregated data;
- prerequisite and follow-up paths.

### 9.4 Final authorization

The path proposal is included in the same approve-and-apply change-set action as related content, metadata, relations, and taxonomy changes.

## 10. Reading-path recommendations

### 10.1 Purpose

When the handbook gains or substantially changes an article, the system should recommend relevant reading paths. A recommendation remains pending until reviewed.

Examples:

- a new diagnostic assessment procedure may be recommended for the diagnostic-week path;
- a new accommodation procedure may be recommended for the special-needs path;
- a revised family-communication template may be recommended for the family-meeting path;
- a new quick guide should be recommended beside the canonical procedure already present in a path.

### 10.2 Recommendation inputs

Recommendations should consider:

- page title and summary;
- full authorized content when needed;
- category and category path;
- document type;
- criticality and required-reading status;
- responsible area;
- modalities, roles, levels, cycles, or grades when available;
- typed relations;
- canonical article relationships;
- terms and aliases from the institutional glossary when implemented;
- existing path purpose, audience, type, sections, and tags;
- similarity to existing path items;
- publication and review dates.

Typed relations should receive strong weight. A new `quickguidefor` or `templatefor` page is a strong candidate for paths containing its canonical target.

### 10.3 Recommendation record

Recommended schema:

```text
local_handbook_pathrecommendation
- id
- pathid
- pageid
- revisionid
- recommendationtype
- confidence
- rationale
- suggestedsection
- suggestedrequired
- suggestedafterpageid
- status
- trigger
- timecreated
- timemodified
- reviewedby
- reviewnote
```

Statuses:

```text
open
accepted
dismissed
deferred
already_covered
intentional_omission
resolved
```

Recommendation types:

```text
add
remove
reorder
replace
split_path
merge_paths
update_required_status
```

### 10.4 Recommendation triggers

Run or offer analysis when:

- a new page is published;
- a page receives a materially changed revision;
- page metadata, category, scope, criticality, or relations change;
- a new reading path is proposed;
- an existing path reaches its review date;
- an administrator requests a handbook-wide path audit.

The scheduled task may create advisory candidates from deterministic metadata and relations. Semantic or editorial recommendations may be generated through Handbook AI on demand.

### 10.5 Recommendation actions

Accepting a recommendation should prepare a reading-path revision inside a change set. It should not directly modify the active path.

Dismissed recommendations should remain suppressed until the page or path changes materially.

## 11. External API and MCP tools for reading paths

### 11.1 Read tools

```text
handbook_list_reading_paths
handbook_get_reading_path
handbook_get_reading_path_revision
handbook_list_reading_path_revisions
handbook_get_reading_path_coverage
handbook_preview_reading_path
handbook_validate_reading_path
handbook_list_path_recommendations
handbook_get_path_recommendation
handbook_audit_reading_paths
```

`handbook_get_reading_path_coverage` should describe handbook coverage and overlap without returning personally identifiable completion data to the ordinary AI service account.

### 11.2 Proposal tools

```text
handbook_upsert_change_set_reading_path
handbook_upsert_change_set_path_deactivation
handbook_upsert_change_set_path_restore
handbook_accept_path_recommendations_into_change_set
```

The upsert tool creates or updates one complete path snapshot within the change set. Repeated calls reuse the same editable proposal and require `expectedtimemodified`.

### 11.3 Advisory tools

```text
handbook_recommend_paths_for_page
handbook_recommend_pages_for_path
handbook_recommend_path_split
```

These functions are read-only or create advisory recommendation records only after explicit authorization. They do not edit an active path.

### 11.4 Privacy boundary

The ordinary Handbook AI account may read:

- path metadata;
- path composition;
- aggregate coverage;
- recommendation records.

It should not receive individual staff completion records unless a separate, explicitly authorized reporting role and use case are implemented.

## 12. Controlled responsible-area vocabulary

Add a proposal kind:

```text
responsible_area_change
```

Supported operations:

```text
create
update
deactivate
replace
merge
```

Replacement should support coordinated metadata patches for affected pages.

Pages should eventually store the stable responsible-area key rather than the display name:

```text
responsibleareakey: coordinacion-academica
displayname: Coordinación Académica
```

This is necessary for cleaning displaced or misspelled entries without requiring direct manual changes.

## 13. Bulk proposal helpers

Add:

```text
handbook_bulk_upsert_page_moves
handbook_bulk_upsert_category_operations
handbook_bulk_upsert_reading_path_items
```

Bulk calls create individual auditable items and return a result for every operation:

```text
created
updated
conflict
skipped
error
```

One error must not hide the result of other proposed items. Whole-set application remains atomic after human authorization.

## 14. Recommended implementation phases

### Phase 1: complete taxonomy proposals

- `page_move` change items;
- `delete_empty` category operation;
- category temporary-reference resolution;
- category slug fix and aliases;
- taxonomy concurrency;
- final-tree preview;
- migration audit improvements.

### Phase 2: atomic change-set authorization

- dependency model;
- complete validation;
- whole-set approval interface;
- atomic whole-set application;
- rollback tests.

### Phase 3: reading-path read and proposal surface

- path list and detail API;
- path draft or revision model;
- complete path snapshot proposals;
- path diff and preview UI;
- human-gated application;
- archive and restore path lifecycle.

### Phase 4: shared reading completion improvements

- path-required items independent of global required reading;
- read-receipt model;
- shared completion across paths;
- optional item tracking;
- cross-path status display;
- assignment and due-date model.

### Phase 5: recommendations and audits

- path purpose and type metadata;
- recommendation records;
- publication and review-date triggers;
- coverage and overlap audits;
- path-size and split recommendations;
- accepting recommendations into change-set drafts.

### Phase 6: controlled vocabulary and bulk ergonomics

- responsible-area proposals;
- bulk staging tools;
- declarative taxonomy plans;
- declarative path plans;
- deployment and connector documentation.

## 15. Required tests

### 15.1 Authority boundary

- no external or MCP tool can approve, apply, or publish;
- proposal capabilities grant drafting only;
- human capability checks precede whole-set authorization;
- direct database writes remain outside the MCP layer.

### 15.2 Taxonomy

- move one page between existing categories;
- move a page into a category proposed in the same set;
- create a subcategory beneath a proposed parent;
- reject a stale page category;
- reject a stale category;
- prevent combined-operation cycles;
- dissolve an empty category;
- reject dissolution of a non-empty category;
- preserve page relations, acknowledgements, revisions, and links;
- preserve old category slugs through aliases;
- update affected page timestamps and attribution;
- roll back the complete migration after a mid-apply failure.

### 15.3 Reading-path drafts

- create a path proposal without activating it directly;
- update an existing path from its expected published revision;
- add, remove, reorder, and regroup pages;
- add a page proposed in the same change set;
- reject duplicate path items;
- reject archived or excluded pages when inappropriate;
- preserve an earlier school-year path;
- deactivate and restore through human review;
- show a complete before-and-after comparison.

### 15.4 Completion

- completing an article in one path marks it complete in another path;
- path-required articles count even when the page is not globally required;
- optional items do not block completion;
- a materially changed revision can require renewed confirmation;
- one renewed confirmation satisfies every path containing the new revision;
- archived pages leave active path totals;
- historical completion evidence remains intact.

### 15.5 Recommendations

- recommend a quick guide for a path containing its canonical procedure;
- recommend a template for a path containing its `templatefor` target;
- suppress an intentionally dismissed recommendation;
- reconsider after a material page or path change;
- create a path draft from accepted recommendations;
- prevent automatic active-path mutation;
- avoid exposing individual completion data through the ordinary AI account.

## 16. Acceptance criteria

The expansion is complete when:

1. Handbook AI can read every authorized active and draft reading path.
2. Handbook AI can preview a complete taxonomy or reading-path plan without writes.
3. After explicit authorization, Handbook AI can create every required proposal in one change set.
4. No human must manually move pages, create categories, order path items, or copy recommendations into a path.
5. The Moodle reviewer can see the final taxonomy and complete reading-path diffs before authorization.
6. One authorized human action can apply the complete approved set atomically.
7. Completion of one article revision carries across every reading path containing it.
8. Path-required reading works independently of the page's global required-reading flag.
9. New or changed articles generate reviewable path recommendations.
10. Historical paths, acknowledgements, revisions, and audit records remain preserved.
11. Handbook AI still has no approval, application, or publication capability.

## 17. Deployment requirements

After implementation:

1. bump `version.php`;
2. add `install.xml` and `upgrade.php` schema changes;
3. add EN, ES, and DE language strings;
4. add PHPUnit coverage and authority-boundary guards;
5. update `docs/API.md`, `docs/SPECIFICATION.md`, `AGENTS.md`, and `mcp/DEPLOY.md`;
6. grant any new propose-only capabilities to the `handbook-ai` service-account role;
7. deploy the Moodle plugin upgrade;
8. deploy and restart the MCP service;
9. refresh the ChatGPT connector actions;
10. begin with read-only validation of the live tools before creating any proposal.
