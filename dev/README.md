# Development mockups

Static HTML mockups of `local_handbook` pages live in `dev/<name>-mockup/index.html`,
following the conventions of the `local_grades` repo (its `AGENTS.md`, "UI Mockups",
and `dev/mockup-template/`).

## Previewing

Open the mockup's `index.html` directly in a browser on a machine with normal
internet access. The page links the live compiled theme stylesheet from
`learn.europaschule.eu` (Space theme + Bootstrap + all deployed plugins), so no
local server is required yet — nothing in this repo is linked relatively.

Alternatively, the `mockups` launch config (`.claude/launch.json`) serves the repo
root on port 8137 (`python -m http.server`), mirroring the grades repo's config;
browse to `/dev/<name>-mockup/index.html`. This is also what the Claude Code
Browser pane uses, since it cannot open `file://` URLs. Once the plugin skeleton
exists and mockups link the repo's `styles.css` relatively, this server becomes
required rather than optional.

## Conventions

- Copy an existing mockup (or the grades repo's `dev/mockup-template/`) to start.
- Body id is the plugin pagetype: `page-local-handbook-area`. All candidate CSS is
  scoped to it, classes prefixed `local-handbook-*`.
- Candidate styles live in the labelled "plugin-candidate styles" block; that block
  is the seed for the future `local/handbook/styles.css`. Harness-only styles
  (Font Awesome CORS fallback, `.mockup-note`, `.mockup-page`) never ship.
- Each mockup opens with a dated `.mockup-note` alert stating what it mocks and
  which patterns are candidates versus already shipped.
- Dialogs follow the local_grades medal-dialog pattern: a `.local-handbook-dialog`
  shell (backdrop, panel, Escape/backdrop/close handling, focus return,
  reduced-motion support) stands in for Moodle `core/modal`; only the panel
  content styling is a plugin candidate. The open/close script is harness-only.
  A `?dialog=<name>` query auto-opens one for review.

## Mockups

| Mockup | Mocks | Spec |
|---|---|---|
| `home-mockup` | Handbook home (`index.php`) | §12.1 |
| `reader-view-mockup` | Reader view (`view.php`): procedure, pending acknowledgement; report-error and revision-history dialogs (`?dialog=report\|history` auto-opens) | §12.2, §19, §11 |
| `policy-reader-mockup` | Reader view (`view.php`): policy, authority level 1, confirmed acknowledgement | §12.2, §10.3 |
| `quick-guide-reader-mockup` | Reader view (`view.php`): quick guide, authority note, no acknowledgement | §12.2, §10.3 |
| `reading-path-mockup` | Assigned reading path (`path.php`) | §15, §16 |

The reader mockups plus the path share one storyline (the *Supervisión durante los
recreos* scenario) and cross-link each other with relative links, so states stay
consistent: 18/31 confirmed, three pending confirmations, current section 5.
