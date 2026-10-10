// Fails the build when the single-file bundle exceeds the size budget.
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

const LIMIT = 15 * 1024;
const file = new URL('../dist/askai-button.min.js', import.meta.url);
const size = gzipSync(readFileSync(file)).length;

console.log(`askai-button.min.js: ${(size / 1024).toFixed(2)} KB gzip (limit ${LIMIT / 1024} KB)`);
if (size > LIMIT) {
  console.error('Bundle is over the size budget.');
  process.exit(1);
}
