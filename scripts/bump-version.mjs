// Sets the version number everywhere it is written and dates the changelog.
//
//   node scripts/bump-version.mjs 1.2.3            writes the files
//   node scripts/bump-version.mjs 1.2.3 --check    prints what would change and writes nothing
//   node scripts/bump-version.mjs 1.2.3 --date 2026-01-31    release date, default: today (local time)
//
// What it does:
//   - replaces the version at every place listed in version-files.mjs (the package, the lock file,
//     the plugin header and constant, the "Stable tag" of readme.txt, the CDN addresses in the READMEs);
//   - moves the content of "## [Unreleased]" in CHANGELOG.md under "## [1.2.3] - <date>" and leaves
//     an empty "Unreleased" section behind.
//
// The same version as the current one is accepted as long as the changelog has no section for it:
// that is how the very first release gets its date. A lower version is refused.
// It does not commit, tag or publish anything.
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { LOCATIONS, ROOT, changelogSections, compareVersions, find, isVersion, read, replace } from './version-files.mjs';

function fail(message) {
  console.error(`bump-version: ${message}`);
  process.exit(1);
}

const args = process.argv.slice(2);
let version;
let check = false;
const today = new Date();
const pad = (n) => String(n).padStart(2, '0');
let date = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
for (let i = 0; i < args.length; i++) {
  if (args[i] === '--check') check = true;
  else if (args[i] === '--date' && args[i + 1]) date = args[++i];
  else if (args[i].startsWith('--date=')) date = args[i].slice(7);
  else if (!args[i].startsWith('-') && version === undefined) version = args[i];
  else fail(`unknown argument "${args[i]}". Usage: node scripts/bump-version.mjs <version> [--check] [--date YYYY-MM-DD]`);
}
if (version === undefined) fail('no version given. Usage: node scripts/bump-version.mjs <version> [--check] [--date YYYY-MM-DD]');
if (!isVersion(version)) fail(`"${version}" is not a version of the form 1.2.3 or 1.2.3-beta.1 (no leading "v").`);
if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || Number.isNaN(Date.parse(date + 'T00:00:00Z'))) fail(`"${date}" is not a date of the form YYYY-MM-DD.`);

// --- Read everything and decide before anything is written -----------------------------------

const current = find(LOCATIONS[0]);
if (current.problem) fail(current.problem);
const currentVersion = current.versions[0];
if (!isVersion(currentVersion)) fail(`the current version "${currentVersion}" in ${LOCATIONS[0].file} is not a valid version.`);
if (compareVersions(version, currentVersion) < 0) fail(`${version} is lower than the current version ${currentVersion}.`);

/** file -> new content */
const files = new Map();
const report = [];
for (const location of LOCATIONS) {
  const found = find(location);
  if (found.problem) fail(found.problem);
  if (!found.versions.length) continue;
  const before = files.get(location.file) ?? read(location.file);
  const after = replace(location, before, version);
  const from = [...new Set(found.versions)].join(', ');
  report.push(`  ${after === before ? 'same   ' : 'change '} ${location.file}: ${location.label}: ${from}${after === before ? '' : ' -> ' + version}`);
  files.set(location.file, after);
}

const changelog = read('CHANGELOG.md');
if (changelog === undefined) fail('CHANGELOG.md does not exist.');
const sections = changelogSections(changelog);
const unreleased = sections.find((section) => section.name.toLowerCase() === 'unreleased');
if (!unreleased) fail('CHANGELOG.md has no "## [Unreleased]" section.');
if (sections.some((section) => section.name === version)) fail(`CHANGELOG.md already has a section for ${version}. A version number is used only once.`);
if (!unreleased.body) fail('the "Unreleased" section of CHANGELOG.md is empty: there is nothing to release. Describe the changes there first.');
const newer = sections.find((section) => isVersion(section.name) && compareVersions(section.name, version) > 0);
if (newer) fail(`CHANGELOG.md already has a section for ${newer.name}, which is higher than ${version}.`);
{
  const newline = changelog.includes('\r\n') ? '\r\n' : '\n';
  const lines = changelog.split('\n');
  const heading = lines[unreleased.start].replace(/\r$/, '');
  // "## [Unreleased]", an empty line, the dated heading, then the former content of "Unreleased" unchanged.
  lines.splice(unreleased.start, 1, heading, '', `## [${version}] - ${date}`);
  files.set('CHANGELOG.md', lines.map((line) => line.replace(/\r$/, '')).join(newline));
  const entries = unreleased.body.split('\n').filter((line) => /^\s*[-*] /.test(line)).length;
  report.push(`  change  CHANGELOG.md: ${entries} entries of "Unreleased" move under "## [${version}] - ${date}"`);
}

// --- Report, then write ----------------------------------------------------------------------

console.log(`bump-version: ${currentVersion} -> ${version}${check ? ' (--check: nothing is written)' : ''}`);
for (const line of report) console.log(line);

const changed = [...files].filter(([file, content]) => content !== read(file));
if (check) {
  console.log(`bump-version: files that would change (${changed.length}): ${changed.map(([file]) => file).join(', ') || 'none'}`);
  process.exit(0);
}
for (const [file, content] of changed) writeFileSync(join(ROOT, file), content);
console.log(`bump-version: files written (${changed.length}): ${changed.map(([file]) => file).join(', ')}`);
console.log(`bump-version: next: review the diff, run "npm run build" and "npm run check:versions", commit, then tag v${version}.`);
