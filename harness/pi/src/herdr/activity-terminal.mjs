import { writeFile, rename, unlink } from 'node:fs/promises';
import { installActivityInput } from './activity-input.mjs';
import { readBinding, serveLocal } from './context.mjs';
import { HerdrActivity } from './activity.mjs';
import { fit, wrap } from '../workspace/presentation.mjs';

const [receipt, token, bindingFile] = process.argv.slice(2);
if (!receipt || !token || !bindingFile || process.env.HERDR_ENV !== '1' || !process.env.HERDR_PANE_ID || !process.stdin.isTTY || !process.stdout.isTTY) process.exit(1);
let activity, server, timer, closing = false, blocked = false, dirty = false, scheduled = false, scroll = 0, selected = null;
const draw = () => {
  if (closing) return;
  if (blocked) { dirty = true; return; }
  dirty = false;
  const width = Math.max(1, (process.stdout.columns || 40) - 1), height = Math.max(1, process.stdout.rows || 20);
  const lines = activity.view().split('\n');
  // Keep selected identity and truthful lifetime at the top while browsing bounded details/history.
  const header = lines.slice(0, 3).map(line => fit(line, width));
  const controls = fit(width < 45 ? '↑↓ Enter Esc · i help · q exit' : '↑↓ choose · Enter view · Esc list · PgUp/PgDn scroll · i help · q exit', width);
  const body = activity.details || activity.help ? lines.slice(header.length).flatMap(line => wrap(line, width)) : lines.slice(header.length).map(line => {
    const wrapped = wrap(line, width);
    return wrapped.length > 1 ? fit(line, Math.max(0, width - 1)) + '…' : line;
  });
  const space = Math.max(0, height - header.length - 2);
  const choice = activity.children?.selected;
  const index = (activity.children?.orderedSources ?? []).findIndex(([key]) => key === choice) * 2;
  if (!activity.details && !activity.help && selected !== `${choice}:${index}:${width}:${height}`) {
    selected = `${choice}:${index}:${width}:${height}`;
    if (index < scroll || index + 1 >= scroll + space) scroll = Math.max(0, index - Math.max(0, space - 2));
  }
  scroll = Math.max(0, Math.min(scroll, Math.max(0, body.length - space)));
  const start = activity.details ? Math.max(0, body.length - scroll - space) : scroll;
  const visible = body.slice(start, start + space);
  const clipped = start > 0 || start + space < body.length;
  const frame = [...header, ...visible, fit(clipped ? '↕ More above/below · PgUp/PgDn' : 'Bounded activity · not a full transcript', width), controls].slice(0, height);
  // Color only renderer-owned glyphs AFTER fitting/sanitizing untrusted output.
  const colored = frame.map(line => line.replace(/^( ?[› ] )?([●✓×○])/, (_match, prefix = '', mark) => `${prefix}\x1b[${({ '●': 35, '✓': 32, '×': 31, '○': 33 })[mark]}m${mark}\x1b[0m`));
  blocked = !process.stdout.write('\x1b[H\x1b[2J' + colored.join('\r\n'));
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
    if (query === token) return { ...identity, generation: binding.generation, invalidated: activity.invalidated === true };
    if (query?.token === token && query.operation === 'selection' && query.generation === binding.generation) return activity.selection();
    throw Error('Unsupported query');
  });
  await writeFile(`${receipt}.tmp`, JSON.stringify({ ...identity, port: server.port, generation: binding.generation, bindingFile }) + '\n', { mode: 0o600 });
  await rename(`${receipt}.tmp`, receipt);
  process.stdout.write('\x1b[?1049h\x1b[?25l');
  process.stdin.setRawMode(true); process.stdin.resume();
  installActivityInput(process.stdin, {
    close: () => { void close(); },
    page: delta => { scroll += activity.details ? delta : -delta; changed(); },
    action: type => { scroll = 0; selected = null; activity.action(type); },
  });
  process.stdout.on('resize', changed);
  process.stdout.on('drain', () => { blocked = false; if (dirty) changed(); });
  process.on('SIGINT', close); process.on('SIGTERM', close); process.on('SIGHUP', close);
  void activity.tick(); timer = setInterval(() => { void activity.tick(); }, 1000);
  draw();
} catch { await close(); }
