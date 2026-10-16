// Copies the built script into the WordPress plugin. The copy is a build artefact and is not committed.
import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const source = fileURLToPath(new URL('../packages/core/dist/askai-button.min.js', import.meta.url));
const target = fileURLToPath(new URL('../wordpress/elephancube-ask-ai-button/assets/askai-button.min.js', import.meta.url));

if (!existsSync(source)) {
  console.error(`copy-dist: ${source} does not exist. Build the core package first (npm run build).`);
  process.exit(1);
}

mkdirSync(dirname(target), { recursive: true });
copyFileSync(source, target);
console.log(`copy-dist: ${source} -> ${target}`);
