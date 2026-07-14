# AGENTS.md

## Purpose

This repository is the source of truth for the Moodle plugin `local_handbook`
(EuropaSchule "Manual Institucional"). The full product specification lives in
`docs/SPECIFICATION.md`; static UI mockups live in `dev/` (see `dev/README.md`).

- Moodle target: Moodle 5.2.1
- Plugin type: `local`
- Component: `local_handbook`
- Moodle install path: `local/handbook`
- GitHub repo: `dimitrieggenberger/handbook`
- Installable ZIPs are build artifacts only, created by `build-zip.ps1`

This plugin follows the shared sibling-plugin rulebook from the `local_grades`
repo (`docs/shared-plugin-instructions.md` there). `local_grades` is the visual
and structural reference: page shell, area actions, CSS scoping, build script.

## Main Files

- `locallib.php`: page setup, breadcrumbs, heading, area actions, reader/list render helpers
- `lib.php`: Moodle callbacks only (pluginfile serving for revision attachments)
- `index.php`: handbook home (categories + recent updates)
- `category.php`: category listing
- `view.php`: published-page reader (`?page=<slug-or-id>`)
- `edit.php`: create page / edit its working draft, submit for review
- `search.php`: dedicated handbook search with filters (spec §13.2)
- `path.php` / `manage/paths.php`: reading paths (spec §15); `ack_service` + confirmation card in `view.php` (spec §16)
- `history.php` / `compare.php`: revision history and word-diff comparison (spec §11.4, `diff_service`)
- `review.php`: review queue (approve, request changes, publish)
- `manage/categories.php`: category management
- `manage/import.php` + `classes/local/service/import_service.php`: JSON seed import (by slug, idempotent)
- `settings.php`: bootstrap mode (direct publish during initial population; off = full workflow)
- `docs/seed/initial-handbook.json`: seed content (never shipped; uploaded via the import page)
- `classes/local/service/page_service.php`: ALL workflow state transitions (spec §11.3)
- `classes/form/`: moodleforms
- `classes/event/`: page_created, draft_submitted, revision_published
- `classes/external/` + `db/services.php`: external API (spec §17; see docs/API.md) — read + draft functions only, NO publish function, AI-access rules in `classes/external/helper.php`
- `db/access.php`: capabilities (spec §7.3); `db/install.xml`: schema (spec §20)
- `lang/{en,es,de}/local_handbook.php`: UI strings (all three, always)
- `styles.css`: plugin CSS, scoped to `#page-local-handbook-area`

## Architecture Rules

- `local_handbook_page.publishedrevisionid` is the single source of truth for
  publication. Never derive publication state from revision status alone.
- Workflow transitions happen only in `page_service`, inside DB transactions.
- Published revisions are immutable historical records.
- Draft writes carry an optimistic concurrency check (revision timemodified).
- Capabilities are checked in system context.
- Stored page content starts at `h2` (spec §10.2); the reader demotes headings
  one level (`local_handbook_demote_headings`).

## Boundaries

This plugin owns: handbook categories/pages/revisions, editorial workflow,
reader views, and (later phases) reading paths, acknowledgements, findings,
and the external API.

It must not own: Moodle core content storage, quiz/question logic (reuse
Moodle Quiz, spec §15.4), theme-level layout outside plugin-scoped CSS, global
navigation outside approved links (the child theme places the sidebar item),
or anything `local_grades` already owns.

## Security Rules

- Require login on every page; enforce capabilities inside the plugin.
- `required_param()` / `optional_param()` with proper `PARAM_*` types.
- sesskey checks for state-changing actions.
- Recheck access before serving files (see `local_handbook_pluginfile`).
- Restricted-audience content must not leak through search or API (spec §13.1, §17.4).
- Stop and report before committing if secrets or tokens appear.

## UI Rules

Follow the styling hierarchy: Moodle core output → Bootstrap/theme classes →
local_grades-approved patterns (`/local/grades/dev/uireference.php`) → new
`local-handbook-*` CSS only when necessary, always scoped to
`#page-local-handbook-area`. Keep strings in EN, ES and DE. Design new screens
as `dev/<name>-mockup/index.html` mockups first (see `dev/README.md`).

## Build and Test

```powershell
php -l path\to\file.php                      # single file
powershell -ExecutionPolicy Bypass -File .\build-zip.ps1   # dist/local_handbook-<version>.zip
[xml](Get-Content -Raw db\install.xml) | Out-Null          # XML validity
```

ZIP must contain exactly one root folder `handbook/` with `version.php`;
`docs/`, `dev/`, `build/`, `dist/` are never shipped. Always bump
`version.php` before committing plugin code changes. PHPUnit tests live in
`tests/` and run inside a Moodle checkout (`vendor/bin/phpunit --testsuite
local_handbook_testsuite` after init).

## Git Rules

Branch: `main`. Commit and push only when the user asks. Clear imperative
messages, e.g. `Add revision publish workflow`.
