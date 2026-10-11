// Translation files of the WordPress plugin. No dependencies.
//
//   node scripts/i18n.mjs
//
// 1. Collects the translatable strings from the PHP sources and writes languages/askai-button.pot.
// 2. Rewrites every languages/askai-button-<locale>.po in the order of the template, keeping its translations.
// 3. Compiles each .po into the .mo file WordPress loads.
//
// Exits with an error if a .po file lacks a translation, has one that is no longer used,
// or has a translation whose placeholders differ from the original.
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const DOMAIN = 'askai-button';
const pluginDir = fileURLToPath(new URL('../wordpress/askai-button/', import.meta.url));
const langDir = join(pluginDir, 'languages');
const FUNCTIONS = ['__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e'];

function phpFiles(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) return name === 'tests' || name === 'languages' ? [] : phpFiles(path);
    return name.endsWith('.php') ? [path] : [];
  });
}

/** msgid → { refs, comments } in the order of first appearance. */
function collect() {
  const found = new Map();
  const add = (id, ref, comment) => {
    const entry = found.get(id) || { refs: [], comments: [] };
    entry.refs.push(ref);
    if (comment && !entry.comments.includes(comment)) entry.comments.push(comment);
    found.set(id, entry);
  };
  const call = new RegExp(
    `(?<![\\w$>:])(?:${FUNCTIONS.join('|')})\\(\\s*'((?:\\\\.|[^'\\\\])*)'\\s*,\\s*'${DOMAIN}'\\s*\\)`,
    'g',
  );
  // A call with this text domain that the pattern above cannot read would be lost silently.
  const anyCall = new RegExp(`(?<![\\w$>:])(?:${FUNCTIONS.join('|')}|_x|_n|_nx|esc_html_x|esc_attr_x)\\(`, 'g');

  for (const file of phpFiles(pluginDir).sort()) {
    const source = readFileSync(file, 'utf8');
    const name = relative(pluginDir, file).replace(/\\/g, '/');
    const lineOf = (index) => source.slice(0, index).split('\n').length;
    const lines = source.split('\n');
    let readable = 0;
    for (const match of source.matchAll(call)) {
      readable++;
      const line = lineOf(match.index);
      // The comment for translators sits on one of the three lines before the call, or on its line.
      const context = lines.slice(Math.max(0, line - 4), line).join('\n');
      const comments = [...context.matchAll(/\/\*\s*(translators:[^*]*?)\s*\*\//g)];
      const text = match[1].replace(/\\(['\\])/g, '$1');
      add(text, `${name}:${line}`, comments.length ? comments[comments.length - 1][1] : '');
    }
    const total = [...source.matchAll(anyCall)].length;
    if (total !== readable) {
      throw new Error(`${name}: ${total - readable} translation call(s) are not of the form __( 'text', '${DOMAIN}' ).`);
    }
    if (name === `${DOMAIN}.php`) {
      const description = /^\s*\*\s*Description:\s*(.+)$/m.exec(source);
      if (description) add(description[1].trim(), name, 'Description of the plugin');
    }
  }
  return found;
}

const quote = (text) =>
  '"' + text.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\t/g, '\\t') + '"';

const unquote = (text) =>
  text.replace(/\\(.)/g, (_, char) => (char === 'n' ? '\n' : char === 't' ? '\t' : char));

/** msgid → msgstr. The header is the entry with the empty msgid. */
function parsePo(source) {
  const out = new Map();
  let id = null;
  let str = null;
  let target = null;
  const flush = () => {
    if (id !== null && str !== null) out.set(id, str);
    id = str = target = null;
  };
  for (const raw of source.split(/\r?\n/)) {
    const line = raw.trim();
    let match;
    if (!line || line.startsWith('#')) {
      if (!line) flush();
    } else if ((match = /^msgid\s+"(.*)"$/.exec(line))) {
      flush();
      id = unquote(match[1]);
      target = 'id';
    } else if ((match = /^msgstr\s+"(.*)"$/.exec(line))) {
      str = unquote(match[1]);
      target = 'str';
    } else if ((match = /^"(.*)"$/.exec(line)) && target) {
      if (target === 'id') id += unquote(match[1]);
      else str += unquote(match[1]);
    } else {
      throw new Error(`Cannot read this line of a .po file: ${raw}`);
    }
  }
  flush();
  return out;
}

function header(locale) {
  const lines = [
    'Project-Id-Version: AskAI Button',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    `X-Domain: ${DOMAIN}`,
  ];
  if (locale) lines.push(`Language: ${locale}`, 'Plural-Forms: nplurals=1; plural=0;');
  return lines.map((line) => line + '\n').join('');
}

function writePo(path, strings, translate, head) {
  const blocks = [
    '# Copyright (c) Elephancube Inc.\n# This file is distributed under the GPL-2.0-or-later.\n' +
      'msgid ""\nmsgstr ""\n' +
      head
        .split('\n')
        .filter(Boolean)
        .map((line) => quote(line + '\n'))
        .join('\n') +
      '\n',
  ];
  for (const [id, entry] of strings) {
    let block = '';
    for (const comment of entry.comments) block += `#. ${comment}\n`;
    block += `#: ${entry.refs.join(' ')}\n`;
    block += `msgid ${quote(id)}\nmsgstr ${quote(translate(id))}\n`;
    blocks.push(block);
  }
  writeFileSync(path, blocks.join('\n'));
}

/** The binary format read by gettext and WordPress: a header, two tables of (length, offset), then the strings. */
function compileMo(pairs) {
  const entries = [...pairs]
    .map(([id, str]) => [Buffer.from(id, 'utf8'), Buffer.from(str, 'utf8')])
    .sort((a, b) => Buffer.compare(a[0], b[0]));
  const count = entries.length;
  const tables = 28 + count * 16;
  const head = Buffer.alloc(tables);
  head.writeUInt32LE(0x950412de, 0);
  head.writeUInt32LE(0, 4);
  head.writeUInt32LE(count, 8);
  head.writeUInt32LE(28, 12);
  head.writeUInt32LE(28 + count * 8, 16);
  head.writeUInt32LE(0, 20);
  head.writeUInt32LE(tables, 24);
  const chunks = [];
  let offset = tables;
  const place = (buffer, at) => {
    head.writeUInt32LE(buffer.length, at);
    head.writeUInt32LE(offset, at + 4);
    chunks.push(buffer, Buffer.from([0]));
    offset += buffer.length + 1;
  };
  entries.forEach(([id], index) => place(id, 28 + index * 8));
  entries.forEach(([, str], index) => place(str, 28 + count * 8 + index * 8));
  return Buffer.concat([head, ...chunks]);
}

const placeholders = (text) => (text.match(/%(?:\d+\$)?[ds]|\{[a-z_]+\}|https:\/\/|#[0-9a-f]{6}/g) || []).sort().join(' ');

const strings = collect();
writePo(join(langDir, `${DOMAIN}.pot`), strings, () => '', header(''));
console.log(`i18n: ${strings.size} strings -> languages/${DOMAIN}.pot`);

let failed = false;
for (const file of readdirSync(langDir).filter((name) => name.endsWith('.po'))) {
  const locale = file.slice(DOMAIN.length + 1, -3);
  const path = join(langDir, file);
  const existing = parsePo(readFileSync(path, 'utf8'));
  const problems = [];
  for (const id of strings.keys()) {
    const str = existing.get(id);
    if (!str) problems.push(`not translated: ${id}`);
    else if (placeholders(str) !== placeholders(id)) problems.push(`placeholders differ: ${id}\n      -> ${str}`);
  }
  for (const id of existing.keys()) {
    if (id && !strings.has(id)) problems.push(`no longer used: ${id}`);
  }
  if (problems.length) {
    failed = true;
    console.error(`i18n: ${file}\n  - ` + problems.join('\n  - '));
    continue;
  }
  writePo(path, strings, (id) => existing.get(id), header(locale));
  const pairs = [['', header(locale)], ...[...strings.keys()].map((id) => [id, existing.get(id)])];
  writeFileSync(join(langDir, `${DOMAIN}-${locale}.mo`), compileMo(pairs));
  console.log(`i18n: ${file} -> ${DOMAIN}-${locale}.mo (${strings.size} strings)`);
}
process.exit(failed ? 1 : 0);
