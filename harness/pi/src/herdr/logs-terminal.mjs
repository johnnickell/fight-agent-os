import { writeFile, rename, unlink } from 'node:fs/promises';
import { readBinding, serveLocal } from './context.mjs';
import { HerdrLogs, readLogConfiguration } from './logs.mjs';
import { fit, wrap } from '../workspace/presentation.mjs';

const [receipt, token, bindingFile] = process.argv.slice(2);
if (!receipt || !token || !bindingFile || process.env.HERDR_ENV !== '1' || !process.env.HERDR_PANE_ID || !process.stdin.isTTY || !process.stdout.isTTY) process.exit(1);
let logs, server, timer, closing = false, blocked = false, dirty = false, scheduled = false, scroll = 0;
const draw = () => {
  if (closing) return;
  if (blocked) { dirty = true; return; }
  dirty = false;
  const width = Math.max(1, (process.stdout.columns || 40) - 1), height = Math.max(1, process.stdout.rows || 20);
  const lines = logs.view().split('\n');
  const header = lines.slice(0, 6).map(line => fit(line, width));
  const controls = fit(width < 45 ? 'p c r i · [ ] service · { } stack · q' : 'p pause c reader r retry i info [ ] service { } stack m shared q quit', width);
  const body = lines.slice(6).flatMap(line => wrap(line, width));
  const space = Math.max(0, height - header.length - 1);
  scroll = Math.max(0, Math.min(scroll, Math.max(0, body.length - space)));
  const end = body.length - scroll;
  blocked = !process.stdout.write('\x1b[H\x1b[2J' + [...header, controls, ...body.slice(Math.max(0, end - space), end)].slice(0, height).join('\r\n'));
};
const changed = () => {
  if (scheduled || closing) return;
  scheduled = true; setImmediate(() => { scheduled = false; draw(); });
};
const close = async () => {
  if (closing) return; closing = true;
  clearInterval(timer); server?.close();
  await logs?.close();
  process.stdin.setRawMode(false);
  process.stdout.write('\x1b[?25h\x1b[?1049l');
  await unlink(receipt).catch(() => {});
  process.exit(0);
};
try {
  const binding = readBinding(bindingFile);
  const configuration = await readLogConfiguration(binding);
  logs = new HerdrLogs(binding, configuration, changed);
  const identity = { token, pid: process.pid, paneId: process.env.HERDR_PANE_ID };
  server = await serveLocal(query => {
    // Ownership/lifetime facts only. Never serve source choices, log data or actions.
    if (query === token) return { ...identity, generation: binding.generation, invalidated: logs.invalidated === true, readerPids: logs.readerPids() };
    throw Error('Unsupported query');
  });
  await writeFile(`${receipt}.tmp`, JSON.stringify({ ...identity, port: server.port }) + '\n', { mode: 0o600 });
  await rename(`${receipt}.tmp`, receipt);
  process.stdout.write('\x1b[?1049h\x1b[?25l');
  process.stdin.setRawMode(true); process.stdin.resume();
  process.stdin.on('data', data => {
    const key = data.toString();
    if (key === 'q' || key === '\x03') { void close(); return; }
    if (key === '\x1b[5~') { scroll += 5; changed(); return; }
    if (key === '\x1b[6~') { scroll -= 5; changed(); return; }
    const action = { '[': 'previous', ']': 'next', '{': 'stack-previous', '}': 'stack-next', m: 'mode', p: 'pause', c: 'toggle', r: 'reconnect', i: 'info' }[key];
    if (action) { scroll = 0; void logs.action(action).catch(() => { logs.hold('UNAVAILABLE — reader action failed'); }); }
  });
  process.stdout.on('resize', changed);
  process.stdout.on('drain', () => { blocked = false; if (dirty) changed(); });
  process.on('SIGINT', close); process.on('SIGTERM', close); process.on('SIGHUP', close);
  void logs.tick(); timer = setInterval(() => { void logs.tick(); }, 500);
  draw();
} catch { await close(); }
