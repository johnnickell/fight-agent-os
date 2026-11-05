// Disposable MCP stdio fixture; never a Fight server.
import { appendFileSync } from 'node:fs';
import { createInterface } from 'node:readline';
const log = process.argv[2];
appendFileSync(log, `${JSON.stringify({ event: 'start', pid: process.pid })}\n`);
const input = createInterface({ input: process.stdin });
input.on('line', (line) => {
  const request = JSON.parse(line);
  if (request.id === undefined) return;
  let result = {};
  if (request.method === 'initialize') result = {
    protocolVersion: request.params.protocolVersion,
    capabilities: { tools: {} }, serverInfo: { name: 'task-00555-stdio', version: '1' },
  };
  if (request.method === 'tools/list') result = { tools: [{
    name: 'echo', description: 'Return fixture text',
    inputSchema: { type: 'object', properties: {} },
  }] };
  if (request.method === 'tools/call') result = { content: [{ type: 'text', text: 'stdio fixture' }] };
  process.stdout.write(`${JSON.stringify({ jsonrpc: '2.0', id: request.id, result })}\n`);
});
input.on('close', () => process.exit(0));
process.on('exit', () => appendFileSync(log, `${JSON.stringify({ event: 'exit', pid: process.pid })}\n`));
