import test from 'node:test';
import assert from 'node:assert/strict';
import { installStagePresentation, stageInvocation } from '../src/observation/stage-presentation.mjs';

const command = "python3 -u -B '/provider/.pi/skills/build/scripts/run-stage.py' --task TASK-00585 --role reviewer --attempt '/private/run/02-reviewer' --provider configured --model configured --thinking high";
const theme = { fg: (_color, text) => text };
function fixture() {
  let resolver;
  const original = { renderShell: 'default', renderCall: () => 'native-call', renderResult: () => 'native-result' };
  assert.equal(installStagePresentation({ registerToolRenderer: fn => { resolver = fn; } }, { truncateToWidth: (line, width) => line.slice(0, width) }), true);
  return { original, renderer: resolver('bash', () => original), resolver };
}
test('recognition accepts simple provider/fixed-monitor argv, aliases and quoted cd; never arbitrary shell scripts', () => {
  assert.equal(stageInvocation(command).role, 'reviewer');
  assert.equal(stageInvocation("cd '/selected checkout' && " + command.replace('reviewer', 'review')).role, 'reviewer');
  assert.equal(stageInvocation(command.replace('/provider/.pi/skills/build/scripts', '/package/src/observation')).task, 'TASK-00585');
  for (const value of ['echo ' + command, command + '; echo unrelated', command + ' && echo unrelated', command.replace('python3', 'python'), command.replace('--role reviewer', '--role unknown'), command.replace('TASK-00585', 'unknown'), command + ' --role qa', 'python3 - <<\'PY\'\n' + command + '\nPY']) assert.equal(stageInvocation(value), null);
});
test('unrelated tools and ordinary bash retain the exact native slots and shell; no executor is replaced', () => {
  const { renderer, resolver, original } = fixture();
  assert.equal(resolver('read', () => original), original);
  assert.equal(renderer.renderShell, original.renderShell);
  assert.equal(renderer.renderCall({ command: 'printf ordinary' }, theme, {}), 'native-call');
  assert.equal(renderer.renderResult({}, {}, theme, { args: { command: 'printf ordinary' } }), 'native-result');
  assert.equal(resolver('bash', () => undefined), undefined);
  assert.equal(installStagePresentation({}, {}), false);
});
test('each active process has a bounded distinguishable dot/Agent row without command or raw chatter', () => {
  const { renderer } = fixture();
  for (const attempt of ['02-reviewer', '03-reviewer']) {
    const args = { command: command.replace('02-reviewer', attempt) };
    const lines = renderer.renderCall(args, theme, {}).render(200);
    assert.match(lines.join('\n'), new RegExp('● Agent.*reviewer TASK-00585.*' + attempt));
    const result = renderer.renderResult({ content: [{ type: 'text', text: 'PRIVATE_TOOL_CHATTER' }] }, { isPartial: true }, theme, { args });
    assert.equal(result.render(200).length, 1); assert.match(result.render(200)[0], /Working.*observation separate/);
    assert.doesNotMatch([...lines, ...result.render(200)].join('\n'), /python|PRIVATE_TOOL_CHATTER/);
    assert.ok(renderer.renderCall(args, theme, {}).render(20).every(line => line.length <= 20));
  }
});
test('terminal error/success describe tool lifecycle, not independent acceptance; expanded evidence is bounded/sanitized', () => {
  const { renderer } = fixture(), args = { command };
  const result = { content: [{ type: 'text', text: Array.from({ length: 25 }, (_, i) => `record-${i}\x1b[31m\u202e`).join('\n') }] };
  for (const isError of [true, false]) {
    const context = { args, isError };
    const compact = renderer.renderResult(result, { isPartial: false }, theme, context).render(200);
    assert.match(compact[0], isError ? /Failed\/cancelled.*not acceptance/ : /finished.*acceptance pending/);
    const details = renderer.renderResult(result, { expanded: true, isPartial: false }, theme, context).render(200);
    assert.equal(details.length, 15); assert.match(details[1], /Attempt: \/private\/run\/02-reviewer/);
    assert.doesNotMatch(details.join('\n'), /[\x1b\u202e]|record-0\b/);
  }
});
