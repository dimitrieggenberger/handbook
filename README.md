# local_handbook — EuropaSchule Manual Institucional

A Moodle 5.2 local plugin providing the EuropaSchule institutional handbook:
one authoritative place for policies, procedures, quick guides and forms, with
capability-based access, a human-controlled publication workflow and a
complete revision history.

Full specification: [docs/SPECIFICATION.md](docs/SPECIFICATION.md).
UI mockups: [dev/README.md](dev/README.md).

## Status

First development milestone (spec §33) — a safe, reviewable skeleton:

- Plugin metadata, capabilities (§7.3) and schema for categories, pages,
  revisions and typed relations (§20).
- Handbook home (`/local/handbook/index.php`), category listing and the
  published-page reader (`view.php?page=<slug>`), protected by
  `local/handbook:view` in system context.
- Editorial workflow: draft → submit → review → approve → publish, with
  optimistic concurrency, immutable published revisions and
  `publishedrevisionid` as the single source of truth (§11).
- Category management, events for audit, EN/ES/DE language packs, PHPUnit
  coverage for the workflow service.

Later phases (per spec §27): search, reading paths and acknowledgements,
quality findings, the external API and the MCP adapter.

## Install

1. Build the ZIP: `powershell -ExecutionPolicy Bypass -File .\build-zip.ps1`
   → `dist/local_handbook-<version>.zip`.
2. Site administration → Plugins → Install plugins, or unzip into
   `local/handbook` on the server.
3. Assign `local/handbook:view` to the system-level staff role (spec §7.2);
   editors/reviewers/publishers receive the corresponding capabilities.
4. Create categories under Management → Manage categories, then create pages.

## Development

- Repository == plugin root (`version.php` at top level). `docs/` and `dev/`
  are never shipped.
- Conventions follow the `local_grades` shared plugin rulebook; see
  `AGENTS.md`.
- Lint: `php -l <file>`. Tests: standard Moodle PHPUnit inside a Moodle
  checkout.
