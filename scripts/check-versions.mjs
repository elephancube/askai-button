// Checks that the version number is the same everywhere it is written. Changes nothing.
//
//   node scripts/check-versions.mjs                  every place agrees with packages/core/package.json
//   node scripts/check-versions.mjs --tag v1.2.3     ... and the tag is "v" + that version, and
//                                                    CHANGELOG.md has a dated section for it
//
// The places are listed in version-files.mjs.
import { LOCATIONS, changelogSections, find, isVersion, packageName, packageNameProblems, read } from './version-files.mjs';

const args = process.argv.slice(2);
let tag;
for (let i = 0; i < args.length; i++) {
  if (args[i] === '--tag' && args[i + 1]) tag = args[++i];
  else if (args[i].startsWith('--tag=')) tag = args[i].slice(6);
  else {
    console.error('Usage: node scripts/check-versions.mjs [--tag v<version>]');
    process.exit(2);
  }
}

const problems = [];
const first = find(LOCATIONS[0]);
if (first.problem) {
  console.error(`check-versions: ${first.problem}`);
  process.exit(1);
}
const version = first.versions[0];
if (!isVersion(version)) problems.push(`${LOCATIONS[0].file}: "${version}" is not a version of the form 1.2.3 or 1.2.3-beta.1`);

for (const location of LOCATIONS) {
  const found = find(location);
  if (found.problem) {
    problems.push(found.problem);
    continue;
  }
  if (!found.versions.length) {
    console.log(`  -      ${location.file}: ${location.label} (not present)`);
    continue;
  }
  const wrong = found.versions.filter((value) => value !== version);
  console.log(`  ${wrong.length ? 'WRONG' : 'ok   '}  ${location.file}: ${location.label} = ${[...new Set(found.versions)].join(', ')}`);
  if (wrong.length) problems.push(`${location.file}: ${location.label} is ${[...new Set(wrong)].join(', ')}, expected ${version}`);
}

// The READMEs and the lock file must name the npm package exactly as its package.json does.
{
  const wrongNames = packageNameProblems();
  console.log(`  ${wrongNames.length ? 'WRONG' : 'ok   '}  npm package name "${packageName()}" in the READMEs and the lock file`);
  problems.push(...wrongNames);
}

if (tag !== undefined) {
  if (tag !== 'v' + version) problems.push(`the tag is "${tag}", but the version in the files is ${version} (expected the tag "v${version}")`);
  const changelog = read('CHANGELOG.md');
  const section = changelog === undefined ? undefined : changelogSections(changelog).find((item) => item.name === version);
  if (!section) problems.push(`CHANGELOG.md has no "## [${version}] - <date>" section. Run: node scripts/bump-version.mjs ${version}`);
  else if (!/^\d{4}-\d{2}-\d{2}$/.test(section.date || '')) problems.push(`CHANGELOG.md: the section of ${version} has no date (expected "## [${version}] - YYYY-MM-DD")`);
  else if (!section.body) problems.push(`CHANGELOG.md: the section of ${version} is empty`);
  else console.log(`  ok     CHANGELOG.md: section of ${version}, dated ${section.date}`);
}

if (problems.length) {
  for (const problem of problems) console.error(`check-versions: ${problem}`);
  console.error('check-versions: to set the version everywhere, run: node scripts/bump-version.mjs <version>');
  process.exit(1);
}
console.log(`check-versions: version ${version} everywhere${tag !== undefined ? `, tag ${tag}` : ''}`);
