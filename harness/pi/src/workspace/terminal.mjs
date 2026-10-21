import { emitKeypressEvents } from 'node:readline';
import { PassThrough } from 'node:stream';
import { prefix } from './text.mjs';

// Bracketed paste must never turn pasted newlines into submissions or confirmations.
export class Keyboard {
  buffer = ''; paste = false; pasted = ''; discarded = false;
  constructor(onKey, onPaste) {
    this.stream = new PassThrough(); emitKeypressEvents(this.stream);
    this.stream.on('keypress', onKey); this.onPaste = onPaste; this.onKey = onKey;
  }
  push(text) {
    this.buffer += text;
    const marker = this.paste ? '\x1b[201~' : '\x1b[200~';
    const at = this.buffer.indexOf(marker);
    if (at >= 0) {
      const before = this.buffer.slice(0, at); this.buffer = this.buffer.slice(at + marker.length);
      if (this.paste) {
        this.retainPaste(before); this.onPaste(this.pasted, { discarded: this.discarded }); this.pasted = ''; this.discarded = false;
      } else if (before) this.stream.write(before);
      this.paste = !this.paste; this.push(''); return;
    }
    // Retain only an incomplete paste marker; Escape and ordinary ANSI keys must still arrive.
    let keep = 0;
    for (let length = 1; length < marker.length; length++) if (this.buffer.endsWith(marker.slice(0, length))) keep = length;
    const ready = this.buffer.slice(0, this.buffer.length - keep); this.buffer = this.buffer.slice(this.buffer.length - keep);
    if (this.paste) this.retainPaste(ready);
    else if (ready) this.stream.write(ready);
    clearTimeout(this.timer);
    if (this.buffer && !this.paste) this.timer = setTimeout(() => {
      if (this.buffer === '\x1b') this.onKey('\x1b', { name: 'escape' });
      else this.stream.write(this.buffer);
      this.buffer = '';
    }, 30);
  }
  retainPaste(text) {
    if (this.discarded) return;
    const bounded = prefix(this.pasted + text, 32768);
    this.pasted = bounded.text; this.discarded = bounded.omitted;
  }
  close() { clearTimeout(this.timer); this.stream.destroy(); }
}
export class Terminal {
  started = false; stopped = false;
  constructor(input, output) { this.input = input; this.output = output; this.wasRaw = input.isRaw; }
  start(onKey, onPaste) {
    if (!this.input.isTTY || !this.output.isTTY) throw new Error('Workspace needs terminal stdin and stdout');
    this.started = true;
    this.keyboard = new Keyboard(onKey, onPaste);
    this.input.setEncoding('utf8'); this.input.setRawMode(true);
    this.listener = text => this.keyboard.push(text); this.input.on('data', this.listener); this.input.resume();
    this.output.write('\x1b[?1049h\x1b[?25l\x1b[?2004h');
  }
  draw(lines, theme) {
    // A slow terminal gets the latest state after drain, not an unbounded queue of stale frames.
    if (this.output.writableNeedDrain) return;
    const palette = theme === 'light' ? '\x1b[30;47m' : theme === 'dark' ? '\x1b[97;40m' : '\x1b[0m';
    // Never write the final column: avoid terminal autowrap and scrolling at the bottom-right cell.
    this.output.write('\x1b[H' + palette + lines.join('\x1b[K\r\n') + '\x1b[K\x1b[0m');
  }
  stop() {
    if (this.stopped) return; this.stopped = true;
    if (!this.started) return;
    this.keyboard?.close(); this.input.removeListener('data', this.listener);
    try { this.input.setRawMode(Boolean(this.wasRaw)); } finally {
      this.input.pause(); this.output.write('\x1b[0m\x1b[?2004l\x1b[?25h\x1b[?1049l');
    }
  }
}
