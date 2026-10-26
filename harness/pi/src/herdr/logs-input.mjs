import { emitKeypressEvents } from 'node:readline';

/** Decode and bound human-only log-pane input, including split/coalesced terminal sequences */
export function installLogInput(input, logs, { closing, close, canChoose, scroll, changed }) {
  let actions = Promise.resolve(), queued = 0;
  emitKeypressEvents(input);
  input.on('keypress', (text, key) => {
    if (key.name === 'q' || key.ctrl && key.name === 'c') { void close(); return; }
    if (key.name === 'pageup' || key.name === 'pagedown') {
      const delta = key.name === 'pageup' ? 5 : -5;
      scroll(logs.logs.menu ? -delta : delta); changed(); return;
    }
    const action = { up: 'up', down: 'down', return: 'choose', enter: 'choose', escape: 'cancel' }[key.name] ??
      { s: 'select', '[': 'previous', ']': 'next', '{': 'stack-previous', '}': 'stack-next', m: 'mode', p: 'pause', c: 'toggle', r: 'reconnect', i: 'info' }[text];
    if (!action || queued >= 16 || closing()) return;
    queued++; scroll(0, true);
    // Discovery already owns a serial queue shared with rebind/refresh. Submit now so
    // entry, generation and manual intent are bound before any later selection tick.
    // Explicit legacy bindings retain their existing terminal-side serialization.
    const pending = (logs.discovery ? logs.action(action, { canChoose }) :
      actions.then(() => closing() ? undefined : logs.action(action))).catch(() => {
      if (!closing()) logs.hold('UNAVAILABLE — reader action failed; reopen from native Pi');
    }).finally(() => { queued--; });
    actions = Promise.all([actions, pending]).then(() => {});
  });
  return { settled: () => actions, pending: () => queued };
}
