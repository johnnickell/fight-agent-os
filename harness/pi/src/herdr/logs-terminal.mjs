import { installLogInput } from './logs-input.mjs';
import { writeFile, rename, unlink } from 'node:fs/promises';
import { readBinding, serveLocal } from './context.mjs';
import { HerdrLogs, readLogConfiguration } from './logs.mjs';
import { fit, wrap } from '../workspace/presentation.mjs';

const [receipt, token, bindingFile] = process.argv.slice(2);
if (!receipt || !token || !bindingFile || process.env.HERDR_ENV !== '1' || !process.env.HERDR_PANE_ID || !process.stdin.isTTY || !process.stdout.isTTY) process.exit(1);
let logs, server, timer, closing = false, blocked = false, dirty = false, scheduled = false, scroll = 0;
const tint = (line, code) => `\x1b[${code}m${line}\x1b[0m`;
const dimensions = () => ({ width: Math.max(1, (process.stdout.columns || 40) - 1), height: Math.max(1, process.stdout.rows || 20) });
const canChoose = () => { const { width, height } = dimensions(); return width >= 39 && height >= 10; };
const chooser = (choice, width, height) => {
  if (!canChoose()) {
    return ['Resize pane to 40x10 to choose logs', 'Enter disabled; no source authorized', 'Esc cancel · q exit'].flatMap(line => wrap(line, width)).slice(0, height);
  }
  // Keep the active ordinal and authorization controls outside the physical-row viewport.
  // Only the active source is paged: long labels/paths cannot hide it behind earlier choices.
  const header = tint(fit(`${choice.kind} ${choice.index + 1}/${choice.count}`, width), 36);
  const controls = wrap('↑/↓ move · Space mark · Enter apply · Esc cancel\nf Follow active · m Main · PgUp/PgDn details · q exit', width);
  const body = wrap(`> ${choice.label}\n${choice.selection ?? ''}\nCheckout: ${choice.worktree}\n${choice.notice ?? ''}\nExact verified stack only; cancellation keeps panes open.`, width);
  const space = height - controls.length - 1;
  scroll = Math.max(0, Math.min(scroll, Math.max(0, body.length - space)));
  return [header, ...body.slice(scroll, scroll + space), ...Array(Math.max(0, space - body.length)).fill(''), ...controls.map(line => tint(line, 36))];
};
const draw = () => {
  if (closing) return;
  if (blocked) { dirty = true; return; }
  dirty = false;
  const { width, height } = dimensions();
  const choice = logs.logs.choice();
  if (choice) {
    blocked = !process.stdout.write('\x1b[H\x1b[2J' + chooser(choice, width, height).join('\r\n'));
    return;
  }
  const { header, notices, output, empty } = logs.presentation();
  const status = notices.flatMap(line => wrap(line, width)).slice(0, Math.max(0, height - 3)).map(line => tint(line, 33));
  const controls = tint(fit(width < 39 ? 'f Follow · m Main · s · i help' : 'f Follow · m Main · s Services · i help', width), 36);
  const body = output.length ? output.flatMap(line => wrap(line, width)) : empty ? [tint(fit(empty, width), 2)] : [];
  const space = Math.max(0, height - status.length - 2);
  scroll = Math.max(0, Math.min(scroll, Math.max(0, body.length - space)));
  const end = body.length - scroll;
  const frame = [tint(fit(header, width), 36), controls, ...status, ...body.slice(Math.max(0, end - space), end)];
  blocked = !process.stdout.write('\x1b[H\x1b[2J' + frame.slice(0, height).join('\r\n'));
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
  installLogInput(process.stdin, logs, { closing: () => closing, close, canChoose,
    scroll: (delta, reset = false) => { scroll = reset ? 0 : scroll + delta; }, changed });
  process.stdout.on('resize', changed);
  process.stdout.on('drain', () => { blocked = false; if (dirty) changed(); });
  process.on('SIGINT', close); process.on('SIGTERM', close); process.on('SIGHUP', close);
  void logs.tick(); timer = setInterval(() => { void logs.tick(); }, 500);
  draw();
} catch { await close(); }
