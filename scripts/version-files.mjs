// Every place where the version number of the project is written, and how to read and replace it.
// Used by check-versions.mjs and bump-version.mjs, so that the two cannot disagree.
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = fileURLToPath(new URL('../', import.meta.url));

// https://semver.org/ without build metadata, which npm ignores.
const SEMVER = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*))*))?$/;
const VERSION = String.raw`\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?`;

export const isVersion = (text) => SEMVER.test(text);

/** Negative, zero or positive, like a sort callback. Follows the precedence rules of semver. */
export function compareVersions(a, b) {
  const [, a1, a2, a3, aPre] = SEMVER.exec(a);
  const [, b1, b2, b3, bPre] = SEMVER.exec(b);
  for (const [x, y] of [[a1, b1], [a2, b2], [a3, b3]]) {
    if (Number(x) !== Number(y)) return Number(x) - Number(y);
  }
  if (aPre === bPre) return 0;
  if (!aPre) return 1;
  if (!bPre) return -1;
  const as = aPre.split('.');
  const bs = bPre.split('.');
  for (let i = 0; i < Math.max(as.length, bs.length); i++) {
    if (as[i] === undefined) return -1;
    if (bs[i] === undefined) return 1;
    if (as[i] === bs[i]) continue;
    const an = /^\d+$/.test(as[i]);
    const bn = /^\d+$/.test(bs[i]);
    if (an && bn) return Number(as[i]) - Number(bs[i]);
    if (an !== bn) return an ? -1 : 1;
    return as[i] < bs[i] ? -1 : 1;
  }
  return 0;
}

/**
 * One entry per place. `pattern` has three groups: text before, the version, text after.
 * With `all`, every match in the file counts; `optional` places may be absent.
 */
export const LOCATIONS = [
  {
    file: 'packages/core/package.json',
    label: 'package version',
    pattern: new RegExp(String.raw`(^\{\s*"name": "askai-button",\s*"version": ")(${VERSION})(")`),
  },
  {
    file: 'package-lock.json',
    label: 'version of packages/core in the lock file',
    pattern: new RegExp(String.raw`("packages/core": \{\s*"name": "askai-button",\s*"version": ")(${VERSION})(")`),
  },
  {
    file: 'wordpress/askai-button/askai-button.php',
    label: 'plugin header "Version:"',
    pattern: new RegExp(String.raw`(^[ \t]*\*[ \t]*Version:[ \t]*)(${VERSION})([ \t]*$)`, 'm'),
  },
  {
    file: 'wordpress/askai-button/askai-button.php',
    label: 'ASKAI_BUTTON_VERSION constant',
    pattern: new RegExp(String.raw`(define\(\s*'ASKAI_BUTTON_VERSION',\s*')(${VERSION})(')`),
    optional: true,
  },
  {
    file: 'wordpress/askai-button/readme.txt',
    label: 'readme.txt "Stable tag:"',
    pattern: new RegExp(String.raw`(^Stable tag:[ \t]*)(${VERSION})([ \t]*$)`, 'm'),
  },
  ...['README.md', 'README.ja.md', 'packages/core/README.md'].map((file) => ({
    file,
    label: 'version in the CDN addresses',
    pattern: new RegExp(String.raw`((?:cdn\.jsdelivr\.net/npm|unpkg\.com)/askai-button@)(${VERSION})(/)`, 'g'),
    all: true,
    optional: true,
  })),
];

export function read(file, root = ROOT) {
  const path = join(root, file);
  return existsSync(path) ? readFileSync(path, 'utf8') : undefined;
}

/**
 * The versions found at one location.
 * Returns `{ versions: string[] }`, or `{ problem: string }` when a required place is missing.
 */
export function find(location, root = ROOT) {
  const text = read(location.file, root);
  if (text === undefined) return { problem: `${location.file} does not exist` };
  const flags = location.pattern.flags.includes('g') ? location.pattern.flags : location.pattern.flags + 'g';
  const versions = [...text.matchAll(new RegExp(location.pattern.source, flags))].map((match) => match[2]);
  if (!versions.length && !location.optional) {
    return { problem: `${location.file}: ${location.label} not found` };
  }
  return { versions: location.all ? versions : versions.slice(0, 1) };
}

/** The text of a file with the version at one location replaced. */
export function replace(location, text, version) {
  const flags = location.all ? location.pattern.flags : location.pattern.flags.replace('g', '');
  return text.replace(new RegExp(location.pattern.source, flags), (match, before, old, after) => before + version + after);
}

// --- CHANGELOG.md ----------------------------------------------------------------------------

const HEADING = /^## \[([^\]]+)\](?:[ \t]*-[ \t]*(\S+))?[ \t]*$/;

/** Sections of the changelog: `{ name, date, start, end, body }`, line numbers of the `##` blocks. */
export function changelogSections(text) {
  const lines = text.split('\n');
  const sections = [];
  lines.forEach((line, index) => {
    const match = HEADING.exec(line.replace(/\r$/, ''));
    if (match) sections.push({ name: match[1], date: match[2], start: index });
  });
  sections.forEach((section, i) => {
    section.end = i + 1 < sections.length ? sections[i + 1].start : lines.length;
    section.body = lines
      .slice(section.start + 1, section.end)
      .join('\n')
      .replace(/\r/g, '')
      .trim();
  });
  return sections;
}
