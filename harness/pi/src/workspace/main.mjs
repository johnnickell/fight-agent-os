import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { Workspace, clean } from './presentation.mjs';
import { PiRpc, messageText } from './rpc.mjs';
import { Terminal } from './terminal.mjs';

// Optional evaluator readiness does not own input authority. Older providers can prove only
// the two unambiguous healthy states; degraded providers must report independent access health.
function permitsInput(status) {
  if (!status || typeof status !== 'object') return false;
  if (status.access !== undefined && (status.access?.state !== 'available' || status.access.reason !== null)) return false;
  if (status.state === 'disabled') return status.enforcement === 'not-enrolled' && status.reason === 'not-enrolled';
  if (status.state === 'active') return status.enforcement === 'project-access-with-optional-screening' && status.reason === null;
  return status.state === 'degraded' && status.enforcement === 'project-access-with-screening-degraded' &&
    status.access?.state === 'available' && typeof status.reason === 'string' && status.reason.length > 0;
}

export function options(argv, cwd) {
  const args = [], values = new Set(['--provider', '--model', '--thinking', '--session', '--session-dir', '--name', '--extension']);
  let theme = 'system';
  for (let index = 0; index < argv.length; index++) {
    const option = argv[index];
    if (option === '--theme') {
      theme = argv[++index]; if (!['system', 'dark', 'light'].includes(theme)) throw new Error('Use --theme system|dark|light');
    } else if (values.has(option)) {
      const value = argv[++index]; if (!value || value.startsWith('-')) throw new Error(`Missing value for ${option}`);
      args.push(option, value);
    } else if (['--continue', '--no-session'].includes(option)) args.push(option);
    else throw new Error(`Unsupported workspace option: ${clean(option)} (see harness/pi/WORKSPACE.md)`);
  }
  if (!args.includes('--no-session') && !args.includes('--session-dir')) args.push('--session-dir', resolve(cwd, 'var/terminal/sessions'));
  return { args, theme };
}
export async function run(argv = process.argv.slice(2), {
  input = process.stdin, output = process.stdout,
  connect = options => new PiRpc(options), createTerminal = (input, output) => new Terminal(input, output),
} = {}) {
  if (argv.includes('--help')) {
    console.log('Fight workspace: ./bin/terminal [--theme system|dark|light] [--provider NAME --model ID --thinking LEVEL] [--session PATH|--continue|--no-session] [--session-dir PATH] [--extension PATH]\nTab focus/select views · PgUp/PgDn scroll · Ctrl+W split · Ctrl+T theme · Ctrl+J newline · Esc abort · Ctrl+Q exit\nPolicy enrollment/role changes and native-only Pi commands require the normal Pi TUI. See harness/pi/WORKSPACE.md.'); return;
  }
  const cwd = process.cwd(), config = options(argv, cwd);
  if (!input.isTTY || !output.isTTY) throw new Error('Workspace needs a real terminal');
  if (!config.args.includes('--no-session')) mkdirSync(config.args[config.args.indexOf('--session-dir') + 1], { recursive: true, mode: 0o700 });
  const model = new Workspace(cwd); model.theme = config.theme;
  const terminal = createTerminal(input, output);
  const rpc = connect({ cwd, args: config.args });
  let closing = false, fault = false, renderTimer, startupTimer, sessionFile, policyReady = false, stateSeen = false, commandsSeen = false;
  const dialogTimers = new Map();
  const render = () => {
    if (closing || renderTimer) return;
    renderTimer = setTimeout(() => {
      renderTimer = undefined;
      try { terminal.draw(model.render(output.columns, output.rows, Math.max(1, output.columns - 1)), process.env.NO_COLOR ? 'system' : model.theme); }
      catch { void close(1); }
    }, 25);
  };
  const close = async (code = 0) => {
    if (closing) return; closing = true; model.ready = false;
    clearTimeout(renderTimer); clearTimeout(startupTimer);
    for (const timer of dialogTimers.values()) clearTimeout(timer);
    output.removeListener('resize', render);
    for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) process.removeListener(signal, onSignal);
    try { terminal.stop(); } catch { code = 1; } finally { await rpc.close(); process.exitCode = code; }
  };
  const fail = message => {
    fault = true; model.ready = false; model.status = message;
    model.append(message); render();
  };
  const updateReady = () => {
    model.ready = !fault && policyReady && stateSeen && commandsSeen;
    if (model.ready) { clearTimeout(startupTimer); model.status = `Conversation: ${cwd} · Pi owns session storage; observation contexts: none`; }
    render();
  };
  const sendAction = async action => {
    if (!action || closing) return;
    try {
      if (action.type === 'quit') { await close(); return; }
      if (action.type === 'dialog') {
        clearTimeout(dialogTimers.get(action.id)); dialogTimers.delete(action.id);
        await rpc.send({ ...action, type: 'extension_ui_response' });
      } else if (action.type === 'abort') {
        // Preserve the native clear-queue then abort semantics; don't silently continue queued work.
        const queue = await rpc.request('clear_queue');
        await rpc.request('abort');
        await rpc.request('abort_bash');
        const restored = [...(queue?.steering ?? []), ...(queue?.followUp ?? [])].join('\n');
        if (restored) model.insert(restored);
      } else if (action.type === 'submit') {
        const message = action.message;
        if (message === '/quit' || message === '/exit') { await close(); return; }
        if (message.startsWith('!')) {
          const excluded = message.startsWith('!!');
          model.append(`Shell input (${excluded ? 'excluded from' : 'included in'} model context)\n${message}`);
          const result = await rpc.request('bash', { command: message.slice(excluded ? 2 : 1).trim(), excludeFromContext: excluded }, 0x7fffffff);
          model.append(`Shell exit: ${result.exitCode ?? 'unknown'}${result.cancelled ? ' (cancelled)' : ''}\n${result.output ?? ''}`);
        } else {
          const command = message.match(/^\/(\S+)/)?.[1];
          if (command?.startsWith('skill:') || command === 'file-screening') {
            model.append('Workflow selector not submitted: RPC cannot select role, workflow, task, checkout or query/status mode. Current binding is unchanged.');
            model.append(sessionFile ? `Use native Pi in the same session: exit (^Q), run pi --session ${JSON.stringify(sessionFile)} from ${cwd}, select the workflow and inspect /file-screening to verify its binding. Exit native Pi, then resume ./bin/terminal --session ${JSON.stringify(sessionFile)}. Resume alone never selects authority.` : 'Use native Pi for workflow selection and verify /file-screening. This in-memory session has no same session resume path; do not assume a new session inherits its binding.');
            render(); return;
          }
          if (command && !model.commands.has(command)) {
            model.append('Unsupported/native-only Pi command. Use the normal Pi TUI; nothing was submitted.'); return;
          }
          // RPC input cannot become interactive operator authority. The Harness retains its own role boundaries.
          await rpc.request('prompt', { message, ...(model.busy ? { streamingBehavior: 'steer' } : {}) });
        }
      }
    } catch (error) { model.append(`Pi request failed: ${clean(error.message)}`); }
    render();
  };
  const onSignal = () => { void close(130); };
  rpc.on('failure', error => { fail(`Pi transport failure: ${clean(error.message)}`); void close(1); });
  rpc.on('exit', code => { if (!closing) { fail(`Pi exited (${code ?? 'signal'})`); void close(code || 1); } });
  rpc.on('policy', status => {
    policyReady = permitsInput(status); model.policy = String(status?.state ?? 'unknown');
    model.append(`Harness: ${model.policy} · role: ${status?.phase ?? 'unknown'} · ${status?.reason ?? status?.enforcement ?? 'invalid status'}`);
    if (!policyReady) model.status = 'Input held: Harness access is unavailable. Inspect the status in native Pi.';
    updateReady();
  });
  rpc.on('record', event => {
    if (event.type === 'extension_ui_request') {
      if (['select', 'confirm', 'input', 'editor'].includes(event.method)) {
        if (model.dialogs.length >= 8) { void rpc.send({ type: 'extension_ui_response', id: event.id, cancelled: true }).catch(() => {}); return; }
        model.dialogs.push({ ...event, scroll: 0 });
        if (event.timeout) dialogTimers.set(event.id, setTimeout(() => {
          model.dialogs = model.dialogs.filter(dialog => dialog.id !== event.id); dialogTimers.delete(event.id); render();
        }, event.timeout));
      } else if (event.method === 'notify') model.append(`Pi ${event.notifyType ?? 'info'}: ${event.message}`);
      else if (event.method === 'set_editor_text') { model.draft = ''; model.cursor = 0; model.insert(event.text ?? ''); }
      else if (event.method === 'setStatus') model.append(`Pi status ${event.statusKey}: ${event.statusText ?? '(cleared)'}`);
      else if (event.method === 'setWidget' && event.widgetLines) model.append(event.widgetLines.join('\n'));
    } else if (event.type === 'agent_start') model.busy = true;
    else if (event.type === 'agent_settled') { model.busy = false; model.live = ''; model.liveOmitted = false; }
    else if (event.type === 'message_update') {
      const delta = event.assistantMessageEvent;
      if (delta.type === 'text_delta') model.stream(delta.delta);
    } else if (event.type === 'message_end') {
      const text = messageText(event.message); if (text) model.append(text); model.live = ''; model.liveOmitted = false;
    } else if (event.type === 'tool_execution_start') model.append(`Tool running: ${event.toolName}`);
    else if (event.type === 'bash_execution_update') model.append(`Shell output (${event.id ?? 'Pi'}):\n${event.delta}`);
    else if (event.type === 'extension_error') fail('Pi extension handler failed; input held. Restart after diagnosis in native Pi.');
    else if (['auto_retry_start', 'auto_retry_end', 'compaction_start', 'compaction_end'].includes(event.type)) model.append(`Pi lifecycle: ${event.type}`);
    render();
  });
  try {
    terminal.start((text, key) => { void sendAction(model.key(text, key)); render(); }, (text, retention = {}) => {
      if (model.dialogs.length) { model.append('Paste ignored during Pi interaction; type an explicit response.'); }
      else if (model.focus === 0 && model.ready) { model.insert(text); if (retention.discarded) model.inputLoss(); }
      render();
    });
    output.on('resize', render);
    for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) process.on(signal, onSignal);
    startupTimer = setTimeout(() => fail('Pi qualification incomplete: healthy Harness access, get_state and get_commands required. Exit with Ctrl+Q; diagnose in normal Pi.'), 30000);
    render();
    const state = await rpc.request('get_state');
    model.identity = `${state.model?.provider ?? '?'}/${state.model?.id ?? '?'} · ${state.thinkingLevel ?? '?'} · session ${state.sessionId}`;
    model.busy = state.isStreaming; sessionFile = state.sessionFile; stateSeen = true;
    model.append(`Conversation directory: ${cwd}\nSession: ${state.sessionId}\nStorage: ${state.sessionFile ?? 'in memory (not resumable)'}\nConfigured model: ${state.model?.provider}/${state.model?.id}; actual provider identity is available only after a response.`);
    const commands = await rpc.request('get_commands');
    model.commands = new Set(commands.commands.map(command => command.name));
    commandsSeen = ['jev', 'file-screening'].every(command => model.commands.has(command));
    if (!commandsSeen) fail('Required Harness commands unavailable; input held. Use normal Pi to qualify the installation.');
    const messages = await rpc.request('get_messages');
    for (const message of messages.messages) { const text = messageText(message); if (text) model.append(text); }
    updateReady();
  } catch (error) { fail(`Startup failed: ${clean(error.message)}`); await close(1); }
  return close;
}
if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  run().catch(error => { console.error(clean(error.message)); process.exitCode = 1; });
}
