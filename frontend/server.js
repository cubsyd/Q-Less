import { createReadStream, existsSync, statSync } from 'node:fs';
import { extname, join, normalize, resolve } from 'node:path';
import { createServer } from 'node:http';

const port = process.env.PORT || 8080;
const publicDir = resolve('dist/frontend/browser');

const mimeTypes = {
  '.css': 'text/css',
  '.html': 'text/html',
  '.ico': 'image/x-icon',
  '.js': 'text/javascript',
  '.json': 'application/json',
  '.map': 'application/json',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.txt': 'text/plain',
  '.webp': 'image/webp',
};

function resolveAsset(urlPath) {
  const cleanPath = normalize(decodeURIComponent(urlPath.split('?')[0])).replace(/^(\.\.[/\\])+/, '');
  const requestedPath = resolve(join(publicDir, cleanPath));

  if (requestedPath.startsWith(publicDir) && existsSync(requestedPath) && statSync(requestedPath).isFile()) {
    return requestedPath;
  }

  return join(publicDir, 'index.html');
}

createServer((request, response) => {
  const filePath = resolveAsset(request.url || '/');
  const extension = extname(filePath);

  response.setHeader('Content-Type', mimeTypes[extension] || 'application/octet-stream');
  createReadStream(filePath).pipe(response);
}).listen(port, () => {
  console.log(`Frontend server listening on port ${port}`);
});
