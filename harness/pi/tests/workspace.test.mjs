import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, statSync, rmSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { run } from '../src/workspace/main.mjs';
import { Workspace, width, fit, wrap, clean, layout } from '../src/workspace/presentation.mjs';
import { JsonLines, PiRpc, childEnvironment, messageText } from '../src/workspace/rpc.mjs';
import { Keyboard, Terminal } from '../src/workspace/terminal.mjs';

// AC-06 retirement: all old launch options refuse before terminal/storage/process effects.
// Public migration guidance and forbidden effects come from TASK-00512, not old renderer output.
test('retirement help describes native ownership, non-Herdr use and same-actual-file migration', () => {
  for (const flag of ['--help', '-h']) {
    let output = '';
    assert.equal(run([flag], { output: { write: text => { output += text; } }, errorOutput: { write: () => assert.fail('help on stderr') } }), 0);
    for (const text of ['retired', 'ordinary pi', '/fight-workspace open', '/fight-workspace close', 'Outside Herdr', 'pi --session /absolute/actual-session.jsonl', 'Do not guess with --continue', 'Ephemeral --no-session', 'No sessions, settings']) assert.ok(output.includes(text), text);
  }
});
test('every former conversation route fails without echoing arguments or touching a terminal/adapter', () => {
  const privateValue = 'PRIVATE-ARGUMENT-DO-NOT-ECHO';
  for (const args of [[], ['--session', privateValue], ['--continue'], ['--no-session'], ['--logs-test', privateValue], ['--observe-child', privateValue], ['--help', '--session', privateValue], [privateValue]]) {
    let diagnostic = '';
    const dependencies = { output: { write: () => assert.fail('no success output') }, errorOutput: { write: text => { diagnostic += text; } } };
    for (const field of ['input', 'connect', 'createTerminal', 'observe', 'readLogs']) Object.defineProperty(dependencies, field, { get: () => assert.fail(`retirement touched ${field}`) });
    assert.equal(run(args, dependencies), 1);
    assert.match(diagnostic, /RPC conversation frontend is retired/);
    assert.ok(!diagnostic.includes(privateValue));
    assert.ok(!diagnostic.includes('\x1b'));
  }
});
test('direct former RPC entry leaves existing history, cwd, settings and executables untouched', t => {
  const root = mkdtempSync(join(tmpdir(), 'fight-retirement-'));
  t.after(() => rmSync(root, { recursive: true, force: true }));
  const project = join(root, 'project with spaces'), home = join(root, 'home'), bin = join(root, 'bin');
  for (const directory of [project, home, bin]) mkdirSync(directory);
  // An existing file is deliberately opaque: retirement must not parse, migrate or create a session.
  const session = join(project, 'actual prior session.jsonl'), settings = join(home, 'settings.json'), marker = join(root, 'unexpected-process');
  writeFileSync(session, 'existing opaque history\n'); writeFileSync(settings, '{"theme":"preserve"}\n');
  for (const command of ['pi', 'herdr', 'docker']) writeFileSync(join(bin, command), `#!/bin/sh\nprintf unexpected > '${marker}'\nexit 91\n`, { mode: 0o700 });
  const before = statSync(session), history = readFileSync(session), configuration = readFileSync(settings);
  for (const args of [[], ['--session', session], ['--continue'], ['--no-session'], ['--help']]) {
    const result = spawnSync(process.execPath, [fileURLToPath(new URL('../src/workspace/main.mjs', import.meta.url)), ...args], {
      cwd: project, env: { HOME: home, PATH: bin, HERDR_ENV: '1' }, input: 'must never become a prompt\n', encoding: 'utf8', timeout: 3000,
    });
    assert.equal(result.error, undefined); assert.equal(result.status, args[0] === '--help' ? 0 : 1);
    assert.match(result.stdout + result.stderr, /frontend is retired/);
    assert.deepEqual(readFileSync(session), history); assert.deepEqual(readFileSync(settings), configuration);
    assert.equal(statSync(session).ino, before.ino);
    assert.deepEqual(readdirSync(project), ['actual prior session.jsonl']);
    assert.deepEqual(readdirSync(root).sort(), ['bin', 'home', 'project with spaces']);
  }
});

