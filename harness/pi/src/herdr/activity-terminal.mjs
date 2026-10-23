import { writeFile, rename, unlink } from 'node:fs/promises';
import { readBinding, serveLocal } from './context.mjs';
import { HerdrActivity } from './activity.mjs';
import { fit, wrap } from '../workspace/presentation.mjs';

const [receipt, token, bindingFile] = process.argv.slice(2);
if (!receipt || !token || !bindingFile || process.env.HERDR_ENV !== '1' || !process.env.HERDR_PANE_ID || !process.stdin.isTTY || !process.stdout.isTTY) process.exit(1);
let activity, server, timer, closing = false, blocked = false, dirty = false, scheduled = false, scroll = 0;
const draw = () => {
  if (closing) return;
  if (blocked) { dirty = true; return; }
  dirty = false;
  const width = Math.max(1, (process.stdout.columns || 40) - 1), height = Math.max(1, process.stdout.rows || 20);
  const lines = activity.view().split('\n');
  // Keep selected identity and truthful lifetime at the top while browsing bounded details/history.
  const header = lines.slice(0, activity.details ? 2 : 4).map(line => fit(line, width));
  const controls = fit(width < 45 ? '[ ] f p c r · i help · q exit' : '[ ] select f pin p pause c close r retry i info q quit', width);
  const body = lines.slice(header.length).flatMap(line => wrap(line, width));
  const space = Math.max(0, height - header.length - 1);
  scroll = Math.max(0, Math.min(scroll, Math.max(0, body.length - space)));
  const end = body.length - scroll;
  const frame = [...header, controls, ...body.slice(Math.max(0, end - space), end)].slice(0, height);
  blocked = !process.stdout.write('\x1b[H\x1b[2J' + frame.join('\r\n'));
};
const changed = () => {
  if (scheduled || closing) return;
  scheduled = true; setImmediate(() => { scheduled = false; draw(); });
};
const close = async () => {
  if (closing) return; closing = true;
  clearInterval(timer); activity?.close(); server?.close();
  process.stdin.setRawMode(false);
  process.stdout.write('\x1b[?25h\x1b[?1049l');
  await unlink(receipt).catch(() => {});
  process.exit(0);
};
try {
  const binding = readBinding(bindingFile);
  activity = new HerdrActivity(binding, changed);
  const identity = { token, pid: process.pid, paneId: process.env.HERDR_PANE_ID };
  server = await serveLocal(async query => {
    if (query === token) return identity;
    if (query?.token === token && query.operation === 'selection' && query.generation === binding.generation) return activity.selection();
    throw Error('Unsupported query');
  });
  await writeFile(`${receipt}.tmp`, JSON.stringify({ ...identity, port: server.port, generation: binding.generation, bindingFile }) + '\n', { mode: 0o600 });
  await rename(`${receipt}.tmp`, receipt);
  process.stdout.write('\x1b[?1049h\x1b[?25l');
  process.stdin.setRawMode(true); process.stdin.resume();
  process.stdin.on('data', data => {
    const key = data.toString();
    if (key === 'q' || key === '\x03') { void close(); return; }
    if (key === '\x1b[5~') { scroll += 5; changed(); return; }
    if (key === '\x1b[6~') { scroll -= 5; changed(); return; }
    const action = { '[': 'previous', ']': 'next', f: 'pin', p: 'pause', c: 'toggle', r: 'reconnect', i: 'details' }[key];
    if (action) { scroll = 0; activity.action(action); }
    // Literal/pasted input is never submitted to Pi or a child; only exact local keys are accepted.
  });
  process.stdout.on('resize', changed);
  process.stdout.on('drain', () => { blocked = false; if (dirty) changed(); });
  process.on('SIGINT', close); process.on('SIGTERM', close); process.on('SIGHUP', close);
  void activity.tick(); timer = setInterval(() => { void activity.tick(); }, 1000);
  draw();
} catch { await close(); }
