// Builds the website into site/dist/: one HTML page and one Markdown copy per page and language,
// plus robots.txt, sitemap.xml, llms.txt and 404.html.
//
//   npm run build        (core, plugin copy, then this script)
//   npm run build:site   (this script only; the core package must have been built)
//
// Environment:
//   SITE_URL   absolute address of the site, default https://elephancube.github.io/askai-button/
//   SITE_BASE  path the site is served under, default: the path of SITE_URL
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { gzipSync } from 'node:zlib';
import { Marked } from 'marked';
import { DEFAULT_SITE_URL, FALLBACK_LANG, LANGS, NOT_FOUND, PAGES, REPO_URL, SITE_NAME, STRINGS } from './site.config.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..');
const out = join(here, 'dist');
const coreDist = join(root, 'packages/core/dist');
const SCRIPT = 'askai-button.min.js';

function fail(message) {
  console.error(`build-site: ${message}`);
  process.exit(1);
}

// ---------------------------------------------------------------------------------------------
// Where the site lives

const withSlashes = (path) => ('/' + path + '/').replace(/\/{2,}/g, '/');

let siteUrl;
try {
  siteUrl = new URL(process.env.SITE_URL || DEFAULT_SITE_URL);
} catch {
  fail(`SITE_URL is not a URL: ${process.env.SITE_URL}`);
}
if (!/^https?:$/.test(siteUrl.protocol)) fail('SITE_URL must start with http:// or https://');
siteUrl.pathname = withSlashes(siteUrl.pathname);
siteUrl.search = '';
siteUrl.hash = '';
const SITE_URL = siteUrl.href;
const BASE = withSlashes(process.env.SITE_BASE || siteUrl.pathname);
if (BASE !== siteUrl.pathname) {
  console.warn(`build-site: SITE_BASE (${BASE}) is not the path of SITE_URL (${SITE_URL}). Links and canonical URLs will disagree.`);
}

const langDir = (lang) => (lang === LANGS[0] ? '' : lang + '/');
const pageDir = (lang, slug) => langDir(lang) + (slug ? slug + '/' : '');
/** Address inside the site, for links between its pages. */
const path = (rel) => BASE + rel;
/** The same address with scheme and host, for metadata and the Markdown copies. */
const absolute = (rel) => SITE_URL + rel;

// ---------------------------------------------------------------------------------------------
// Facts taken from the library itself, so that the pages cannot drift away from it

const scriptFile = join(coreDist, SCRIPT);
const moduleFile = join(coreDist, 'askai-button.esm.js');
if (!existsSync(scriptFile) || !existsSync(moduleFile)) {
  fail(`${scriptFile} is missing. Build the library first: run "npm run build" in the repository root.`);
}
const library = await import(pathToFileURL(moduleFile).href);
const scriptBytes = readFileSync(scriptFile);
const version = JSON.parse(readFileSync(join(root, 'packages/core/package.json'), 'utf8')).version;
const kb = (bytes) => (bytes / 1024).toFixed(1);

const facts = {
  version,
  repo: REPO_URL,
  gzip_kb: kb(gzipSync(scriptBytes).length),
  min_kb: kb(scriptBytes.length),
};

// ---------------------------------------------------------------------------------------------
// Small helpers

const escapeHtml = (text) =>
  String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const stripTags = (html) => html.replace(/<[^>]*>/g, '');

/** An id for a heading. Letters and digits of any script are kept, so Japanese headings work. */
function slugify(text) {
  return (
    text
      .trim()
      .toLowerCase()
      .replace(/[^\p{L}\p{N}]+/gu, '-')
      .replace(/^-+|-+$/g, '') || 'section'
  );
}

function write(rel, content) {
  const target = join(out, rel);
  mkdirSync(dirname(target), { recursive: true });
  writeFileSync(target, content);
}

