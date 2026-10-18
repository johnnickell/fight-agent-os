import assert from 'node:assert/strict';
import test from 'node:test';
import { ChildCommandView } from '../src/jev/child-command-view.mjs';

const request = { task: 'TASK-00491', role: 'engineer', session: 'fixture', cwd: '/owned/checkout',
  outcome: 'uncertain', reason: 'incomplete', command: '# ' + 'harmless comment; '.repeat(200) + '\nprintf "é-界 \\"quoted\\""\t\x1b[2J\u202e' };
function fixture(columns = 80, rows = 24) {
  const size = { columns, rows }, decisions = [];
  const view = new ChildCommandView(request, { size: () => size, redraw() {},
    done: value => decisions.push(value), key: (data, key) => data === key });
  const render = () => view.render(size.columns);
  return { view, size, decisions, render };
}

for (const [columns, rows] of [[100, 30], [80, 24], [64, 12]]) {
  test(`QA-01: all command bytes inspectable with persistent controls at ${columns}x${rows}`, () => {
    const { view, decisions, render } = fixture(columns, rows);
    const body = [];
    for (let page = 0; ; page++) {
      assert.ok(page < 200);
      const lines = render();
      assert.ok(lines.length <= rows - 2);
      assert.ok(lines.every(line => line.length <= columns && /^[\x20-\x7e]*$/.test(line)));
      assert.match(lines.at(-2), /PgDn/);
      assert.match(lines.at(-1), /Enter\/Esc\/d: deny/);
      body.push(...lines.slice(3, -3));
      if (lines.at(-1).startsWith('a:')) break;
      view.handleInput('a'); assert.deepEqual(decisions, []);
      view.handleInput('n');
    }
    // Rejoin exact width chunks; JSON decoding must recover controls, whitespace and Unicode.
    const text = body.join('');
    const label = 'Command (ASCII JSON; escapes are literal):';
    const command = text.slice(text.indexOf(label) + label.length);
    assert.equal(JSON.parse(command), request.command);
    assert.ok(text.includes('Task: "TASK-00491"Role: "engineer"'));
    view.handleInput('a'); assert.deepEqual(decisions, [true]);
  });
}

test('QA-01: Enter is refusal before rendering and on a clipped-length first page', () => {
  for (const draw of [false, true]) {
    const { view, decisions, render } = fixture();
    if (draw) render();
    view.handleInput('enter'); view.handleInput('a');
    assert.deepEqual(decisions, [false]);
  }
});

test('jumping to End or sending input bursts cannot skip unseen pages', () => {
  const { view, render, decisions } = fixture(); render();
  view.handleInput('end'); view.handleInput('a'); assert.deepEqual(decisions, []);
  assert.match(render().at(-1), /Approval locked/);
  view.handleInput('a'); assert.deepEqual(decisions, []);
  view.handleInput('home'); render();
  view.handleInput('n'); view.handleInput('n');
  assert.match(render()[2], /^Page 2\//);
  view.handleInput('p'); assert.match(render()[2], /^Page 1\//);
});

test('resize invalidates approval and restarts inspection; too-small windows cannot approve', () => {
  const { view, render, size, decisions } = fixture(100, 100);
  assert.match(render().at(-1), /^a:/);
  size.columns = 80; size.rows = 24;
  view.handleInput('a'); assert.deepEqual(decisions, []);
  assert.match(render()[2], /^Page 1\//);
  assert.match(render().at(-1), /Approval locked/);
  size.columns = 20; size.rows = 6;
  assert.ok(render().every(line => line.length <= 20));
  view.handleInput('a'); assert.deepEqual(decisions, []);
  view.handleInput('escape'); assert.deepEqual(decisions, [false]);
});

test('cancel and late input complete once, without acceptance', () => {
  const { view, render, decisions } = fixture(); render();
  view.finish(false); view.handleInput('end'); render(); view.handleInput('a'); view.finish(true);
  assert.deepEqual(decisions, [false]);
});

test('invalidation requires another render before accepting', () => {
  const { view, render, decisions } = fixture(100, 100); render(); view.invalidate();
  view.handleInput('a'); assert.deepEqual(decisions, []);
  render(); view.handleInput('a'); assert.deepEqual(decisions, [true]);
});