// Retained presentation/transport contracts. The retired conversation controller is not runnable.
const key = (model, name, extras = {}) => model.key('', { name, ...extras });
const workspace = () => { const model = new Workspace('/checkout'); model.ready = true; return model; };

test('wide geometry preserves half conversation and two right quarters without overlap', () => {
  assert.deepEqual(layout(120, 43), [
    { pane: 0, x: 0, y: 1, w: 60, h: 40 },
    { pane: 1, x: 60, y: 1, w: 60, h: 20 },
    { pane: 2, x: 60, y: 21, w: 60, h: 20 },
  ]);
});
test('narrow, short and collapsed layouts select exactly the focused view', () => {
  for (const [columns, rows, collapsed] of [[80, 30, false], [120, 18, false], [120, 40, true]]) {
    const areas = layout(columns, rows, 2, collapsed);
    assert.equal(areas.length, 1); assert.equal(areas[0].pane, 2); assert.equal(areas[0].w, columns);
  }
});
test('focus cannot submit or edit conversation from either observation', () => {
  const model = workspace(); model.insert('retained');
  for (let pane = 1; pane <= 2; pane++) {
    key(model, 'tab'); model.key('!rm unrelated'); assert.equal(key(model, 'return'), undefined);
    assert.equal(model.draft, 'retained'); assert.equal(model.cwd, '/checkout');
  }
  key(model, 'tab'); assert.deepEqual(key(model, 'return'), { type: 'submit', message: 'retained' });
});
test('startup holds input and submit until qualified', () => {
  const model = new Workspace('/checkout'); model.key('hello'); key(model, 'return'); assert.equal(model.draft, '');
});
test('reverse focus, split toggle and theme selection do not change directory', () => {
  const model = workspace(); key(model, 'tab', { shift: true }); assert.equal(model.focus, 2);
  key(model, 'w', { ctrl: true }); assert.equal(model.collapsed, true);
  key(model, 't', { ctrl: true }); assert.equal(model.theme, 'light');
  key(model, 't', { ctrl: true }); assert.equal(model.theme, 'dark'); assert.equal(model.cwd, '/checkout');
});
test('editor moves and deletes whole Unicode graphemes, accepts multiline and history', () => {
  const model = workspace(); model.insert('A👩‍💻界é'); key(model, 'left'); key(model, 'backspace');
  assert.equal(model.draft, 'A👩‍💻é'); key(model, 'home'); key(model, 'delete');
  assert.equal(model.draft, '👩‍💻é'); key(model, 'end'); key(model, 'j', { ctrl: true }); model.insert('next');
  assert.equal(key(model, 'return').message, '👩‍💻é\nnext');
  key(model, 'up'); assert.equal(model.draft, '👩‍💻é\nnext'); key(model, 'down'); assert.equal(model.draft, '');
  model.insert('x'.repeat(1000)); key(model, 'home');
  assert.match(model.render(80, 24).join('\n'), /> ▏/);
});
test('draft, live display and transcript retention are bounded with disclosed omission', () => {
  const model = workspace(); model.insert('x'.repeat(40000)); assert.equal(model.draft.length, 32768);
  for (let index = 0; index < 600; index++) model.append(`line ${index} ${'x'.repeat(1000)}`);
  assert.ok(model.transcriptBytes <= 262144); assert.ok(model.transcript.length <= 500);
  assert.match(model.paneText(0), /Older display content omitted/);
});
test('Unicode line width and clipping preserve CJK, combining marks and emoji clusters', () => {
  assert.equal(width('A界é👩‍💻🇺🇸'), 8); assert.equal(fit('界界界', 5), '界界 ');
  assert.deepEqual(wrap('界界界\né👩‍💻', 4), ['界界', '界', 'é👩‍💻']);
  assert.equal(width(fit('👩‍💻', 1)), 1);
});
test('untrusted output cannot emit terminal controls or bidi overrides', () => {
  assert.equal(clean('\x1b]52;c;secret\x07\r\u202eevil'), ']52;c;secretevil');
  assert.ok(!/[\x00-\x1f\x7f-\x9f]/.test(fit('\x1b[2J\tdata', 20)));
  assert.equal(clean('A\u200b\u200e\ufeff界'), 'A界');
});
test('every render stays inside viewport with Unicode in adjacent panes and after resize', () => {
  const model = workspace(); model.append('界👩‍💻é'.repeat(400));
  for (const [columns, rows] of [[120, 40], [101, 21], [80, 24], [20, 8], [8, 3], [1, 1]]) {
    const lines = model.render(columns, rows); assert.equal(lines.length, rows);
    for (const line of lines) assert.equal(width(line), columns, line);
    if (columns >= 80) assert.match(lines[0], /screening: unavailable/);
  }
});
test('conversation scrolling is clamped and independent of observation focus', () => {
  const model = workspace(); for (let index = 0; index < 100; index++) model.append(`entry-${index}`);
  for (let index = 0; index < 100; index++) key(model, 'pageup');
  const before = model.render(120, 30).join('\n'); assert.match(before, /entry-0/); assert.doesNotMatch(before, /entry-99/);
  const offset = model.offsets[0]; key(model, 'tab'); key(model, 'pagedown'); assert.equal(model.offsets[1], 5);
  key(model, 'pageup'); assert.equal(model.offsets[1], 0); model.render(120, 30);
  assert.equal(model.offsets[0], offset); assert.equal(model.offsets[1], 0);
  key(model, 'tab', { shift: true }); for (let index = 0; index < 100; index++) key(model, 'pagedown');
  assert.match(model.render(120, 30).join('\n'), /entry-99/);
});
test('observation states disclose missing producers and cannot impersonate conversation', () => {
  const model = workspace(); assert.match(model.paneText(1), /no child selected/);
  assert.match(model.paneText(2), /No runtime selected/); assert.match(model.paneText(2), /shared runtime/);
});
test('Pi confirmations are modal, explicit, request-bound and deny by default', () => {
  const model = workspace(); model.focus = 2; model.insert('draft');
  model.dialogs.push({ id: 'first', method: 'confirm' }, { id: 'second', method: 'confirm' });
  assert.equal(key(model, 'tab'), undefined); assert.equal(model.focus, 2);
  assert.deepEqual(key(model, 'return'), { type: 'dialog', id: 'first', confirmed: false });
  assert.deepEqual(model.key('y', { name: 'y' }), { type: 'dialog', id: 'second', confirmed: true });
  assert.equal(model.draft, 'draft'); assert.equal(model.focus, 2);
});
test('dialogs support cancellation, selection, editing and bounded scroll of exact command', () => {
  const model = workspace(); model.dialogs.push({ id: 'choose', method: 'select', options: ['deny', 'allow'] });
  key(model, 'down'); assert.equal(key(model, 'return').value, 'allow');
  model.dialogs.push({ id: 'input', method: 'editor', prefill: 'hello' }); model.key('界', {}); key(model, 'backspace');
  assert.equal(key(model, 'return').value, 'hello');
  model.dialogs.push({ id: 'approval', method: 'confirm', message: Array.from({ length: 100 }, (_, i) => `command line ${i}`).join('\n') });
  key(model, 'pagedown'); assert.equal(model.dialogs[0].scroll, 5);
  assert.match(model.render(80, 24).join('\n'), /command line 5/);
  assert.deepEqual(key(model, 'escape'), { type: 'dialog', id: 'approval', cancelled: true });
});
test('abort and exit remain available while observing or startup is held', () => {
  const model = new Workspace('/checkout'); model.focus = 1;
  assert.equal(key(model, 'escape').type, 'abort'); assert.equal(key(model, 'q', { ctrl: true }).type, 'quit');
  assert.equal(key(model, 'd', { ctrl: true }).type, 'quit');
  model.draft = 'retained'; assert.equal(key(model, 'd', { ctrl: true }), undefined);
});
test('bracketed paste across chunks never becomes Return, command submission or confirmation', () => {
  const keys = [], pastes = []; const keyboard = new Keyboard((text, key) => keys.push(key.name), text => pastes.push(text));
  keyboard.push('\x1b[20'); keyboard.push('0~!danger\ny\r'); keyboard.push('\x1b[201~');
  assert.deepEqual(keys, []); assert.deepEqual(pastes, ['!danger\ny\r']);
  keyboard.push('\r'); assert.deepEqual(keys, ['return']); keyboard.close();
});
test('keyboard bounds oversized pastes and handles standalone Escape', async () => {
  const keys = [], pastes = []; const keyboard = new Keyboard((text, key) => keys.push(key.name), text => pastes.push(text));
  keyboard.push('\x1b[200~' + 'x'.repeat(50000) + '\x1b[201~'); assert.equal(pastes[0].length, 32768);
  keyboard.push('\x1b'); await new Promise(resolve => setTimeout(resolve, 50)); assert.deepEqual(keys, ['escape']); keyboard.close();
});
test('RPC framing accepts fragmented LF, CRLF and Unicode separators inside strings', () => {
  const records = [], lines = new JsonLines(record => records.push(record));
  lines.push('{"type":"event","text":"a\u2028b\u2029c"}\r'); lines.push('\n{"type":"next"}\n');
  assert.deepEqual(records, [{ type: 'event', text: 'a\u2028b\u2029c' }, { type: 'next' }]);
});
test('RPC rejects oversized or malformed records instead of growing indefinitely', () => {
  assert.throws(() => new JsonLines(() => {}, 8).push('123456789'), /limit/);
  assert.throws(() => new JsonLines(() => {}).push('invalid\n'), SyntaxError);
});
test('child environment excludes provider/application credentials and launcher role promotion', () => {
  assert.deepEqual(childEnvironment({ HOME: '/home', PATH: '/bin', OPENAI_API_KEY: 'fixture', APP_SECRET: 'fixture', FIGHT_SCREENING_PHASE: 'land' }), { HOME: '/home', PATH: '/bin' });
});
test('message display omits system/signature/hidden content and preserves tool and image identities', () => {
  assert.equal(messageText({ role: 'system', content: 'private' }), '');
  assert.equal(messageText({ role: 'custom', display: false, content: 'private' }), '');
  const text = messageText({ role: 'assistant', content: [{ type: 'text', text: 'hello', textSignature: 'opaque' }, { type: 'image', data: 'private' }, { type: 'toolCall', name: 'read', arguments: { path: 'README.md' } }] });
  assert.match(text, /hello/); assert.match(text, /image retained by Pi/); assert.match(text, /tool: read/); assert.doesNotMatch(text, /opaque|private/);
  assert.match(messageText({ role: 'assistant', provider: 'configured-provider', model: 'requested', responseModel: 'returned', content: 'result' }), /configured configured-provider\/requested · response model returned/);
});
test('terminal restores original raw mode and alternate screen once, including drawing failure', () => {
  const input = new EventEmitter(); input.isTTY = true; input.isRaw = false;
  const modes = [], output = []; input.setRawMode = mode => modes.push(mode); input.setEncoding = () => {}; input.resume = () => {}; input.pause = () => {};
  const terminal = new Terminal(input, { isTTY: true, write: text => output.push(text) });
  terminal.start(() => {}, () => {}); terminal.draw(['hello'], 'light'); terminal.stop(); terminal.stop();
  assert.deepEqual(modes, [true, false]); assert.equal(input.listenerCount('data'), 0);
  assert.ok(output[0].includes('\x1b[?2004h')); assert.ok(output.at(-1).includes('\x1b[?1049l'));
  const failed = new Terminal(input, { isTTY: true, write: text => { if (text.includes('boom')) throw new Error('render'); output.push(text); } });
  failed.start(() => {}, () => {}); assert.throws(() => failed.draw(['boom'], 'dark')); failed.stop(); assert.equal(modes.at(-1), false);
});
test('slow terminal output drops obsolete frames instead of growing a display write queue', () => {
  const input = new EventEmitter(); input.isTTY = true; input.setRawMode = () => {}; input.setEncoding = () => {}; input.resume = () => {}; input.pause = () => {};
  const writes = [], output = { isTTY: true, writableNeedDrain: false, write: text => writes.push(text) };
  const terminal = new Terminal(input, output); terminal.start(() => {}, () => {});
  output.writableNeedDrain = true;
  for (let i = 0; i < 1000; i++) terminal.draw([`obsolete ${i}`], 'dark');
  assert.equal(writes.length, 1);
  output.writableNeedDrain = false; terminal.draw(['latest frame'], 'dark');
  assert.match(writes.at(-1), /latest frame/); assert.equal(writes.length, 2);
  terminal.stop(); assert.match(writes.at(-1), /\?1049l/);
});
test('nonterminal launch is rejected without changing raw mode', () => {
  assert.throws(() => new Terminal({ isTTY: false }, { isTTY: false }).start(), /terminal/);
});
test('R2 long and wrapped selects keep the exact selected value visible after navigation and resize', () => {
  const model = workspace();
  const options = Array.from({ length: 50 }, (_, i) => `choice-${i} ${'wrapped label '.repeat(8)} END-${i}`);
  model.dialogs.push({ id: 'long', method: 'select', title: 'Choose', options });
  for (let i = 0; i < 30; i++) key(model, 'down');
  for (const [columns, rows] of [[78, 24], [120, 30], [30, 12], [78, 24]]) {
    const frame = model.render(columns, rows).join('\n');
    assert.match(frame, /> choice-30/);
  }
  key(model, 'up'); assert.match(model.render(78, 24).join('\n'), /> choice-29/);
  assert.equal(key(model, 'return').value, options[29]);
});
test('R2 manually scrolling a label taller than the pane retains the selected option identity', () => {
  const model = workspace(); model.dialogs.push({ id: 'tall', method: 'select', options: ['deny', 'allow\n' + 'detail\n'.repeat(40) + 'END-ALLOW'] });
  key(model, 'down'); model.render(30, 12);
  for (let i = 0; i < 8; i++) key(model, 'pagedown');
  const frame = model.render(30, 12).join('\n');
  assert.match(frame, /Selected option 2\/2/); assert.match(frame, /END-ALLOW/);
  assert.match(key(model, 'return').value, /^allow/);
});
test('R3 typed and dialog input limits reject whole emoji and combining graphemes with notice', () => {
  for (const cluster of ['👩‍💻', 'é']) {
    const model = workspace(); model.insert('x'.repeat(32767));
    const keyboard = new Keyboard((text, key) => model.key(text, key), text => model.insert(text));
    keyboard.push(cluster); keyboard.close();
    assert.equal(model.draft, 'x'.repeat(32767));
    assert.ok(model.draft.isWellFormed()); assert.match(model.status, /input.*(discard|reject)/i);
    model.dialogs.push({ id: 'bounded', method: 'input', prefill: 'x'.repeat(32767) });
    model.key(cluster, {});
    assert.equal(model.dialogs[0].value, 'x'.repeat(32767));
    assert.match(model.status, /input.*(discard|reject)/i);
    assert.equal(key(model, 'return').value.length, 32767);
  }
});
test('R3 paste bounds preserve split graphemes and disclose discarded input', () => {
  const model = workspace();
  const keyboard = new Keyboard((text, key) => model.key(text, key), (text, retention) => { model.insert(text); if (retention.discarded) model.inputLoss(); });
  keyboard.push('\x1b[200~' + 'x'.repeat(32767) + '👩'); keyboard.push('‍💻'); keyboard.push('\x1b[201~'); keyboard.close();
  assert.match(model.status, /input.*(discard|reject)/i);
  assert.equal(key(model, 'return').message, 'x'.repeat(32767));
});
test('R3 complete graphemes that fit remain accepted; rejected middle insertion preserves existing suffix', () => {
  for (const cluster of ['👩‍💻', 'é', '界']) {
    const model = workspace(); model.insert('x'.repeat(32768 - cluster.length));
    const keyboard = new Keyboard((text, key) => model.key(text, key), text => model.insert(text));
    keyboard.push(cluster); keyboard.close();
    assert.equal(model.draft, 'x'.repeat(32768 - cluster.length) + cluster);
    key(model, 'home'); model.insert('👩‍💻');
    assert.equal(model.draft, 'x'.repeat(32768 - cluster.length) + cluster);
    assert.match(model.status, /Input rejected/);
  }
});
test('R3 paste combining boundaries, huge dialog prefill and newline cannot bypass the bound', () => {
  const model = workspace();
  const keyboard = new Keyboard(() => {}, (text, retention) => { model.insert(text); if (retention.discarded) model.inputLoss(); });
  keyboard.push('\x1b[200~' + 'x'.repeat(32767) + 'e'); keyboard.push('́\x1b[201~'); keyboard.close();
  assert.equal(model.draft, 'x'.repeat(32767)); assert.match(model.status, /Input rejected/);
  model.dialogs.push({ id: 'prefill', method: 'editor', prefill: 'x'.repeat(32767) + '👩‍💻' }); model.render(78, 24);
  assert.equal(model.dialogs[0].value, 'x'.repeat(32767));
  key(model, 'j', { ctrl: true }); key(model, 'j', { ctrl: true });
  assert.equal(key(model, 'return').value, 'x'.repeat(32767) + '\n');
});
test('R4 a single large entry discloses clipping while retaining source identity and a Unicode-safe tail', () => {
  const model = workspace(); model.append('toolResult: read\nBEGIN_CONTEXT\n' + '👩‍💻'.repeat(15000) + '\nEND');
  assert.ok(model.transcript[0].length <= 65536);
  assert.ok(model.transcript[0].isWellFormed());
  assert.match(model.paneText(0), /toolResult: read/);
  assert.match(model.paneText(0), /omitted|truncat/i);
  assert.match(model.paneText(0), /END/);
  const frame = model.render(78, 24).join('\n');
  assert.match(frame, /toolResult: read/); assert.match(frame, /truncated/i);
});
test('R4 streaming loss is visible with assistant identity before the final message', () => {
  const model = workspace(); model.stream('👩‍💻'.repeat(15000) + '\nTAIL');
  const frame = model.render(140, 40).join('\n');
  assert.match(frame, /assistant/i); assert.match(frame, /omitted|truncat/i); assert.match(frame, /TAIL/);
  assert.ok(frame.isWellFormed());
});

