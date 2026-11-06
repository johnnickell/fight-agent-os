import { basename } from 'node:path';
import { clean } from '../workspace/presentation.mjs';
import { stageRole } from '../stage-roles.mjs';

// Presentation recognition only. Never supplies membership, execution or workflow authority.
export function stageInvocation(command) {
  if (typeof command !== 'string' || command.length > 16384 || /[\r\n]/.test(command)) return null;
  // Deliberately accept only simple argv launches (optionally after cd). Complex shell stays native.
  const words = command.match(/'[^']*'|"[^"$`\\]*"|&&|[^\s'";&|<>$`\\]+/g);
  if (!words || words.join('').replace(/\s/g, '') !== command.replace(/\s/g, '')) return null;
  const argv = words.map(word => /^['"]/.test(word) ? word.slice(1, -1) : word);
  if (argv[0] === 'cd' && argv[2] === '&&') argv.splice(0, 3);
  if (!/^(?:\/[^\s]+\/)?python3$/.test(argv.shift() ?? '')) return null;
  while (['-u', '-B'].includes(argv[0])) argv.shift();
  if (!/^\/[^\n]+\/(?:\.pi\/skills\/build\/scripts|src\/observation)\/run-stage\.py$/.test(argv.shift() ?? '')) return null;
  const fields = {};
  while (argv.length) {
    const key = argv.shift();
    if (key === '--screening-dispatch') continue;
    if (!['--attempt', '--task', '--role', '--provider', '--model', '--thinking', '--timeout', '--heartbeat', '--harness-package', '--observe-socket'].includes(key) || !argv.length || fields[key] !== undefined) return null;
    fields[key] = argv.shift();
  }
  const role = stageRole(fields['--role']);
  if (!role || !/^TASK-\d{5}$/.test(fields['--task'] ?? '') || !fields['--attempt']?.startsWith('/')) return null;
  return { role, task: fields['--task'], attempt: clean(fields['--attempt']).replace(/\n/g, ' '), label: clean(basename(fields['--attempt'])).replace(/\n/g, ' ') };
}

/** Customize only recognized ordinary process-stage rows, leaving execution and other tools native */
export function installStagePresentation(pi, { truncateToWidth }) {
  if (typeof pi.registerToolRenderer !== 'function') return false;
  const component = lines => ({ render: width => lines.map(line => truncateToWidth(line, Math.max(0, width))), invalidate() {} });
  pi.registerToolRenderer((name, next) => {
    const native = next();
    // Without both native slots, leave unrelated bash rendering wholly untouched.
    if (name !== 'bash' || !native?.renderCall || !native?.renderResult) return native;
    return { ...native,
      renderCall(args, theme, context) {
        const stage = stageInvocation(args?.command);
        if (!stage) return native.renderCall(args, theme, context);
        return component([theme.fg('accent', `● Agent · ${stage.role} ${stage.task} · ${stage.label}`)]);
      },
      renderResult(result, options, theme, context) {
        const stage = stageInvocation(context.args?.command);
        if (!stage) return native.renderResult(result, options, theme, context);
        const state = options.isPartial ? 'Working · process tool running; observation separate'
          : context.isError ? 'Failed/cancelled · inspect evidence; not acceptance'
            : 'Process tool finished · independent acceptance pending';
        const lines = [theme.fg(context.isError ? 'error' : 'muted', state)];
        if (options.expanded) {
          lines.push(`Attempt: ${stage.attempt}`);
          const output = result.content.filter(part => part.type === 'text').map(part => part.text).join('\n');
          lines.push(...output.split('\n').slice(-12).map(line => clean(line).replace(/\n/g, ' ').slice(0, 600)));
          lines.push('Bounded tool output only · process/SDK outcome is not Review/QA acceptance');
        }
        return component(lines);
      },
    };
  });
  return true;
}
