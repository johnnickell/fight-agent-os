// Local presentation only. Pi remains authoritative for conversation, tools and sessions.
import { graphemes, prefix, tail, boundedInsert } from './text.mjs';
export { graphemes } from './text.mjs';
export const clean = value => String(value ?? '').replace(/[\x00-\x08\x0b-\x1f\x7f-\x9f\u061c\u200b\u200e\u200f\u202a-\u202e\u2060-\u206f\ufeff]/g, '').replace(/\t/g, '    ');
export function cellWidth(cluster) {
  if (/^[\p{Mark}\u200d\ufe0f]+$/u.test(cluster)) return 0;
  const code = cluster.codePointAt(0);
  return /\p{Extended_Pictographic}|\p{Regional_Indicator}|\u20e3/u.test(cluster) ||
    (code >= 0x1100 && (code <= 0x115f || code === 0x2329 || code === 0x232a ||
      (code >= 0x2e80 && code <= 0xa4cf && code !== 0x303f) || (code >= 0xac00 && code <= 0xd7a3) ||
      (code >= 0xf900 && code <= 0xfaff) || (code >= 0xfe10 && code <= 0xfe19) ||
      (code >= 0xfe30 && code <= 0xfe6f) || (code >= 0xff00 && code <= 0xff60) ||
      (code >= 0xffe0 && code <= 0xffe6) || code >= 0x20000)) ? 2 : 1;
}
export const width = text => graphemes(text).reduce((sum, part) => sum + cellWidth(part), 0);
export function fit(text, columns) {
  let result = '', used = 0;
  for (const part of graphemes(clean(text).replace(/\n/g, ' '))) {
    const size = cellWidth(part);
    if (used + size > columns) break;
    result += part; used += size;
  }
  return result + ' '.repeat(Math.max(0, columns - used));
}
export function wrap(text, columns) {
  columns = Math.max(1, columns);
  const lines = [];
  for (const line of clean(text).split('\n')) {
    let current = '', used = 0;
    for (const part of graphemes(line)) {
      const size = cellWidth(part);
      if (used + size > columns && current) { lines.push(current); current = ''; used = 0; }
      if (size > columns) { lines.push('?'); continue; }
      current += part; used += size;
    }
    lines.push(current);
  }
  return lines;
}
export function layout(columns, rows, focus = 0, collapsed = false, availableColumns = columns) {
  const singleView = columns < 100 || rows < 20 || collapsed;
  columns = Math.max(1, Math.min(columns, availableColumns));
  if (singleView) return [{ pane: focus, x: 0, y: 1, w: columns, h: Math.max(1, rows - 3) }];
  const left = Math.floor(columns / 2), top = Math.floor((rows - 3) / 2);
  return [{ pane: 0, x: 0, y: 1, w: left, h: rows - 3 },
    { pane: 1, x: left, y: 1, w: columns - left, h: top },
    { pane: 2, x: left, y: 1 + top, w: columns - left, h: rows - 3 - top }];
}
export class Workspace {
  focus = 0; collapsed = false; theme = 'system'; draft = ''; cursor = 0;
  offsets = [0, 0, 0]; transcript = []; transcriptBytes = 0; omitted = false;
  live = ''; liveOmitted = false; rejectedInput = ''; status = 'Starting Pi; input held until policy qualification'; ready = false; busy = false;
  identity = 'Pi starting'; policy = 'unavailable'; commands = new Set(); dialogs = []; history = []; historyIndex = 0;
  constructor(cwd) { this.cwd = cwd; }
  append(text) {
    text = clean(text);
    if (text.length > 65536) {
      // Keep the source/role header independently of the clipped body tail.
      const identity = prefix(text.split('\n', 1)[0], 256).text;
      const notice = `${identity}\n[Earlier entry content omitted; Pi history is authoritative]\n`;
      const ending = `\n[${identity}: entry truncated; Pi history is authoritative]`;
      text = notice + tail(text, 65536 - notice.length - ending.length) + ending; this.omitted = true;
    }
    this.transcript.push(text); this.transcriptBytes += text.length;
    while (this.transcriptBytes > 262144 || this.transcript.length > 500) {
      this.transcriptBytes -= this.transcript.shift().length; this.omitted = true;
    }
  }
  stream(text) {
    const combined = this.live + clean(text);
    if (combined.length > 65536) this.liveOmitted = true;
    this.live = tail(combined, 65536);
  }
  inputLoss() { this.status = 'Input rejected at 32 Ki limit; discarded content was not submitted.'; }
  insert(text) {
    // A later keypress may continue a rejected emoji/combining cluster. Do not insert its fragments.
    const bounded = boundedInsert(this.draft, this.cursor, clean(text), this.rejectedInput);
    this.draft = bounded.value; this.cursor = bounded.cursor; this.rejectedInput = bounded.rejected;
    if (bounded.discarded) this.inputLoss();
  }
  key(text, key = {}) {
    const dialog = this.dialogs[0];
    if (key.ctrl && (key.name === 'q' || key.name === 'd' && !this.draft && !dialog)) return { type: 'quit' };
    if (dialog) return this.dialogKey(text, key, dialog);
    if (key.name === 'tab') { this.focus = (this.focus + (key.shift ? 2 : 1)) % 3; return; }
    if (key.ctrl && key.name === 'w') { this.collapsed = !this.collapsed; return; }
    if (key.ctrl && key.name === 't') { this.theme = this.theme === 'light' ? 'dark' : 'light'; return; }
    if (key.name === 'pageup' || key.name === 'pagedown') {
      const delta = (key.name === 'pageup' ? 5 : -5) * (this.focus === 0 || this.focus === 2 && !this.runtimeLogs?.info ? 1 : -1);
      this.offsets[this.focus] = Math.max(0, this.offsets[this.focus] + delta); return;
    }
    if (key.ctrl && key.name === 'c' || key.name === 'escape') return { type: 'abort' };
    if (this.focus === 1 && !key.ctrl && !key.meta) {
      const action = { '[': 'previous', ']': 'next', f: 'pin', p: 'pause', c: 'toggle', r: 'reconnect' }[text];
      if (action) return { type: 'observation', action };
    }
    if (this.focus === 2 && !key.ctrl && !key.meta) {
      const action = { '[': 'previous', ']': 'next', '{': 'stack-previous', '}': 'stack-next', m: 'mode', p: 'pause', c: 'toggle', r: 'reconnect', i: 'info' }[text];
      if (action) return { type: 'runtime', action };
    }
    if (this.focus !== 0 || !this.ready) return;
    if (key.name === 'return') {
      const message = this.draft.trim();
      if (!message) return;
      this.history.push(this.draft); this.history = this.history.slice(-50); this.historyIndex = this.history.length;
      this.draft = ''; this.cursor = 0; this.rejectedInput = '';
      return { type: 'submit', message };
    }
    if (key.ctrl && key.name === 'j') { this.insert('\n'); return; }
    if (['left', 'right', 'home', 'end', 'backspace', 'delete', 'up', 'down'].includes(key.name)) this.rejectedInput = '';
    if (key.name === 'left') this.cursor = Math.max(0, this.cursor - 1);
    else if (key.name === 'right') this.cursor = Math.min(graphemes(this.draft).length, this.cursor + 1);
    else if (key.name === 'home' || key.ctrl && key.name === 'a') this.cursor = 0;
    else if (key.name === 'end' || key.ctrl && key.name === 'e') this.cursor = graphemes(this.draft).length;
    else if (key.name === 'backspace' || key.name === 'delete') {
      const parts = graphemes(this.draft), index = key.name === 'backspace' ? this.cursor - 1 : this.cursor;
      if (index >= 0) { parts.splice(index, 1); this.draft = parts.join(''); if (key.name === 'backspace') this.cursor = index; }
    } else if (key.name === 'up' || key.name === 'down') {
      this.historyIndex = Math.max(0, Math.min(this.history.length, this.historyIndex + (key.name === 'up' ? -1 : 1)));
      this.draft = this.history[this.historyIndex] ?? ''; this.cursor = graphemes(this.draft).length;
    } else if (!key.ctrl && !key.meta && text) this.insert(text);
  }
  dialogKey(text, key, dialog) {
    let response;
    if (key.name === 'pageup' || key.name === 'pagedown') {
      dialog.scroll = Math.max(0, (dialog.scroll ?? 0) + (key.name === 'pagedown' ? 5 : -5));
      dialog.followEdit = false; return;
    }
    if (key.name === 'escape' || key.ctrl && key.name === 'c') response = { cancelled: true };
    else if (dialog.method === 'confirm') {
      if (text?.toLowerCase() === 'y') response = { confirmed: true };
      else if (text?.toLowerCase() === 'n' || key.name === 'return') response = { confirmed: false };
    } else if (dialog.method === 'select') {
      if (key.name === 'up') dialog.choice = Math.max(0, (dialog.choice ?? 0) - 1);
      if (key.name === 'down') dialog.choice = Math.min(dialog.options.length - 1, (dialog.choice ?? 0) + 1);
      if (key.name === 'return') response = { value: dialog.options[dialog.choice ?? 0] };
    } else {
      const initial = prefix(clean(dialog.value ?? dialog.prefill ?? ''), 32768);
      dialog.value = initial.text; if (initial.omitted) this.inputLoss();
      if (key.name === 'return') response = { value: dialog.value };
      else if (key.name === 'backspace') {
        dialog.value = graphemes(dialog.value).slice(0, -1).join(''); dialog.rejectedInput = ''; dialog.followEdit = true;
      }
      else if (key.ctrl && key.name === 'j' || !key.ctrl && !key.meta && text) {
        const bounded = boundedInsert(dialog.value, graphemes(dialog.value).length, key.ctrl ? '\n' : clean(text), dialog.rejectedInput);
        dialog.value = bounded.value; dialog.rejectedInput = bounded.rejected; dialog.followEdit = true;
        if (bounded.discarded) this.inputLoss();
      }
    }
    if (response) { this.dialogs.shift(); return { type: 'dialog', id: dialog.id, ...response }; }
  }
  paneText(pane) {
    if (pane === 1) return this.childObservation?.view() ?? 'Observation only — child agent\nUnavailable: no child selected\nNo discovery, launch or child control.';
    if (pane === 2) return this.runtimeLogs?.view() ?? 'Human-only shared runtime logs\nNo runtime selected. No Docker reader started.';
    return [this.omitted ? '[Older display content omitted; Pi session remains authoritative]' : '', ...this.transcript, this.live || this.liveOmitted ? `${this.liveOmitted ? '[assistant stream: earlier content omitted]\n' : ''}${this.live}${this.liveOmitted ? '\n[assistant stream truncated; Pi history is authoritative]' : ''}` : ''].filter(Boolean).join('\n');
  }
  render(columns, rows, availableColumns = columns) {
    columns = Math.max(1, columns); rows = Math.max(1, rows);
    // Physical dimensions select the layout; drawable width may reserve the autowrap column.
    const areas = layout(columns, rows, this.dialogs.length ? 0 : this.focus, this.collapsed, availableColumns);
    columns = Math.max(1, Math.min(columns, availableColumns));
    if (rows < 6 || columns < 12) return Array.from({ length: rows }, (_, index) => fit(index === 0 ? 'Resize terminal; ^Q exits' : '', columns));
    const output = Array.from({ length: rows }, () => ' '.repeat(columns));
    const pieces = Array.from({ length: rows }, () => []);
    output[0] = fit(`FIGHT | screening: ${this.policy} | ${this.busy ? 'running' : 'idle'} | ${this.identity}`, columns);
    for (const area of areas) {
      const { pane, x, y, w, h } = area, inner = Math.max(1, w - 2);
      let title = ['Conversation', 'Child observation', 'Runtime observation'][pane];
      const content = this.dialogs.length && pane === 0 ? this.dialogText() : this.paneText(pane);
      let lines = wrap(content, inner);
      const inputRows = pane === 0 && !this.dialogs.length ? Math.min(4, Math.max(1, h - 3)) : 0;
      const viewport = Math.max(0, h - 2 - inputRows);
      this.offsets[pane] = Math.min(this.offsets[pane], Math.max(0, lines.length - viewport));
      const end = lines.length - this.offsets[pane];
      // Observation and dialogs start at the top; conversation follows the newest content.
      if (pane === 0 && !this.dialogs.length) lines = lines.slice(Math.max(0, end - viewport), end);
      else if (pane === 2 && this.runtimeLogs && !this.runtimeLogs.info && (this.runtimeLogs.source || this.runtimeLogs.bindings)) {
        const header = wrap(this.runtimeLogs.summary(), inner).slice(0, Math.max(0, viewport - 2));
        const body = wrap(this.runtimeLogs.body(), inner), available = viewport - header.length;
        this.offsets[2] = Math.min(this.offsets[2], Math.max(0, body.length - available));
        const end = body.length - this.offsets[2];
        lines = [...header, ...body.slice(Math.max(0, end - available), end)];
      } else {
        const dialog = this.dialogs.length && pane === 0 ? this.dialogs[0] : undefined;
        let offset = dialog ? Math.min(dialog.scroll ?? 0, Math.max(0, lines.length - viewport)) : this.offsets[pane];
        if (dialog?.method === 'select') {
          const available = Math.max(0, viewport - 1);
          const selectionView = `${dialog.choice ?? 0}:${inner}:${available}`;
          if (dialog.selectionView !== selectionView) {
            const before = [dialog.title, dialog.message, ...dialog.options.slice(0, dialog.choice ?? 0).map(option => `  ${option}`)].filter(Boolean).join('\n');
            const selectedStart = before ? wrap(before, inner).length : 0;
            const selectedRows = wrap(`> ${dialog.options[dialog.choice ?? 0]}`, inner).length;
            if (selectedStart < offset || selectedStart + selectedRows > offset + available) offset = selectedStart;
            dialog.selectionView = selectionView;
          }
          dialog.scroll = offset;
          // Manual scroll can inspect the rest of a long label without hiding what Enter submits.
          lines = [`Selected option ${(dialog.choice ?? 0) + 1}/${dialog.options.length}`, ...lines.slice(offset, offset + available)];
        } else {
          if (dialog && ['input', 'editor'].includes(dialog.method)) {
            if (dialog.followEdit !== false) {
              // The caret ends the value, immediately before the final help line. Follow it on
              // edits/resizes, but leave deliberate PageUp/PageDown inspection alone until editing.
              const caretRow = wrap(content.slice(0, content.lastIndexOf('\n')), inner).length - 1;
              if (caretRow < offset) offset = caretRow;
              else if (caretRow >= offset + viewport) offset = Math.max(0, caretRow - viewport + 1);
            }
            dialog.scroll = offset;
          }
          lines = lines.slice(offset, offset + viewport);
        }
      }
      if (this.dialogs.length && pane === 0) title = 'Pi interaction — explicit response required';
      const block = [fit(`${pane === this.focus ? '>' : ' '} ${title} ${this.offsets[pane] ? `[scroll ${this.offsets[pane]}]` : ''}`, w)];
      for (let i = 0; i < viewport; i++) block.push(fit(`|${fit(lines[i] ?? '', inner)}|`, w));
      if (inputRows) {
        const parts = graphemes(this.draft); parts.splice(this.cursor, 0, '▏');
        const cursorRow = wrap(`> ${graphemes(this.draft).slice(0, this.cursor).join('')}▏`, inner).length - 1;
        const start = Math.max(0, cursorRow - inputRows + 1);
        const draft = wrap(`> ${parts.join('')}`, inner).slice(start, start + inputRows);
        for (let i = 0; i < inputRows; i++) block.push(fit(`|${fit(draft[i] ?? '', inner)}|`, w));
      }
      block.push('-'.repeat(w));
      for (let i = 0; i < h; i++) {
        pieces[y + i].push({ x, text: fit(block[i] ?? '', w) });
      }
    }
    for (let row = 1; row < rows - 2; row++) {
      if (pieces[row].length) output[row] = pieces[row].sort((a, b) => a.x - b.x).map(piece => piece.text).join('');
    }
    output[rows - 2] = fit(this.status, columns);
    const logHelp = this.runtimeLogs?.bindings ? 'Logs: { / } stack  [ / ] service  m mode  p pause  c close/open  r reconnect  i details  Tab focus  ^Q exit' :
      'Logs: [ / ] service  p pause  c reader close/open  r reconnect  i source details  Tab focus  PgUp/PgDn scroll  ^Q exit';
    const childHelp = 'Child: [ / ] choose  f pin/unpin  p pause  c all readers  r reconnect  Tab focus  ^Q exit';
    output[rows - 1] = fit(!this.dialogs.length && this.focus === 1 ? childHelp : this.focus === 2 && !this.dialogs.length ? logHelp : 'Tab focus/views  PgUp/PgDn scroll  ^W split  ^T theme  Esc abort  ^J newline  ^Q exit', columns);
    return output;
  }
  dialogText() {
    const dialog = this.dialogs[0];
    if (['input', 'editor'].includes(dialog.method)) {
      const bounded = prefix(clean(dialog.value ?? dialog.prefill ?? ''), 32768);
      dialog.value = bounded.text; if (bounded.omitted) this.inputLoss();
    }
    return [dialog.title, dialog.message, ...(dialog.options ?? []).map((option, index) => `${index === (dialog.choice ?? 0) ? '>' : ' '} ${option}`),
      dialog.method === 'confirm' ? 'y: allow this request once · n/Enter/Esc: deny' : dialog.method === 'select' ? 'Up/Down select · Enter submit · Esc cancel' : `${dialog.value ?? dialog.prefill ?? ''}▏\nEnter submit · ^J newline · Esc cancel`].filter(Boolean).join('\n');
  }
}
