// Public signing-key fixture for local HTTP tests. No real Google credentials.
import http from 'node:http';
let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { input += chunk; });
process.stdin.on('end', () => {
  const jwks = JSON.parse(input);
  let requests = 0;
  const server = http.createServer((request, response) => {
    response.setHeader('Content-Type', 'application/json');
    if (request.url === '/requests') return response.end(JSON.stringify({ requests }));
    requests++;
    if (request.url === '/certs') return response.end(JSON.stringify(jwks));
    if (request.url === '/unavailable') {
      response.writeHead(503);
      return response.end(JSON.stringify({ error: 'Certificate service unavailable' }));
    }
    response.writeHead(501);
    response.end(JSON.stringify({ error: 'Endpoint not modeled' }));
  });
  server.listen(0, '127.0.0.1', () => process.stdout.write(JSON.stringify({ port: server.address().port }) + '\n'));
});