/** `key: value` lines between two `---` lines at the top of a content file. */
function readPage(file) {
  const source = readFileSync(file, 'utf8').replace(/\r\n/g, '\n');
  const match = /^---\n([\s\S]*?)\n---\n/.exec(source);
  if (!match) fail(`${file}: no front matter.`);
  const meta = {};
  for (const line of match[1].split('\n')) {
    const at = line.indexOf(':');
    if (at > 0) meta[line.slice(0, at).trim()] = line.slice(at + 1).trim();
  }
  for (const key of ['title', 'description', 'nav']) {
    if (!meta[key]) fail(`${file}: "${key}" is missing from the front matter.`);
  }
  return { meta, body: source.slice(match[0].length).trim() + '\n' };
}

/** Calls `visit` for every line that is not part of a fenced code block. */
function mapLines(markdown, visit) {
  let fence = '';
  return markdown
    .split('\n')
    .map((line) => {
      const mark = /^\s*(```+|~~~+)/.exec(line);
      if (mark) {
        if (!fence) fence = mark[1];
        else if (mark[1].startsWith(fence)) fence = '';
        return line;
      }
      return fence ? line : visit(line);
    })
    .join('\n');
}

/** Replaces `{{name}}` and `{{name:argument}}`. Unknown names stop the build. */
function expand(markdown, file, values) {
  return markdown.replace(/\{\{([a-z_]+)(?::([^}]*))?\}\}/g, (match, name, arg) => {
    const value = values[name];
    if (value === undefined) fail(`${file}: unknown placeholder ${match}`);
    return typeof value === 'function' ? value(arg || '') : value;
  });
}

const DIRECTIVE = /^:::\s*([a-z][\w -]*)?\s*$/;

/**
 * `::: name modifier` ... `:::` wraps a part of a page in an element, for layout.
 * `section` becomes a full-width band; every other name becomes a `<div>` with that class.
 */
function directivesToHtml(markdown, file) {
  const open = [];
  const result = mapLines(markdown, (line) => {
    const match = DIRECTIVE.exec(line);
    if (!match) return line;
    if (!match[1]) {
      if (!open.length) fail(`${file}: ":::" without an opening line.`);
      return '\n' + open.pop() + '\n';
    }
    const [name, ...modifiers] = match[1].trim().split(/\s+/);
    if (name === 'section') {
      open.push('</div></section>');
      const classes = ['band', ...modifiers.map((m) => 'band--' + m)].join(' ');
      return `\n<section class="${classes}"><div class="container">\n`;
    }
    open.push('</div>');
    return `\n<div class="${[name, ...modifiers].join(' ')}">\n`;
  });
  if (open.length) fail(`${file}: a ":::" block is not closed.`);
  return result;
}

/** The Markdown copy has no layout: directive lines and `{#id}` markers are dropped. */
function toPlainMarkdown(markdown) {
  const DROP = '\u0000';
  return mapLines(markdown, (line) => (DIRECTIVE.test(line) ? DROP : line.replace(/\s*\{#[\w-]+\}\s*$/, '')))
    .split('\n')
    .filter((line) => line !== DROP)
    .join('\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

/** Markdown to HTML, with heading ids, a list of the h2 headings, and accessible tables. */
function render(markdown, strings, pageTitle) {
  const toc = [];
  const used = new Set();
  let lastHeading = pageTitle;
  const labels = new Map();

  const marked = new Marked({
    gfm: true,
    renderer: {
      heading({ tokens, depth }) {
        let html = this.parser.parseInline(tokens);
        let id = '';
        const explicit = /\s*\{#([\w-]+)\}\s*$/.exec(html);
        if (explicit) {
          id = explicit[1];
          html = html.slice(0, explicit.index);
        }
        const text = stripTags(html);
        lastHeading = text;
        if (depth === 1) return `<h1>${html}</h1>\n`;
        if (!id) id = slugify(text);
        let unique = id;
        for (let n = 2; used.has(unique); n++) unique = `${id}-${n}`;
        used.add(unique);
        if (depth === 2) toc.push({ id: unique, html });
        return `<h${depth} id="${unique}">${html}</h${depth}>\n`;
      },
      // Short pieces of code stay on one line; long ones may wrap so that narrow screens do not overflow.
      codespan({ text }) {
        return `<code${text.length <= 24 ? ' class="nowrap"' : ''}>${escapeHtml(text)}</code>`;
      },
      table(token) {
        const cell = (tag, attrs, item) => `<${tag}${attrs}>${this.parser.parseInline(item.tokens)}</${tag}>`;
        const head = token.header.map((item) => cell('th', ' scope="col"', item)).join('');
        const rows = token.rows
          .map((row) => '<tr>' + row.map((item, i) => (i ? cell('td', '', item) : cell('th', ' scope="row"', item))).join('') + '</tr>')
          .join('\n');
        // Two tables under one heading still need different names.
        const name = strings.table.replace('{title}', lastHeading);
        const count = (labels.get(name) || 0) + 1;
        labels.set(name, count);
        const label = escapeHtml(count > 1 ? `${name} (${count})` : name);
        // The region scrolls sideways on narrow screens, so it has to be reachable by keyboard.
        return `<div class="table-wrap" tabindex="0" role="region" aria-label="${label}"><table>\n<thead><tr>${head}</tr></thead>\n<tbody>\n${rows}\n</tbody></table></div>\n`;
      },
    },
  });
  return { html: marked.parse(markdown), toc };
}

// ---------------------------------------------------------------------------------------------
// Shared layout

// The glyph of the button itself, reused as the mark of the site.
const ROBOT =
  'M7 8.6h10a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5H7a3.5 3.5 0 0 1-3.5-3.5v-6A3.5 3.5 0 0 1 7 8.6zM20.5 12.1H22a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1.5zM3.5 17.1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1.5zM9 11.2a3.4 3.4 0 0 0 0 6.8h6a3.4 3.4 0 0 0 0-6.8zM7.9 14.6a1.5 1.5 0 1 1 3 0 1.5 1.5 0 1 1-3 0zM13.1 14.6a1.5 1.5 0 1 1 3 0 1.5 1.5 0 1 1-3 0zM10.3 3.8a1.7 1.7 0 1 1 3.4 0 1.7 1.7 0 1 1-3.4 0zM11 5h2v4h-2z';
const robotSvg = `<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="${ROBOT}"/></svg>`;

/** A picture of the panel made of plain elements, with the wording the library really uses. */
function mockPanel(lang) {
  const s = STRINGS[lang];
  const { labels, providers } = library.defaults(lang);
  const chosen = providers[0];
  const fill = (text) => text.replace('{provider}', chosen.name);
  const e = escapeHtml;
  return `<figure class="mock-figure">
<div class="mock" role="img" aria-label="${e(s.mockLabel)}">
<div class="mock-head"><span class="mock-title">${e(labels.panelTitle)}</span><span class="mock-close">×</span></div>
<span class="mock-sub">${e(labels.panelSubtitle)}</span>
<span class="mock-input">${e(s.mockQuestion)}</span>
<span class="mock-chips">${s.presets.map((text) => `<span>${e(text)}</span>`).join('')}</span>
<span class="mock-ais">${providers.map((p, i) => `<span${i ? '' : ' class="on"'}>${e(p.name)}</span>`).join('')}</span>
<span class="mock-note">${e(fill(labels.note))}</span>
<span class="mock-send">${e(fill(labels.send))}</span>
</div>
<figcaption>${e(s.mockCaption)}</figcaption>
</figure>`;
}

function layout({ lang, slug, title, description, main, pages, isHome, notFound }) {
  const s = STRINGS[lang];
  const e = escapeHtml;
  const dir = pageDir(lang, slug);
  const fullTitle = isHome ? `${SITE_NAME} — ${title}` : `${title} | ${SITE_NAME}`;
  const others = LANGS.filter((code) => code !== lang);

  const head = [
    '<meta charset="utf-8">',
    '<meta name="viewport" content="width=device-width, initial-scale=1">',
    `<title>${e(fullTitle)}</title>`,
    `<meta name="description" content="${e(description)}">`,
  ];
  if (notFound) {
    head.push('<meta name="robots" content="noindex">');
  } else {
    head.push(
      `<link rel="canonical" href="${e(absolute(dir))}">`,
      ...LANGS.map((code) => `<link rel="alternate" hreflang="${code}" href="${e(absolute(pageDir(code, slug)))}">`),
      `<link rel="alternate" hreflang="x-default" href="${e(absolute(pageDir(FALLBACK_LANG, slug)))}">`,
      `<link rel="alternate" type="text/markdown" href="${e(absolute(dir + 'index.md'))}">`,
      '<meta property="og:type" content="website">',
      `<meta property="og:site_name" content="${SITE_NAME}">`,
      `<meta property="og:title" content="${e(fullTitle)}">`,
      `<meta property="og:description" content="${e(description)}">`,
      `<meta property="og:url" content="${e(absolute(dir))}">`,
      `<meta property="og:locale" content="${s.ogLocale}">`,
    );
  }
  head.push(
    `<link rel="icon" href="${path('assets/favicon.svg')}" type="image/svg+xml">`,
    `<link rel="stylesheet" href="${path('assets/site.css')}">`,
  );

  const nav = pages
    .map((page) => {
      const current = !notFound && page.slug === slug ? ' aria-current="page"' : '';
      return `<li><a href="${path(pageDir(lang, page.slug))}"${current}>${e(page.meta.nav)}</a></li>`;
    })
    .join('\n');

  // On the 404 page nobody knows which page was meant, so the switch leads to the other top page.
  const langSwitch = others
    .map((code) => `<li><a href="${path(pageDir(code, notFound ? '' : slug))}" lang="${code}" hreflang="${code}">${STRINGS[code].langName}</a></li>`)
    .join('');

  // The button of this very project, added exactly as the installation page describes.
  // The site lives below the top of its host, so the address handed to the AI is given explicitly.
  const widget = [
    `src="${path(SCRIPT)}"`,
    'data-askai',
    `data-site-url="${e(absolute(langDir(lang)))}"`,
    notFound ? '' : `data-md-url="${e(absolute(dir + 'index.md'))}"`,
    `data-presets="${e(s.presets.join('|'))}"`,
  ]
    .filter(Boolean)
    .join(' ');

  return `<!doctype html>
<html lang="${lang}">
<head>
${head.join('\n')}
</head>
<body>
<a class="skip" href="#main">${e(s.skip)}</a>
<header class="site-header">
<div class="container site-header-inner">
<a class="brand" href="${path(langDir(lang))}">${robotSvg}<span>${SITE_NAME}</span></a>
<nav class="site-nav" aria-label="${e(s.mainNav)}">
<ul>
${nav}
</ul>
</nav>
<nav class="site-tools" aria-label="${e(s.langNav)}">
<ul>${langSwitch}<li><a href="${REPO_URL}">${e(s.github)}</a></li></ul>
</nav>
</div>
</header>
<main id="main" tabindex="-1">
${main}
</main>
<footer class="site-footer">
<div class="container">
<nav aria-label="${e(s.footerNav)}">
<ul>
${pages.map((page) => `<li><a href="${path(pageDir(lang, page.slug))}">${e(page.meta.nav)}</a></li>`).join('\n')}
<li><a href="${REPO_URL}">${e(s.github)}</a></li>
${notFound ? '' : `<li><a href="${path(dir + 'index.md')}">${e(s.markdown)}</a></li>`}
</ul>
</nav>
<p>${e(s.noTracking)}</p>
<p>${e(s.trademark)}</p>
<p>${e(s.licence)}</p>
<p class="copyright">Copyright © 2026 Elephancube Inc.</p>
</div>
</footer>
<script ${widget}></script>
</body>
</html>
`;
}

// ---------------------------------------------------------------------------------------------
// Pages

const site = {};
for (const lang of LANGS) {
  const dir = join(here, 'content', lang);
  const found = readdirSync(dir).filter((name) => name.endsWith('.md')).sort();
  const expected = PAGES.map((slug) => (slug || 'index') + '.md').sort();
  if (found.join() !== expected.join()) {
    fail(`content/${lang} has [${found}] but the pages of the site are [${expected}]. Every language needs the same pages.`);
  }
  site[lang] = PAGES.map((slug) => ({ slug, file: `content/${lang}/${slug || 'index'}.md`, ...readPage(join(dir, (slug || 'index') + '.md')) }));
}

rmSync(out, { recursive: true, force: true });
mkdirSync(out, { recursive: true });

const written = [];
for (const lang of LANGS) {
  const s = STRINGS[lang];
  const dictionary = library.defaults(lang).labels;

  for (const page of site[lang]) {
    const { slug, meta, body, file } = page;
    const dir = pageDir(lang, slug);
    const isHome = meta.layout === 'home';
    const other = LANGS.filter((code) => code !== lang)[0];

    const shared = {
      ...facts,
      query_template: dictionary.queryTemplate,
      query_example: dictionary.queryTemplate.replace('{question}', s.mockQuestion).replace('{site_url}', absolute(langDir(lang))),
      site_url: absolute(langDir(lang)),
      example_url: absolute(dir),
    };

    // --- HTML
    const forHtml = expand(body, file, {
      ...shared,
      page: (target) => path(pageDir(lang, target)),
      file: (name) => path(name),
      mock: () => '\n' + mockPanel(lang) + '\n',
    });
    const { html, toc } = render(directivesToHtml(forHtml, file), s, meta.heading || meta.title);

    let main;
    if (isHome) {
      main = html;
    } else {
      const contents = toc.map((item) => `<li><a href="#${item.id}">${item.html}</a></li>`).join('\n');
      main = `<div class="band band--title"><div class="container">
<h1>${escapeHtml(meta.heading || meta.title)}</h1>
<p class="lead">${escapeHtml(meta.description)}</p>
</div></div>
<div class="container doc">
<nav class="toc" aria-label="${escapeHtml(s.toc)}">
<p class="toc-title" aria-hidden="true">${escapeHtml(s.toc)}</p>
<ul>
${contents}
</ul>
</nav>
<div class="prose">
${html}
</div>
</div>`;
    }
    if ((main.match(/<h1[ >]/g) || []).length !== 1) fail(`${file}: a page needs exactly one h1.`);

    write(dir + 'index.html', layout({ lang, slug, title: meta.title, description: meta.description, main, pages: site[lang], isHome }));

    // --- Markdown copy of the same page, readable on its own: absolute links, no layout markers.
    const forMarkdown = expand(body, file, {
      ...shared,
      page: (target) => absolute(pageDir(lang, target)),
      file: (name) => absolute(name),
      mock: () => '',
    });
    const top = isHome ? '' : `# ${meta.heading || meta.title}\n\n${meta.description}\n\n`;
    const foot = `\n\n---\n\n- HTML: ${absolute(dir)}\n- ${STRINGS[other].langName}: ${absolute(pageDir(other, slug) + 'index.md')}\n- llms.txt: ${absolute('llms.txt')}\n`;
    write(dir + 'index.md', top + toPlainMarkdown(forMarkdown) + foot);

    written.push({ lang, slug, dir, meta });
  }
}

// ---------------------------------------------------------------------------------------------
// The reference pages have to name everything the library accepts.

{
  const types = readFileSync(join(root, 'packages/core/src/types.ts'), 'utf8');
  const entry = readFileSync(join(root, 'packages/core/src/index.ts'), 'utf8');
  const optionsBlock = (/export interface Options \{([\s\S]*?)\n\}/.exec(types) || [])[1] || '';
  const options = [...optionsBlock.matchAll(/^\s{2}(\w+)\??:/gm)].map((m) => m[1]);
  const attributes = [...entry.matchAll(/\b(?:text|num|list)\('([a-z-]+)'/g)].map((m) => 'data-' + m[1]);
  const defaults = library.defaults('en');
  const needed = [
    ...options,
    ...attributes,
    ...Object.keys(library),
    ...Object.keys(defaults.labels),
    ...defaults.providers.flatMap((p) => [p.id, p.urlTemplate, p.color]),
  ];
  if (options.length < 5 || attributes.length < 5) fail('could not read the options or the data-* attributes from the library source.');
  for (const lang of LANGS) {
    const text = readFileSync(join(out, pageDir(lang, 'reference'), 'index.md'), 'utf8');
    const missing = needed.filter((name) => !text.includes('`' + name + '`') && !text.includes('`' + name + '('));
    if (missing.length) fail(`content/${lang}/reference.md does not mention: ${missing.join(', ')}`);
  }
}

// ---------------------------------------------------------------------------------------------
// Files next to the pages

copyFileSync(scriptFile, join(out, SCRIPT));
mkdirSync(join(out, 'assets'), { recursive: true });
// The picture of the panel shows the default state of the real one: the first service is selected,
// and its colour comes from the library, as does the fallback for a service without a colour.
{
  const first = library.defaults('en').providers[0];
  const color = /^#[0-9a-f]{3,8}$/i.test(first.color || '') ? first.color : '#27455c';
  const css = readFileSync(join(here, 'assets/site.css'), 'utf8');
  write('assets/site.css', `${css}
/* Added by the build: colour of the service selected by default (${first.id}). */
.mock {
  --mock-select: ${color};
}
`);
}
write('assets/favicon.svg', `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="4" fill="#222"/><path fill="#fff" transform="translate(3 3.3) scale(.75)" d="${ROBOT}"/></svg>\n`);

// 404.html: served by the host for every address that does not exist, at any depth.
{
  const blocks = LANGS.map((lang, i) => {
    const t = NOT_FOUND[lang];
    const heading = i ? `<h2 lang="${lang}">${escapeHtml(t.title)}</h2>` : `<h1>${escapeHtml(t.title)}</h1>`;
    return `${heading}
<p${i ? ` lang="${lang}"` : ''}>${escapeHtml(t.text)}</p>
<p class="actions"${i ? ` lang="${lang}"` : ''}><a href="${path(langDir(lang))}"${i ? ` hreflang="${lang}"` : ''}>${escapeHtml(t.home)}</a></p>`;
  }).join('\n');
  const lang = LANGS[0];
  write(
    '404.html',
    layout({
      lang,
      slug: '',
      title: NOT_FOUND[lang].title,
      description: NOT_FOUND[lang].text,
      main: `<div class="band"><div class="container prose not-found">\n${blocks}\n</div></div>`,
      pages: site[lang],
      notFound: true,
    }),
  );
}

// robots.txt: everything may be read, by search engines and by AI services alike.
write(
  'robots.txt',
  `# Everything on this site may be crawled, by search engines and by AI services alike.
# No crawler is blocked: the "Ask AI" button only works if the AI service can read these pages.
User-agent: *
Allow: /

Sitemap: ${absolute('sitemap.xml')}
`,
);

write(
  'sitemap.xml',
  `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
${written
  .map(
    ({ slug, dir }) => `  <url>
    <loc>${absolute(dir)}</loc>
${[...LANGS.map((code) => [code, code]), ['x-default', FALLBACK_LANG]]
  .map(([hreflang, code]) => `    <xhtml:link rel="alternate" hreflang="${hreflang}" href="${absolute(pageDir(code, slug))}"/>`)
  .join('\n')}
  </url>`,
  )
  .join('\n')}
</urlset>
`,
);

// llms.txt: tells an AI that was handed only the top address where the Markdown copies are.
{
  const section = (lang) =>
    written
      .filter((page) => page.lang === lang)
      .map(({ dir, meta }) => `- [${meta.title}](${absolute(dir + 'index.md')}): ${meta.description}`)
      .join('\n');
  write(
    'llms.txt',
    `# ${SITE_NAME}

> ${STRINGS.en.tagline}. The visitor types a question and picks ChatGPT, Claude or Google AI Mode; that service opens in a new tab with the question and the address of the site filled in. No API, no server, no tracking. A JavaScript library (MIT) and a WordPress plugin (GPL-2.0-or-later), version ${version}.

Every page of this site exists as HTML and as Markdown. The Markdown copy of a page is the file index.md in the directory of that page. The pages are available in English and in Japanese with the same content.

## English

${section('en')}

## 日本語 (Japanese)

${section('ja')}

## Optional

- [Source code](${REPO_URL}): the repository of the library, the WordPress plugin and this site
- [${SCRIPT}](${absolute(SCRIPT)}): the script itself, ${facts.min_kb} KB (${facts.gzip_kb} KB gzip)
`,
  );
}

console.log(`build-site: ${written.length} pages in ${LANGS.length} languages -> ${out}`);
console.log(`build-site: SITE_URL=${SITE_URL} SITE_BASE=${BASE}`);
