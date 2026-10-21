import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { createBashToolDefinition, createLocalBashOperations } from '@earendil-works/pi-coding-agent';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { NativeLauncher, stageRequest, nativeExecutorAllowed } from './observation/native-launch.mjs';

/** Associate fixed provider monitor launches with actual native Pi session context */
export default function installNativeObservation(pi: ExtensionAPI) {
  const ownerPath = fileURLToPath(import.meta.url);
  let launcher: NativeLauncher | undefined;
  let session: string | undefined;
  let epoch = 0;
  const close = () => { epoch++; launcher?.close(); launcher = undefined; session = undefined; };
  const shellOptions = () => {
    const settings = pi.getSettings();
    return { shellPath: settings.shellPath, commandPrefix: settings.shellCommandPrefix };
  };
  function operations(ctx: ExtensionContext) {
    return { exec: async (command: string, cwd: string, options: Parameters<ReturnType<typeof createLocalBashOperations>['exec']>[2]) => {
      const request = stageRequest(command);
      if (request === null) return createLocalBashOperations().exec(command, cwd, options);
      // Observation never makes RPC a native Workflow launcher or weakens deterministic policy.
      const executor = pi.getAllTools().find(tool => tool.name === 'bash')?.sourceInfo;
      if (ctx.mode !== 'tui' || pi.getFlag('fight-tool-policy') !== undefined || !nativeExecutorAllowed(executor, ownerPath, pi.getActiveTools().includes('bash'))) throw new Error('fight-stage requires native Pi and the existing authorized launch route');
      if (typeof pi.getSettings !== 'function') throw new Error('Native observation requires supported effective-settings access');
      const shell = shellOptions();
      if (shell.shellPath || shell.commandPrefix) throw new Error('Native fixed launch unavailable with a configured shell override; existing executor remains unchanged');
      const actual = ctx.sessionManager.getSessionId();
      if (actual !== session || !launcher) {
        close(); session = actual;
        // Ask Pi to persist its genuine session before taking a file-lifetime witness.
        pi.appendEntry('fight-native-observation', { version: 1 });
        // Retain the actual loaded screening provider resource identity; candidate observation
        // must not load a second copy of installed flags/tools in the monitored Pi.
        const owner = pi.getAllTools().find(tool => tool.name === 'file_screening_status')?.sourceInfo?.path;
        launcher = new NativeLauncher(ctx, { harnessPackage: owner ? resolve(dirname(owner), '..') : undefined });
      }
      return launcher!.execute(request, options);
    } };
  }
  pi.on('session_start', (_event, ctx) => {
    close();
    // Do not replace the independently selected deterministic executor.
    const executor = pi.getAllTools().find(tool => tool.name === 'bash')?.sourceInfo;
    if (typeof pi.getSettings !== 'function' || pi.getFlag('fight-tool-policy') !== undefined || !nativeExecutorAllowed(executor, ownerPath, pi.getActiveTools().includes('bash'))) return;
    const base = createBashToolDefinition(ctx.cwd, shellOptions());
    pi.registerTool({ ...base, async execute(id, args, signal, update, toolCtx) {
      if (!args.command.startsWith('fight-stage ')) return createBashToolDefinition(toolCtx.cwd, shellOptions()).execute(id, args, signal, update, toolCtx);
      return createBashToolDefinition(toolCtx.cwd, { operations: operations(toolCtx) }).execute(id, args, signal, update, toolCtx);
    } });
  });
  pi.on('user_bash', (event, ctx) => {
    if (event.command.startsWith('fight-stage ') && pi.getFlag('fight-tool-policy') === undefined) return { operations: operations(ctx) };
  });
  pi.registerCommand('fight-stage', { description: 'Native-only fixed provider stage launch using JSON identity; existing Build authority required',
    handler: async (args, ctx) => {
      if (ctx.mode !== 'tui') { ctx.ui.notify('Native-only launch: nothing submitted', 'warning'); return; }
      let commandEpoch = -1;
      // A human command leaves the native editor available for concurrent authorized launches.
      // Build's bash operation still waits for the monitor and propagates its actual exit.
      const completion = operations(ctx).exec(`fight-stage ${args}`, ctx.cwd, { onData: data => {
        if (epoch === commandEpoch) ctx.ui.notify(data.toString('utf8').trim(), 'info');
      } });
      commandEpoch = epoch;
      void completion.then(result => {
        if (epoch === commandEpoch) ctx.ui.notify(`Fixed monitor exit ${result.exitCode}; not acceptance`, result.exitCode === 0 ? 'info' : 'warning');
      }).catch(() => { if (epoch === commandEpoch) ctx.ui.notify('Native fixed-monitor launch failed; inspect the owned attempt', 'warning'); });
      ctx.ui.notify('Native launch request pending; safe progress and owned attempt record its outcome', 'info');
    } });
  pi.on('session_before_switch', close); pi.on('session_before_fork', close);
  pi.on('session_shutdown', close);
}
