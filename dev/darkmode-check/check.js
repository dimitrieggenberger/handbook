// Dark theme checks for the handbook plugin (local_handbook), in the simulation Moodle.
//
// Adapted from local_grades' dev/darkmode-check/check.js. Opens the
// handbook's pages as the administrator in Space's dark mode (user
// preference darkmode-on) and in light mode, on a desktop and a phone, and
// audits what the plugin draws (the main region of #page-local-handbook-area,
// and any open modal):
// - text contrast against the real background behind it: WCAG AA, 4.5:1,
//   or 3:1 for large text (24px, or 18.66px bold);
// - in dark mode, light surfaces (a card, table, pill or field that stays
//   white on a dark page).
// manage/styleguide.php (the pattern catalogue) and the seeded
// "patrones-de-contenido" article cover every hb-* content pattern. The
// hb-chat thread is drawn as a light phone in both themes on purpose and is
// left out. Seed first: dev/sim/seed.php. Screenshots go to out/, the
// findings to out/report.md. Exit code 1 when anything is found.
// ONLY=name,name runs a subset.
//
// Usage: MOODLE_DIR=/opt/sim/moodle NODE_PATH=<a node_modules with playwright> node check.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path'), fs = require('fs');
const BASE = process.env.SIM_URL || 'http://localhost:8080';
const MOODLE = process.env.MOODLE_DIR || '/opt/sim/moodle';
const ONLY = (process.env.ONLY || '').split(',').filter(Boolean);
const out = path.join(__dirname, 'out');
const php = code => execFileSync('php', ['-r', 'define("CLI_SCRIPT", true); require "config.php"; ' + code],
  { cwd: MOODLE }).toString();

// Runs in the page: what fails, in the portal area and open panels.
const audit = dark => {
  const parse = c => {
    const m = /rgba?\(([^)]+)\)/.exec(c || '');
    if (!m) { return null; }
    const p = m[1].split(/[ ,/]+/).filter(Boolean).map(parseFloat);
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  };
  const over = (top, bottom) => ({ r: top.r * top.a + bottom.r * (1 - top.a), g: top.g * top.a + bottom.g * (1 - top.a),
    b: top.b * top.a + bottom.b * (1 - top.a), a: 1 });
  const lum = c => {
    const f = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
  };
  const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
  const hex = c => '#' + [c.r, c.g, c.b].map(v => Math.round(v).toString(16).padStart(2, '0')).join('');
  // The colour behind an element: its own and its ancestors' backgrounds,
  // stacked. A background image (not a gradient) makes it unknown.
  const behind = el => {
    const layers = [];
    for (let e = el; e && e.nodeType === 1; e = e.parentElement) {
      const cs = getComputedStyle(e);
      if (cs.backgroundImage && cs.backgroundImage !== 'none') {
        if (!/gradient/.test(cs.backgroundImage)) { return null; }
        // A gradient: judge against its first colour stop.
        const first = parse((/rgba?\([^)]+\)/.exec(cs.backgroundImage) || [])[0]);
        if (first) { layers.push(first); if (first.a >= 1) { break; } }
      }
      const c = parse(cs.backgroundColor);
      if (c && c.a > 0) { layers.push(c); if (c.a >= 1) { break; } }
    }
    let base = dark ? { r: 17, g: 18, b: 20, a: 1 } : { r: 255, g: 255, b: 255, a: 1 };
    for (let i = layers.length - 1; i >= 0; i--) { base = over(layers[i], base); }
    return base;
  };
  const label = el => {
    const cls = [...el.classList].filter(c => !/^(d-|p[xytb]?-|m[xytb]?-|col-|align-|justify-|flex-|gap-)/.test(c)).slice(0, 3);
    return el.tagName.toLowerCase() + (cls.length ? '.' + cls.join('.') : '');
  };
  const visible = el => {
    const r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) { return false; }
    const cs = getComputedStyle(el);
    return cs.visibility !== 'hidden' && cs.display !== 'none' && parseFloat(cs.opacity) > 0.05;
  };
  // Disabled controls are exempt from the contrast rule (WCAG 1.4.3);
  // Space's yellow warning button is the theme's own design.
  const skip = el => el.closest('.disabled, :disabled, [aria-disabled="true"], .btn-warning, svg, canvas, img, input[type="range"], .filemanager .fp-thumbnail, [aria-hidden="true"], .visually-hidden, .sr-only, .hb-chat');
  const scopes = [...document.querySelectorAll('#page-local-handbook-area [role="main"], ' + '.modal.show .modal-content')];
  const text = [], surfaces = [];
  const seen = new Set();
  for (const scope of scopes) {
    for (const el of scope.querySelectorAll('*')) {
      if (skip(el) || !visible(el)) { continue; }
      const cs = getComputedStyle(el);
      // Text: an element with its own words (or a field with a value; a
      // checkbox's or radio's value is never shown).
      const own = [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 0)
        || (/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName) && !/^(checkbox|radio)$/.test(el.type) && (el.value || el.placeholder));
      if (own) {
        const bg = behind(el);
        const fg = parse(cs.color);
        if (bg && fg) {
          const color = over(fg, bg);
          const size = parseFloat(cs.fontSize), bold = parseInt(cs.fontWeight, 10) >= 700;
          const need = size >= 24 || (size >= 18.66 && bold) ? 3 : 4.5;
          const r = ratio(color, bg);
          const words = String(el.value || el.placeholder || [...el.childNodes].filter(n => n.nodeType === 3)
            .map(n => n.textContent).join(' ')).trim().replace(/\s+/g, ' ').slice(0, 50);
          const key = label(el) + '|' + hex(color) + '|' + hex(bg);
          if (r < need && !seen.has(key)) {
            seen.add(key);
            text.push({ el: label(el), words, fg: hex(color), bg: hex(bg), ratio: Math.round(r * 100) / 100, need });
          }
        }
      }
      // Dark mode: a light surface of its own on a dark page.
      if (dark) {
        const c = parse(cs.backgroundColor);
        const r = el.getBoundingClientRect();
        if (c && c.a >= 0.6 && lum(c) > 0.45 && r.width * r.height > 1500) {
          const key = 'surface|' + label(el) + '|' + hex(c);
          if (!seen.has(key)) {
            seen.add(key);
            surfaces.push({ el: label(el), bg: hex(c), size: Math.round(r.width) + 'x' + Math.round(r.height),
              words: (el.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 40) });
          }
        }
      }
    }
  }
  return { text, surfaces };
};

