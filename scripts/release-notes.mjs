// Prints the section of CHANGELOG.md for one version, as the text of a release.
//
//   node scripts/release-notes.mjs 1.2.3 > notes.md
//
// Fails when the changelog has no section for that version, or when the section is empty.
import { changelogSections, isVersion, read } from './version-files.mjs';

const version = (process.argv[2] || '').replace(/^v/, '');
if (!isVersion(version)) {
  console.error('Usage: node scripts/release-notes.mjs <version>');
  process.exit(2);
}
const changelog = read('CHANGELOG.md');
const section = changelog === undefined ? undefined : changelogSections(changelog).find((item) => item.name === version);
if (!section || !section.body) {
  console.error(`release-notes: CHANGELOG.md has no content for ${version}.`);
  process.exit(1);
}
process.stdout.write(section.body + '\n');
