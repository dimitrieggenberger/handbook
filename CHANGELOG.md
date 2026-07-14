# Changelog

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