(async () => {
  fs.rmSync(out, { recursive: true, force: true });
  fs.mkdirSync(out, { recursive: true });
  const ids = JSON.parse(php(`
    $page = $DB->get_record("local_handbook_page", ["slug" => "patrones-de-contenido"], "*", MUST_EXIST);
    $revs = $DB->get_fieldset_select("local_handbook_revision", "id", "pageid = ? ORDER BY versionnumber", [$page->id]);
    echo json_encode(["page" => (int)$page->id, "category" => (int)$page->categoryid,
      "from" => (int)$revs[0], "to" => (int)end($revs),
      "path" => (int)$DB->get_field_sql("SELECT MIN(id) FROM {local_handbook_path}"),
      "finding" => (int)$DB->get_field_sql("SELECT MIN(id) FROM {local_handbook_finding}")]);`));
  const setdark = on => php(`foreach ($DB->get_records_select("user", "deleted = 0 AND username <> 'guest'") as $user) {
      set_user_preference("darkmode-on", ${on ? 1 : 0}, $user); }`);

  const b = await chromium.launch({ executablePath: process.env.CHROMIUM || undefined });
  const login = async (user, pw, phone) => {
    const ctx = await b.newContext(phone ? { viewport: { width: 400, height: 860 }, deviceScaleFactor: 2, isMobile: true }
      : { viewport: { width: 1366, height: 900 } });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login/index.php');
    await p.fill('#username', user);
    await p.fill('#password', pw);
    await Promise.all([p.waitForNavigation(), p.click('#loginbtn')]);
    return p;
  };
  const admin = ['admin', 'Sim-Admin-2026!'];
  const L = '/local/handbook/';
  const P = 'patrones-de-contenido';
  // Each check: [name, user, url].
  const checks = [
    ['styleguide', admin, L + 'manage/styleguide.php'],
    ['index', admin, L + 'index.php'],
    ['category', admin, L + 'category.php?id=' + ids.category],
    ['view-patterns', admin, L + 'view.php?page=' + P],
    ['view-policy', admin, L + 'view.php?page=politica-de-convivencia-escolar'],
    ['view-quickguide', admin, L + 'view.php?page=guia-rapida-turno-de-patio-en-1-pagina'],
    ['view-procedure', admin, L + 'view.php?page=supervision-durante-los-recreos'],
    ['search', admin, L + 'search.php?q=recreo'],
    ['path', admin, L + 'path.php?id=' + ids.path],
    ['history', admin, L + 'history.php?page=' + P],
    ['compare', admin, L + 'compare.php?page=' + P + '&from=' + ids.from + '&to=' + ids.to],
    ['edit', admin, L + 'edit.php?id=' + ids.page],
    ['review', admin, L + 'review.php'],
    ['review-revision', admin, L + 'review.php?rid=' + ids.to],
    ['report', admin, L + 'report.php?page=' + P],
    ['print', admin, L + 'print.php?page=' + P],
    ['manage-categories', admin, L + 'manage/categories.php'],
    ['manage-areas', admin, L + 'manage/areas.php'],
    ['manage-paths', admin, L + 'manage/paths.php'],
    ['manage-path', admin, L + 'manage/paths.php?id=' + ids.path],
    ['manage-reports', admin, L + 'manage/reports.php'],
    ['manage-readers', admin, L + 'manage/readers.php'],
    ['manage-findings', admin, L + 'manage/findings.php'],
    ['manage-finding', admin, L + 'manage/findings.php?id=' + ids.finding],
    ['manage-changesets', admin, L + 'manage/changesets.php'],
    ['manage-recommendations', admin, L + 'manage/recommendations.php'],
    ['manage-questions', admin, L + 'manage/questions.php?id=' + ids.page],
    ['manage-images', admin, L + 'manage/images.php'],
    ['manage-holders', admin, L + 'manage/holders.php?id=' + ids.page],
    ['manage-import', admin, L + 'manage/import.php'],
  ].filter(c => !ONLY.length || ONLY.includes(c[0]));
  const phone = ['index', 'category', 'view-patterns', 'view-procedure', 'path', 'search'];

  const findings = [];
  const sessions = {};
  for (const dark of [true, false]) {
    setdark(dark);
    for (const k of Object.keys(sessions)) { delete sessions[k]; }
    for (const device of ['desktop', 'phone']) {
      for (const [name, [user, pw], url] of checks) {
        if (device === 'phone' && !phone.includes(name)) { continue; }
        const key = user + device;
        const shot = `${dark ? 'dark' : 'light'}-${device}-${name}.png`;
        try {
          sessions[key] = sessions[key] || await login(user, pw, device === 'phone');
          const p = sessions[key];
          await p.goto(BASE + url, { waitUntil: 'networkidle', timeout: 60000 });
          if (!(await p.$('body#page-local-handbook-area'))) {
            const why = await p.evaluate(() => (document.querySelector('.errormessage, .alert-danger, #notice, .box.errorbox')
              || document.body).innerText.trim().replace(/\s+/g, ' ').slice(0, 120));
            await p.screenshot({ path: path.join(out, shot) });
            findings.push({ name, dark, device, shot, skipped: 'not a handbook page: ' + why, text: [], surfaces: [] });
            continue;
          }
          const crash = await p.evaluate(() => { const e = document.querySelector('.Whoops .exc-message, .exc-message');
            return e ? e.innerText.trim().split('\n')[0] : ''; });
          if (crash) { throw new Error('PHP error on the page: ' + crash); }
          const isdark = await p.evaluate(() => document.body.classList.contains('theme-dark'));
          if (dark && !isdark && await p.$('body.pagelayout-embedded')) {
            // Space draws embedded pages (quick entry) without its dark mode.
            findings.push({ name, dark, device, shot, skipped: 'embedded layout, never dark', text: [], surfaces: [] });
            continue;
          }
          if (isdark !== dark) { throw new Error('dark mode is ' + (isdark ? 'on' : 'off')); }
          const result = await p.evaluate(audit, dark);
          await p.screenshot({ path: path.join(out, shot), fullPage: true });
          findings.push({ name, dark, device, shot, ...result });
        } catch (e) {
          findings.push({ name, dark, device, error: e.message.split('\n')[0], text: [], surfaces: [] });
        }
      }
    }
    for (const p of Object.values(sessions)) { await p.context().close(); }
  }
  setdark(false);
  await b.close();

  let failures = 0, md = '# Dark theme checks (local_handbook)\n\n';
  md += '| Page | Mode | Device | Low-contrast text | Light surfaces |\n|---|---|---|---|---|\n';
  for (const f of findings) {
    const n = f.text.length + f.surfaces.length + (f.error ? 1 : 0);
    failures += n;
    const t = f.error ? 'ERROR: ' + f.error : f.skipped ? 'SKIPPED: ' + f.skipped : f.text.length;
    md += `| ${f.name} | ${f.dark ? 'dark' : 'light'} | ${f.device} | ${t} | ${f.surfaces.length} |\n`;
  }
  for (const f of findings.filter(x => x.text.length || x.surfaces.length)) {
    md += `\n## ${f.name} (${f.dark ? 'dark' : 'light'}, ${f.device}) - ${f.shot}\n\n`;
    for (const t of f.text) {
      md += `- text \`${t.el}\` "${t.words}": ${t.fg} on ${t.bg} = ${t.ratio}:1 (needs ${t.need}:1)\n`;
    }
    for (const s of f.surfaces) {
      md += `- light surface \`${s.el}\` ${s.bg} ${s.size} "${s.words}"\n`;
    }
  }
  fs.writeFileSync(path.join(out, 'report.md'), md);
  for (const f of findings) {
    const bad = f.text.length + f.surfaces.length || f.error;
    console.log(`${bad ? 'FAIL' : f.skipped ? 'skip' : 'ok  '} ${f.dark ? 'dark ' : 'light'} ${f.device.padEnd(7)} `
      + `${f.name}${f.error ? ' ERROR ' + f.error : ''}${f.skipped ? ' (' + f.skipped + ')' : ''}`
      + `${f.text.length ? ' text:' + f.text.length : ''}${f.surfaces.length ? ' surfaces:' + f.surfaces.length : ''}`);
  }
  console.log(failures ? failures + ' finding(s): see out/report.md' : 'all checks passed');
  process.exit(failures ? 1 : 0);
})().catch(e => { console.error('ABORT', e.message.split('\n')[0]); process.exit(2); });
