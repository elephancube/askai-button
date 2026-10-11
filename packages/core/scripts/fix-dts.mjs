// Adds the `.js` extension to relative imports in the emitted type declarations.
//
// The sources are written for a bundler and import './types' without an extension. The package
// is an ES module, and TypeScript's `node16` / `nodenext` resolution requires the extension in
// relative imports of ES modules: without it the types of the package silently turn into `any`
// for projects using those settings. The build fails if a specifier cannot be matched to a file.
import { existsSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const typesDir = fileURLToPath(new URL('../dist/types/', import.meta.url));
const SPECIFIER = /(\b(?:from|import)\s*\(?\s*)(['"])(\.{1,2}\/[^'"]*)\2/g;

function declarationFiles(dir) {
  return readdirSync(dir)
    .sort()
    .flatMap((name) => {
      const path = join(dir, name);
      if (statSync(path).isDirectory()) return declarationFiles(path);
      return name.endsWith('.d.ts') ? [path] : [];
    });
}

let changed = 0;
for (const file of declarationFiles(typesDir)) {
  const source = readFileSync(file, 'utf8');
  const result = source.replace(SPECIFIER, (match, before, quote, specifier) => {
    if (/\.(?:js|mjs|cjs|json)$/.test(specifier)) return match;
    const target = join(dirname(file), specifier);
    let fixed;
    if (existsSync(target + '.d.ts')) fixed = specifier + '.js';
    else if (existsSync(join(target, 'index.d.ts'))) fixed = specifier.replace(/\/?$/, '/index.js');
    else {
      console.error(`fix-dts: ${file} imports "${specifier}", which has no declaration file.`);
      process.exit(1);
    }
    changed++;
    return before + quote + fixed + quote;
  });
  if (result !== source) writeFileSync(file, result);
}
console.log(`fix-dts: ${changed} relative imports given a .js extension`);
