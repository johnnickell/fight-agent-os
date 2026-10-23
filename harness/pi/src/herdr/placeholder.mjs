import net from 'node:net';
import { writeFile, rename, unlink } from 'node:fs/promises';

// A display-only companion: no tools, provider, Docker, observer or execution API.
const [receipt, token, index] = process.argv.slice(2);
if (!receipt || !token || !['0', '1'].includes(index) || process.env.HERDR_ENV !== '1' || !process.env.HERDR_PANE_ID || !process.stdin.isTTY || !process.stdout.isTTY) process.exit(1);
const title = index === '0' ? 'Child activity' : 'Runtime logs';
const next = index === '0' ? 'TASK-00511' : 'TASK-00512';
let closing = false;
const server = net.createServer((socket) => {
  let input = '';
  socket.setTimeout(300, () => socket.destroy());
  socket.on('error', () => {});
  socket.on('data', (chunk) => {
    input += chunk;
    if (input.length > 128) return socket.destroy();
    if (input === `${token}\n`) socket.end(JSON.stringify({ token, pid: process.pid, paneId: process.env.HERDR_PANE_ID }) + '\n');
  });
});
server.maxConnections = 4;
const draw = () => {
  const width = Math.max(1, process.stdout.columns || 40);
  const lines = [title, 'PLACEHOLDER', 'Not observing', `After ${next}`, 'No agents/log readers.', '', 'Herdr: focus/resize/zoom', 'q / Ctrl+C: exit display'];
  const height = Math.max(1, process.stdout.rows || 20);
  process.stdout.write('\x1b[H\x1b[2J' + lines.slice(0, height).map((line) => line.slice(0, width - 1)).join('\r\n'));
};
const close = async () => {
  if (closing) return;
  closing = true;
  process.stdin.setRawMode(false);
  process.stdout.write('\x1b[?25h\x1b[?1049l');
  server.close();
  await unlink(receipt).catch(() => {});
  process.exit(0);
};
try {
  await new Promise((done, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', done); });
  const data = { token, pid: process.pid, paneId: process.env.HERDR_PANE_ID, port: server.address().port };
  await writeFile(`${receipt}.tmp`, JSON.stringify(data) + '\n', { mode: 0o600 });
  await rename(`${receipt}.tmp`, receipt);
  process.stdout.write('\x1b[?1049h\x1b[?25l');
  process.stdin.setRawMode(true);
  process.stdin.resume();
  process.stdin.on('data', (data) => { if (data.includes(3) || data.includes(113)) void close(); });
  process.stdout.on('resize', draw);
  process.on('SIGINT', close);
  process.on('SIGTERM', close);
  process.on('SIGHUP', close);
  draw();
} catch {
  await close();
}