// R6: standard input/editor dialogs must show the point being edited, not just retain its value.
for (const method of ['input', 'editor']) {
  test(`R6 long ${method} follows edits, backspace, newline and resize with exact request identity`, () => {
    const model = workspace();
    const prefill = Array.from({ length: 40 }, (_, i) => `existing-line-${i}`).join('\n');
    model.dialogs.push({ id: `long-${method}`, method, title: 'Review response', prefill });
    assert.match(model.render(78, 24).join('\n'), /existing-line-39▏/);
    model.key('VISIBLE_EDIT_MARKER', {});
    assert.match(model.render(78, 24).join('\n'), /VISIBLE_EDIT_MARKER▏/);
    key(model, 'backspace');
    assert.match(model.render(78, 24).join('\n'), /VISIBLE_EDIT_MARKE▏/);
    key(model, 'j', { ctrl: true }); model.key('界👩‍💻'.repeat(30) + 'WRAPPED_END', {});
    for (const [columns, rows] of [[78, 24], [120, 30], [30, 12], [78, 24]]) {
      const lines = model.render(columns, rows);
      assert.match(lines.join('\n'), /WRAPPED_END▏/);
      assert.equal(lines.length, rows);
      for (const line of lines) assert.equal(width(line), columns);
    }
    assert.deepEqual(key(model, 'return'), {
      type: 'dialog', id: `long-${method}`,
      value: prefill + 'VISIBLE_EDIT_MARKE\n' + '界👩‍💻'.repeat(30) + 'WRAPPED_END',
    });
  });
}
test('R6 manual dialog scrolling survives redraw/resize until the next edit and cancellation stays bound', () => {
  const model = workspace(); model.focus = 2; model.insert('separate draft');
  model.dialogs.push({ id: 'manual-editor', method: 'editor', prefill: Array.from({ length: 80 }, (_, i) => `line-${i}`).join('\n') });
  model.render(78, 24);
  for (let i = 0; i < 30; i++) key(model, 'pageup');
  for (const [columns, rows] of [[78, 24], [78, 24], [120, 30], [30, 12]]) {
    const frame = model.render(columns, rows).join('\n');
    assert.match(frame, /line-0/); assert.doesNotMatch(frame, /line-79/);
  }
  key(model, 'pagedown'); assert.match(model.render(78, 24).join('\n'), /line-5/);
  key(model, 'backspace'); assert.match(model.render(78, 24).join('\n'), /line-7▏/);
  for (let i = 0; i < 30; i++) key(model, 'pageup');
  model.render(78, 24); key(model, 'j', { ctrl: true });
  assert.match(model.render(78, 24).join('\n'), /line-7/);
  assert.match(model.render(78, 24).join('\n'), /▏/);
  assert.deepEqual(key(model, 'escape'), { type: 'dialog', id: 'manual-editor', cancelled: true });
  assert.equal(model.draft, 'separate draft'); assert.equal(model.focus, 2);
});

