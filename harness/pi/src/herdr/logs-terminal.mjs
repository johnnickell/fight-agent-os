import { emitKeypressEvents } from 'node:readline';
import { writeFile, rename, unlink } from 'node:fs/promises';
import { readBinding, serveLocal } from './context.mjs';
import { HerdrLogs, readLogConfiguration } from './logs.mjs';
import { fit, wrap } from '../workspace/presentation.mjs';

const [receipt, token, bindingFile] = process.argv.slice(2);
if (!receipt || !token || !bindingFile || process.env.HERDR_ENV !== '1' || !process.env.HERDR_PANE_ID || !process.stdin.isTTY || !process.stdout.isTTY) process.exit(1);
let logs, server, timer, closing = false, blocked = false, dirty = false, scheduled = false, scroll = 0;
const dimensions = () => ({ width: Math.max(1, (process.stdout.columns || 40) - 1), height: Math.max(1, process.stdout.rows || 20) });
const canChoose = () => { const { width, height } = dimensions(); return width >= 39 && height >= 10; };
const chooser = (choice, width, height) => {
  if (!canChoose()) {
    return ['Resize pane to 40x10 to choose logs', 'Enter disabled; no source authorized', 'Esc cancel · q exit'].flatMap(line => wrap(line, width)).slice(0, height);
  }
  // Keep the active ordinal and authorization controls outside the physical-row viewport.
  // Only the active source is paged: long labels/paths cannot hide it behind earlier choices.
  const header = fit(`Choose logs ${choice.index + 1}/${choice.count} — verified local`, width);
  const controls = wrap('↑/↓ source · Enter choose · Esc cancel\nPgUp/PgDn details · q exit', width);
  const body = wrap(`> ${choice.label}\nCheckout: ${choice.worktree}\nExact checkout only · no shared fallback\nCancellation keeps the workspace open.`, width);
  const space = height - controls.length - 1;
  scroll = Math.max(0, Math.min(scroll, Math.max(0, body.length - space)));
  return [header, ...body.slice(scroll, scroll + space), ...Array(Math.max(0, space - body.length)).fill(''), ...controls];
};
const draw = () => {
  if (closing) return;
  if (blocked) { dirty = true; return; }
  dirty = false;
  const { width, height } = dimensions();
  const choice = logs.discovery ? logs.logs.choice() : null;
  if (choice) {
    blocked = !process.stdout.write('\x1b[H\x1b[2J' + chooser(choice, width, height).join('\r\n'));
    return;
  }
  const lines = logs.view().split('\n');
  const header = lines.slice(0, 6).map(line => fit(line, width));
  const controls = fit(logs.discovery ? (width < 45 ? 's choose · r retry · i info · q exit' : 's choose source · r retry · p pause · c reader · i info · q exit') : (width < 45 ? 'p c r i · [ ] service · { } stack · q' : 'p pause c reader r retry i info [ ] service { } stack m shared q quit'), width);
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
    if (query === token) return { ...identity, generation: binding.generation, invalidated: logs.invalidated === true, readerPids: logs.readerPids(), metadataPids: logs.metadataPids() };
    throw Error('Unsupported query');
  });
  await writeFile(`${receipt}.tmp`, JSON.stringify({ ...identity, port: server.port }) + '\n', { mode: 0o600 });
  await rename(`${receipt}.tmp`, receipt);
  process.stdout.write('\x1b[?1049h\x1b[?25l');
  process.stdin.setRawMode(true); process.stdin.resume();
  // Decode terminal sequences across chunks, including coalesced arrow + Enter.
  // Serialize at most 16 display actions so a quick choice is not lost during refresh.
  let actions = Promise.resolve(), queued = 0;
  emitKeypressEvents(process.stdin);
  process.stdin.on('keypress', (text, key) => {
    if (key.name === 'q' || key.ctrl && key.name === 'c') { void close(); return; }
    if (key.name === 'pageup' || key.name === 'pagedown') {
      const delta = key.name === 'pageup' ? 5 : -5;
      scroll += logs.logs.menu ? -delta : delta; changed(); return;
    }
    const action = { up: 'up', down: 'down', return: 'choose', enter: 'choose', escape: 'cancel' }[key.name] ??
      { s: 'select', '[': 'previous', ']': 'next', '{': 'stack-previous', '}': 'stack-next', m: 'mode', p: 'pause', c: 'toggle', r: 'reconnect', i: 'info' }[text];
    if (!action || queued >= 16 || closing) return;
    queued++; scroll = 0;
    actions = actions.then(() => closing || action === 'choose' && logs.logs.menu && !canChoose() ? undefined : logs.action(action)).catch(() => {
      if (!closing) logs.hold('UNAVAILABLE — reader action failed; reopen from native Pi');
    }).finally(() => { queued--; });
  });
  process.stdout.on('resize', changed);
  process.stdout.on('drain', () => { blocked = false; if (dirty) changed(); });
  process.on('SIGINT', close); process.on('SIGTERM', close); process.on('SIGHUP', close);
  void logs.tick(); timer = setInterval(() => { void logs.tick(); }, 500);
  draw();
} catch { await close(); }
