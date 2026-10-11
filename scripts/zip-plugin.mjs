// Packs the WordPress plugin into dist-plugin/askai-button-<version>.zip. No dependencies.
//
//   npm run build && node scripts/zip-plugin.mjs
//
// The archive has one top-level folder, askai-button/, with everything WordPress needs.
// The tests are left out. The built script must exist: run the build first.
import { existsSync, mkdirSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deflateRawSync } from 'node:zlib';

const SLUG = 'askai-button';
const pluginDir = fileURLToPath(new URL(`../wordpress/${SLUG}/`, import.meta.url));
const outDir = fileURLToPath(new URL('../dist-plugin/', import.meta.url));
const EXCLUDED_DIRS = ['tests'];
const REQUIRED = [
  `${SLUG}.php`,
  'uninstall.php',
  'readme.txt',
  'LICENSE',
  'assets/askai-button.min.js',
  `languages/${SLUG}.pot`,
];

function fail(message) {
  console.error(`zip-plugin: ${message}`);
  process.exit(1);
}

/** Paths relative to the plugin folder, with forward slashes, sorted. */
function listFiles(dir, prefix = '') {
  const out = [];
  for (const name of readdirSync(dir).sort()) {
    if (name.startsWith('.')) continue;
    const path = join(dir, name);
    if (statSync(path).isDirectory()) {
      if (prefix === '' && EXCLUDED_DIRS.includes(name)) continue;
      out.push(...listFiles(path, `${prefix}${name}/`));
    } else {
      out.push(prefix + name);
    }
  }
  return out;
}

const CRC_TABLE = new Uint32Array(256);
for (let n = 0; n < 256; n++) {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  CRC_TABLE[n] = c >>> 0;
}

function crc32(buffer) {
  let crc = 0xffffffff;
  for (let i = 0; i < buffer.length; i++) crc = CRC_TABLE[(crc ^ buffer[i]) & 0xff] ^ (crc >>> 8);
  return (crc ^ 0xffffffff) >>> 0;
}

/** Date in the two 16-bit fields of the ZIP format. Local time, as the format expects. */
function dosDateTime(date) {
  const year = Math.max(1980, date.getFullYear());
  return {
    time: (date.getHours() << 11) | (date.getMinutes() << 5) | (date.getSeconds() >> 1),
    date: ((year - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate(),
  };
}

/** A ZIP archive of `entries` ({ name, data, mtime }). Deflate, or stored when that is smaller. */
function zip(entries) {
  const local = [];
  const central = [];
  let offset = 0;

  for (const entry of entries) {
    const name = Buffer.from(entry.name, 'utf8');
    const deflated = deflateRawSync(entry.data, { level: 9 });
    const stored = deflated.length >= entry.data.length;
    const body = stored ? entry.data : deflated;
    const { time, date } = dosDateTime(entry.mtime);
    const crc = crc32(entry.data);

    // Fields shared by the local header and the central directory entry.
    const shared = Buffer.alloc(26);
    shared.writeUInt16LE(20, 0); // version needed: 2.0
    shared.writeUInt16LE(0x0800, 2); // flags: names are UTF-8
    shared.writeUInt16LE(stored ? 0 : 8, 4); // method
    shared.writeUInt16LE(time, 6);
    shared.writeUInt16LE(date, 8);
    shared.writeUInt32LE(crc, 10);
    shared.writeUInt32LE(body.length, 14);
    shared.writeUInt32LE(entry.data.length, 18);
    shared.writeUInt16LE(name.length, 22);
    shared.writeUInt16LE(0, 24); // extra field length

    const localHead = Buffer.alloc(4);
    localHead.writeUInt32LE(0x04034b50, 0);
    local.push(localHead, shared, name, body);

    const centralHead = Buffer.alloc(6);
    centralHead.writeUInt32LE(0x02014b50, 0);
    centralHead.writeUInt16LE(20, 4); // version made by: 2.0, attributes in MS-DOS form
    const centralTail = Buffer.alloc(14);
    centralTail.writeUInt16LE(0, 0); // comment length
    centralTail.writeUInt16LE(0, 2); // disk number
    centralTail.writeUInt16LE(0, 4); // internal attributes
    centralTail.writeUInt32LE(0, 6); // external attributes
    centralTail.writeUInt32LE(offset, 10); // offset of the local header
    central.push(centralHead, shared, centralTail, name);

    offset += 4 + shared.length + name.length + body.length;
  }

  const directory = Buffer.concat(central);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(0, 4); // this disk
  end.writeUInt16LE(0, 6); // disk with the directory
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(directory.length, 12);
  end.writeUInt32LE(offset, 16);
  end.writeUInt16LE(0, 20); // comment length
  return Buffer.concat([...local, directory, end]);
}

if (!existsSync(join(pluginDir, 'assets/askai-button.min.js'))) {
  fail('assets/askai-button.min.js is missing. Run "npm run build" first.');
}

const files = listFiles(pluginDir);
for (const required of REQUIRED) {
  if (!files.includes(required)) fail(`${required} is missing from the plugin folder.`);
}
if (!files.some((file) => /^languages\/.+\.mo$/.test(file))) {
  fail('no compiled translation (languages/*.mo). Run "npm run i18n" first.');
}

const main = readFileSync(join(pluginDir, `${SLUG}.php`), 'utf8');
const version = (/^\s*\*\s*Version:\s*(\S+)\s*$/m.exec(main) || [])[1];
if (!version || !/^[0-9]+(\.[0-9]+)*(-[0-9A-Za-z.]+)?$/.test(version)) {
  fail(`cannot read the version from ${SLUG}.php.`);
}
const stable = (/^Stable tag:\s*(\S+)\s*$/m.exec(readFileSync(join(pluginDir, 'readme.txt'), 'utf8')) || [])[1];
if (stable !== version) {
  fail(`the version of ${SLUG}.php (${version}) and the stable tag of readme.txt (${stable}) differ.`);
}

const entries = files.map((file) => {
  const path = join(pluginDir, file);
  return { name: `${SLUG}/${file}`, data: readFileSync(path), mtime: statSync(path).mtime };
});
const archive = zip(entries);
const target = join(outDir, `${SLUG}-${version}.zip`);
mkdirSync(outDir, { recursive: true });
writeFileSync(target, archive);

for (const entry of entries) console.log(`  ${entry.name} (${entry.data.length} bytes)`);
console.log(`zip-plugin: ${entries.length} files, ${archive.length} bytes -> ${target}`);
