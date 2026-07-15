# Changelog

## 0.11.6 (2026-07-14)

- Embedded files now follow drafts created outside the web editor:
  page_service copies a base/restored revision's stored files to the new
  draft's file area, so API-created drafts and restored revisions keep
  their images after publication. PHPUnit coverage for both paths.

## 0.11.5 (2026-07-14)

- Fix empty editor when a published page has no working draft yet: the
  content (and its embedded files) now prefill from the published
  revision, so editing starts from the current text instead of a blank
  field that would have wiped the page on save.

## 0.11.4 (2026-07-14)

- Actually fix "Undefined constant EDITOR_UNLIMITED_FILES": in Moodle 5.2
  the constant is defined in lib/formslib.php (verified against core
  source), not lib/editorlib.php as 0.11.3 assumed. edit.php now requires
  formslib before building the editor options; the editorlib require is
  removed.

## 0.11.3 (2026-07-14)

- Fix "Undefined constant EDITOR_UNLIMITED_FILES" on edit.php: locallib
  now also requires lib/editorlib.php (same load-order class of bug as the
  0.11.1 filelib fix — edit.php uses the constant before formslib loads).
  Superseded by 0.11.4: the constant is not in editorlib on Moodle 5.2.

## 0.11.2 (2026-07-14)

- Fix "El archivo subido no es JSON válido" on seed import: the 0.11.1
  seed file carried a UTF-8 BOM, which json_decode rejects. The seed is
  BOM-free again and the importer now strips a leading BOM from any
  uploaded file.

## 0.11.1 (2026-07-14)

- Fix reader crash "Call to undefined function file_rewrite_pluginfile_urls":
  locallib.php now requires lib/filelib.php explicitly (editor pages loaded
  it indirectly through formslib; view.php/print.php did not).
- Category icons: new icon field (Font Awesome solid class) with upgrade
  step, input in the category form and the importer, validated rendering on
  the home grid with folder fallback; the seed assigns icons to all eleven
  top-level categories.

## 0.11.0 (2026-07-14)

Mockup parity: home personalization, reader polish, path navigation.

- Home (spec §12.1): search hero, pending-required-reading card, reading-
  path progress card with continue button, editorial-work card with live
  counters, and rail cards for safety-critical pages, quick guides and
  forms/templates; pending-count badge on the "Mi ruta" tab (single-query
  validity rule in ack_service::count_pending_for_user).
- Reader (spec §12.2): on-page table of contents with stable heading
  anchors (toc_service, existing ids preserved), print-friendly view
  (print.php with provenance footer and print CSS), localized relation
  labels in BOTH directions (policies now show "Implementada por"),
  automatic quick-guide authority note from the quickguidefor relation,
  print as primary action on quick guides, reading-path membership shown
  on the acknowledgement card.
- Path (spec §15): "Continuar" card jumping to the next pending item and
  current-section highlight.
- TOC/authority-note/is-current/print styles ship in styles.css (mockup
  candidate blocks trimmed); EN/ES/DE strings; toc_service PHPUnit tests.

## 0.10.1 (2026-07-14)

Full handbook preseed.

- docs/seed/initial-handbook.json now scaffolds the complete handbook:
  50 pages covering every §9.1 subcategory — three finished articles plus
  47 writing scaffolds ([BORRADOR] lead + topic-aware sections with bullet
  prompts), realistic metadata (type, authority, criticality, required
  reading, responsible area) per page.
- The "Ser docente en EuropaSchule 2026-2027" path now has all twelve
  §15.2 sections with 41 items (33 required, 8 optional) and 24 typed
  relations including quick-guide and template links.
- Importer: re-imports no longer create new revisions when the seed
  content is identical to the published content (contenthash guard) —
  metadata still updates, so iterating on the seed is churn-free.

## 0.10.0 (2026-07-14)

Path audiences and the MCP adapter.

- Reading-path audiences (spec §15.3, resolves §32.1 for paths): cohorts
  and/or system roles per path via path_service; empty = all staff.
  Enforced in path.php, the "Mi ruta" tab and the path switcher; managers
  see everything. The completion report now covers exactly the audience.
  Importer accepts "cohorts" (idnumbers) and "roles" (shortnames).
- MCP adapter in mcp/ (spec §18, separate deliverable, excluded from the
  plugin ZIP): Node server exposing 14 tools (search/read/changes/
  relations/revisions, draft create/update/submit, findings) over the REST
  API; setup docs for Claude Code, Claude Desktop and remote options.
- EN/ES/DE strings; PHPUnit coverage for audience visibility/encoding.

## 0.9.0 (2026-07-14)

Privacy completion, archive and restore.

- Privacy API: full export (acknowledgements + authored-revision summary)
  and deletion of acknowledgements per user, context or userlist.
  Editorial attribution is retained on deletion as the institution's audit
  record (documented in the provider).
- Archive/unarchive from the reader (publish capability, confirm modal):
  readers stop seeing archived pages, editors keep access, history is
  preserved; page_archived event.
- Restore an older revision from the history page as a new working draft
  (spec 11.3): content copied from the old revision, based on the current
  published one, normal review workflow applies; blocked while a working
  revision exists.
- EN/ES/DE strings; PHPUnit coverage for archive and restore.

## 0.8.0 (2026-07-14)

Notifications and scheduled tasks (spec §21).

- Message providers: draft submitted (to reviewers), changes requested
  (to the author), finding created (to findings managers), review due
  (to the page owner). Sent through the Message API, so each user controls
  delivery channels; sending never blocks the workflow action.
- Weekly review-reminder task: owners of published pages whose review
  date is within 30 days or overdue.
