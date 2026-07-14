# Development mockups

Static HTML mockups of `local_handbook` pages live in `dev/<name>-mockup/index.html`,
following the conventions of the `local_grades` repo (its `AGENTS.md`, "UI Mockups",
and `dev/mockup-template/`).

## Previewing

Open the mockup's `index.html` directly in a browser on a machine with normal
internet access. The page links the live compiled theme stylesheet from
`learn.europaschule.eu` (Space theme + Bootstrap + all deployed plugins), so no
local server is required yet — nothing in this repo is linked relatively.

Once the plugin skeleton exists and mockups link the repo's `styles.css`, serve the
repo root the same way the grades repo does (its `mockups` launch config serves the
repo on a local port) so relative links resolve.

## Conventions

- Copy an existing mockup (or the grades repo's `dev/mockup-template/`) to start.
- Body id is the plugin pagetype: `page-local-handbook-area`. All candidate CSS is
  scoped to it, classes prefixed `local-handbook-*`.
- Candidate styles live in the labelled "plugin-candidate styles" block; that block
  is the seed for the future `local/handbook/styles.css`. Harness-only styles
  (Font Awesome CORS fallback, `.mockup-note`, `.mockup-page`) never ship.
- Each mockup opens with a dated `.mockup-note` alert stating what it mocks and
  which patterns are candidates versus already shipped.

## Mockups

| Mockup | Mocks | Spec |
|---|---|---|
| `reader-view-mockup` | Published-page reader view (`view.php`) | §12.2 |
