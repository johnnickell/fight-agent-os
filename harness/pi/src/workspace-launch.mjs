import { accessSync, closeSync, constants, openSync, readSync, realpathSync, statSync } from 'node:fs';
import { delimiter, isAbsolute, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const help = `fight-workspace — start native interactive Pi and open owned Herdr companions
Usage: fight-workspace [supported native Pi options]

Requires an unzoomed Herdr tab containing only the caller and one complete trusted
Harness package discovered from this executable's installed package directory.
Pi stays the editor/session engine. No prompt, model turn, workflow selection,
Docker binding, installation or RPC fallback is performed by this launcher.
Startup asks for explicit human log bindings; choose zero for unavailable logs.

Forwarded values: --provider --model --thinking --models --session --session-dir
  --name (-n) --use-theme --tui-mode --fight-header
Forwarded switches: --continue (-c), --resume (-r), --no-session, --offline, --verbose
--session requires an absolute existing file; inspect /session and /file-screening.
One session selector only. No prompts/@files, mode/print/export, forks, API keys,
trust overrides, resource overrides or unknown/extension options are accepted.
Values remain separate argv entries, including paths/names with spaces.

Ordinary pi is unchanged. /fight-workspace open recovers/reuses companions;
/fight-workspace close closes only proven-owned companions, not native Pi.
Missing/filtered/duplicate Harness: inspect pi list and exact paths in ordinary Pi;
keep one complete package identity matching this installed package, then restart.
See this package's README.md for explicit user-prefix install/update/remove and
fight-terminal retirement/exact-file RPC history migration. pi install alone does
not put this executable on PATH. No installation rollout occurs at launch.
`;

class LaunchUnavailable extends Error {}
const unavailable = (message) => { throw new LaunchUnavailable(message); };

/** Validate native option boundaries before starting any process or creating state */
function options(args) {
  const values = new Set(['--provider', '--model', '--thinking', '--models', '--session', '--session-dir', '--name', '--use-theme', '--tui-mode', '--fight-header']);
  const switches = new Set(['--continue', '--resume', '--no-session', '--offline', '--verbose']);
  const aliases = { '-c': '--continue', '-r': '--resume', '-n': '--name' };
  const seen = new Set();
  const forwarded = [];
  for (let i = 0; i < args.length; i++) {
    const option = aliases[args[i]] || args[i];
    if (!values.has(option) && !switches.has(option)) unavailable('Unsupported option or prompt. Use fight-workspace --help; no Pi process started');
    if (seen.has(option)) unavailable('Duplicate option; no Pi process started');
    seen.add(option);
    forwarded.push(option);
    if (values.has(option)) {
      const value = args[++i];
      if (!value || value.startsWith('-') || /[\x00-\x1f\x7f]/u.test(value)) unavailable('Missing or invalid option value; no Pi process started');
      forwarded.push(value);
      if (option === '--session') {
        if (!isAbsolute(value) || !statSync(value).isFile()) unavailable('--session requires an absolute existing session file');
        const fd = openSync(value, 'r');
        const buffer = Buffer.alloc(64 * 1024);
        let header;
        try {
          const bytes = readSync(fd, buffer);
          const newline = buffer.subarray(0, bytes).indexOf(10);
          if (newline < 0) unavailable('Session header unavailable; inspect the actual saved file in ordinary Pi');
          header = JSON.parse(buffer.subarray(0, newline).toString('utf8'));
        } finally { closeSync(fd); }
        if (header.type !== 'session' || typeof header.id !== 'string' || !header.id || typeof header.cwd !== 'string' || realpathSync(header.cwd) !== realpathSync(process.cwd())) unavailable('Session must belong to this caller directory; use the original checkout, never an implicit fork');
      }
      if (option === '--tui-mode' && !['fullscreen', 'regular'].includes(value)) unavailable('--tui-mode must be fullscreen or regular');
    }
  }
  if (['--session', '--continue', '--resume', '--no-session'].filter(option => seen.has(option)).length > 1) unavailable('Choose only one native session selector');
  if (seen.has('--no-session') && seen.has('--session-dir')) unavailable('--no-session cannot use --session-dir');
  if (seen.has('--provider') && !seen.has('--model')) unavailable('--provider requires --model');
  return forwarded;
}

try {
  const args = process.argv.slice(2);
  if (args.length === 1 && ['--help', '-h'].includes(args[0])) {
    process.stdout.write(help);
  } else {
    const forwarded = options(args);
    if (typeof process.execve !== 'function') unavailable('Node lacks process.execve; use a supported Node runtime (ordinary pi remains available)');
    if (!process.stdin.isTTY || !process.stdout.isTTY) unavailable('Requires an interactive terminal; piped input/print/RPC are unsupported. Use ordinary pi');
    if (process.env.HERDR_ENV !== '1' || ['HERDR_SOCKET_PATH', 'HERDR_WORKSPACE_ID', 'HERDR_TAB_ID', 'HERDR_PANE_ID'].some(key => !process.env[key])) unavailable('Requires complete native Herdr caller context. Outside Herdr use ordinary pi; nothing was started');
    if (!statSync(process.env.HERDR_SOCKET_PATH).isSocket()) unavailable('Herdr socket unavailable; use ordinary pi');
    const executable = (name) => {
      for (const directory of (process.env.PATH || '').split(delimiter)) {
        // Do not resolve executables from target-project relative PATH entries.
        if (!isAbsolute(directory)) continue;
        const candidate = join(directory, name);
        try { accessSync(candidate, constants.X_OK); if (statSync(candidate).isFile()) return realpathSync(candidate); } catch { /* Try next explicit PATH entry */ }
      }
      unavailable(`${name} unavailable on absolute PATH; install it explicitly, never via this launcher`);
    };
    const pi = executable('pi');
    executable('herdr');
    const startup = fileURLToPath(new URL('./workspace-startup.ts', import.meta.url));
    accessSync(startup, constants.R_OK);
    // Replace this process, retaining Herdr foreground PID, cwd, terminal and signal semantics.
    // The one-use marker is consumed by the trusted startup hook, never a prompt/session entry.
    process.execve(pi, [pi, '--extension', startup, ...forwarded], { ...process.env, FIGHT_WORKSPACE_LAUNCH_PID: String(process.pid) });
  }
} catch (error) {
  // Do not echo argv, session content or opaque upstream filesystem diagnostics.
  console.error(`fight-workspace: ${error instanceof LaunchUnavailable ? error.message : 'Session, socket or installed assets unreadable, or native exec failed; inspect the selected file/package and PATH in ordinary Pi'}. No fallback or automatic retry.`);
  process.exitCode = 1;
}