// Transport integration uses a separate harmless protocol peer, not a substitute for real Pi qualification.
const peer = `process.stdin.setEncoding('utf8');let b='';process.stdin.on('data',t=>{b+=t;let n;while((n=b.indexOf('\\n'))>=0){let r=JSON.parse(b.slice(0,n));b=b.slice(n+1);if(r.type==='get_state')setTimeout(()=>process.stdout.write(JSON.stringify({type:'response',id:r.id,success:true,data:{sessionId:'owned'}})+'\\n'),20);else if(r.type==='get_commands')process.stdout.write(JSON.stringify({type:'response',id:r.id,success:true,data:{commands:[]}})+'\\n');else if(r.type==='fail')process.stdout.write(JSON.stringify({type:'response',id:r.id,success:false,error:'rejected'})+'\\n');}});`;
test('RPC correlates concurrent responses, surfaces rejection/deadline and closes child cleanly', async () => {
  const rpc = new PiRpc({ cwd: process.cwd(), spawnProcess: (_file, _args, options) => spawn(process.execPath, ['-e', peer], options) });
  rpc.on('failure', () => {});
  try {
    const [state, commands] = await Promise.all([rpc.request('get_state'), rpc.request('get_commands')]);
    assert.equal(state.sessionId, 'owned'); assert.deepEqual(commands.commands, []);
    await assert.rejects(rpc.request('fail'), /rejected/);
    await assert.rejects(rpc.request('never', {}, 10), /deadline/);
    await rpc.close(); await rpc.close(); assert.equal(rpc.exited, true); assert.equal(rpc.pending.size, 0);
  } finally { await rpc.close(); }
});
