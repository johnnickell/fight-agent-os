import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const migration = `fight-terminal: the repository RPC conversation frontend is retired.
Start ordinary pi in the conversation checkout. Inside Herdr, use /fight-workspace open
for child activity and human-only verified logs; /fight-workspace close closes companions only.
Outside Herdr, keep using ordinary pi. Missing Herdr prerequisites never launch an RPC editor.

Existing RPC conversation: stop its owned client normally, then from the same checkout run
  pi --session /absolute/actual-session.jsonl
Use the actual session file previously displayed by that client (normally under
var/terminal/sessions/, or its explicitly chosen --session-dir). Do not guess with --continue,
fork/clone history, copy launcher-role flags or select a workflow through injected input.
In native Pi inspect /session and /file-screening; only the operator selects workflow authority.
Ephemeral --no-session conversations cannot be resumed.

No Pi, Herdr, Docker reader or provider is started by this command. No sessions, settings,
credentials, packages or integrations are changed. See the package README.md and WORKSPACE.md
for explicit installation adoption/removal; repository source changes are not live rollout.
`;

/** Retire every former launch option without interpreting or echoing private arguments */
export function run(argv = process.argv.slice(2), { output = process.stdout, errorOutput = process.stderr } = {}) {
  const help = argv.length === 1 && (argv[0] === '--help' || argv[0] === '-h');
  (help ? output : errorOutput).write(migration);
  return help ? 0 : 1;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  process.exitCode = run();
}
