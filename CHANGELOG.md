# Changelog

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