- Daily link-checker task: internal handbook links whose target is
  missing/unpublished and path-item quiz cmids that no longer exist become
  deduped advisory broken_link findings (source "audit", spec §15.4).
- EN/ES/DE strings; PHPUnit coverage for the link checker.

## 0.7.0 (2026-07-14)

Reports (spec §12.5, §15.3).

- manage/reports.php behind local/handbook:viewreports, in the Gestión
  menu, with three views:
  - Editorial health: review date exceeded, no owner, never published,
    oldest drafts in review, open findings count.
  - Path completion: per-staff confirmed/required progress for any path.
  - Page acknowledgements: who confirmed which version and when, and who
    is still pending, per required-reading page.
- report_service with the set-wise acknowledgement-validity rule (the
  re-acknowledgement boundary) shared with ack_service; staff = holders of
  the view capability (path audiences remain a deferred decision).
- EN/ES/DE strings; PHPUnit coverage including boundary invalidation.

## 0.6.1 (2026-07-14)

- Fix upgrade failure "Key pageid collides with index page" on
  local_handbook_findpage: removed the redundant single-column index that
  duplicated the foreign key. The upgrade step is re-runnable; installs
  interrupted by the error resume cleanly.

## 0.6.0 (2026-07-14)

Phase 5 (first slice): quality findings.

- finding/findpage tables with upgrade step (§20.8–20.9).
- finding_service: create + status transitions (open, under_review,
  accepted, dismissed, resolved, intentional_difference, §19.3); findings
  are advisory and never change page content.
- report.php: "Reportar un error" from every reader page creates a
  human-source finding linked to the page and published revision (§12.2).
- manage/findings.php: dashboard with status filters, affected-page links,
  inline status transitions with resolution notes.
- API: local_handbook_list_findings and local_handbook_create_finding
  (agents cite pages/anchors; AI-excluded pages filtered).
- finding_created event, EN/ES/DE strings, PHPUnit coverage.

## 0.5.0 (2026-07-14)

Phase 4: reading paths and required-reading acknowledgements.

- Schema + upgrade step for path, pathitem and ack tables (§20.5–20.7).
- Acknowledgements (§16): confirmation card and status notices in the
  reader, recorded per user and published revision; older confirmations
  stay valid until a version published with "requires renewed
  acknowledgement" (editable flag on drafts) demands reconfirmation.
- Reading paths (§15): path.php with sections, per-item status, progress
  bar and quiz links; manage/paths.php CRUD with item management; "Mi ruta
  de lectura" tab appears once an active path exists.
- Seed importer supports declarative paths; the initial seed now includes
  the "Ser docente en EuropaSchule 2026-2027" induction path.
- page_acknowledged event, ack privacy metadata, EN/ES/DE strings,
  PHPUnit coverage for the acknowledgement lifecycle; path and ack styles
  ship in styles.css (mockup candidate blocks trimmed).
- Deferred (recorded, spec §32): audience assignment of paths (all active
  paths visible to all staff for now) and the completion report.

## 0.4.0 (2026-07-14)

Reader search and revision comparison.

- search.php: dedicated handbook search with content-type and category
  filters, pagination, and a search tab in the area navigation (spec §13.2).
- history.php: full revision history per page (viewhistory/editorial).
- compare.php: metadata comparison plus a word-level text diff of any two
  revisions (spec §11.4), linked from the history, the review queue
  ("View changes") and the reader's editor footer.
- diff_service: dependency-free longest-common-block word diff with
  paragraph fallback for very large texts; PHPUnit coverage.

## 0.3.0 (2026-07-14)

Phase 3: external API (spec §17).

- Eight read functions: list_categories, list_pages, get_page, search_pages,
  list_page_revisions, get_revision, list_changes (incremental sync cursor),
  list_relations.
- Four draft functions: create_page_draft, create_revision_draft (expected
  base-revision check), update_draft (mandatory concurrency token),
  submit_draft_for_review. No publish function by design.
- AI-access enforcement on every function: excluded pages omitted/denied,
  metadata_only pages never return or accept content.
- Prebuilt restricted service "Institutional Handbook API"
  (`local_handbook_api`); draft_updated audit event for UI and API writes.
- docs/API.md with setup steps and curl examples; PHPUnit external tests.

## 0.2.0 (2026-07-14)

Initial-population tooling.

- Bootstrap mode setting: while enabled, publishers get "Save and publish"
  in the editor and imports may publish immediately, skipping review.
  Revision history is recorded either way; switch off after seeding to
  enforce the full workflow (spec §4.10).
- JSON seed importer (`manage/import.php` + `import_service`): creates or
  updates categories, pages and typed relations by slug through the normal
  workflow service, so imports are ordinary revisions. Idempotent re-import.
- Seed file `docs/seed/initial-handbook.json`: full §9.1 category tree,
  three complete articles from the mockup scenario and five skeleton drafts.
- Import link in the management dropdown; EN/ES/DE strings; PHPUnit tests
  for the importer.

## 0.1.0 (2026-07-14)

First development milestone (spec §33): plugin skeleton.

- Capabilities (§7.3), system-context checks, conservative archetype defaults.
- Schema: category, page, revision, relation tables (§20) with unique slug
  and (pageid, versionnumber) indexes.
- Handbook home, category listing, published-page reader with slug URLs.
- Editorial workflow in `page_service`: draft → in_review →
  changes_requested/approved → published/superseded, all transitions in DB
  transactions, optimistic concurrency on draft saves (§11.3).
- Page and category forms, review queue with approve/request-changes/publish.
- Events: page_created, draft_submitted, revision_published.
- Editor file support via Moodle File API (`revision` file area).
- EN/ES/DE language packs, privacy metadata provider, PHPUnit tests for the
  workflow service, build script mirroring local_grades.
