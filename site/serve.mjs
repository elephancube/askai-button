// Serves site/dist/ under the path the site was built for, so that addresses such as
// /askai-button/install/ work locally exactly as they do on the real host.
//
//   npm run site            (build, then serve)
//   node site/serve.mjs     (serve what is already built)
//
// Environment: SITE_URL and SITE_BASE as for the build, PORT (default 4173).
import { createReadStream, existsSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { dirname, extname, join, normalize, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DEFAULT_SITE_URL } from './site.config.mjs';

const dist = join(dirname(fileURLToPath(import.meta.url)), 'dist');
const withSlashes = (path) => ('/' + path + '/').replace(/\/{2,}/g, '/');
const base = withSlashes(process.env.SITE_BASE || new URL(process.env.SITE_URL || DEFAULT_SITE_URL).pathname);
const port = Number(process.env.PORT) || 4173;

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.md': 'text/markdown; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
  '.svg': 'image/svg+xml',
};

if (!existsSync(join(dist, 'index.html'))) {
  console.error('serve-site: site/dist is empty. Run "npm run build" first.');
  process.exit(1);
}

function send(response, status, file) {
  response.writeHead(status, { 'Content-Type': TYPES[extname(file)] || 'application/octet-stream', 'Cache-Control': 'no-store' });
  createReadStream(file).pipe(response);
}

createServer((request, response) => {
  let pathname;
  try {
    pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname);
  } catch {
    pathname = '';
  }
  const notFound = () => send(response, 404, join(dist, '404.html'));

  // Like the real host: nothing of this site exists outside its base path.
  if (!pathname.startsWith(base)) {
    if (pathname + '/' === base) {
      response.writeHead(301, { Location: base });
      return response.end();
    }
    return notFound();
  }
  const file = normalize(join(dist, pathname.slice(base.length)));
  if (file !== dist && !file.startsWith(dist + sep)) return notFound();

  if (existsSync(file) && statSync(file).isDirectory()) {
    if (!pathname.endsWith('/')) {
      response.writeHead(301, { Location: pathname + '/' });
      return response.end();
    }
    const index = join(file, 'index.html');
    return existsSync(index) ? send(response, 200, index) : notFound();
  }
  return existsSync(file) ? send(response, 200, file) : notFound();
}).listen(port, () => {
  console.log(`serve-site: http://localhost:${port}${base}`);
});
