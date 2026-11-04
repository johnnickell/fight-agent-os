import { emitKeypressEvents } from 'node:readline';

/** Decode native split/coalesced keys without submitting text to any agent */
export function installActivityInput(input, { action, close, page }) {
  emitKeypressEvents(input);
  input.on('keypress', (text, key) => {
    if (key.name === 'q' || key.ctrl && key.name === 'c') { close(); return; }
    if (key.name === 'pageup' || key.name === 'pagedown') { page(key.name === 'pageup' ? 5 : -5); return; }
    const type = { up: 'previous', down: 'next', return: 'enter', enter: 'enter', escape: 'back' }[key.name] ??
      { '[': 'previous', ']': 'next', f: 'pin', p: 'pause', c: 'toggle', r: 'reconnect', i: 'help' }[text];
    // Node marks a standalone Escape as meta=true; it is still the explicit Back key.
    if (type === 'back' || type && !key.ctrl && !key.meta) action(type);
  });
}
