// ASCII JSON preserves every code unit while making controls, bidi and terminal escapes visible.
const escaped = value => JSON.stringify(value).replace(/[\u007f-\uffff]/g,
  character => `\\u${character.charCodeAt(0).toString(16).padStart(4, '0')}`);

/** Present one immutable request with bounded pages and refusal as the default */
export class ChildCommandView {
  constructor(request, { size, redraw, done, key }) {
    this.text = [
      `Task: ${escaped(request.task)}`, `Role: ${escaped(request.role)}`,
      `Child session: ${escaped(request.session)}`, `Directory: ${escaped(request.cwd)}`,
      `Outcome: ${escaped(request.outcome)}`, `Reason: ${escaped(request.reason)}`,
      'Command (ASCII JSON; escapes are literal):', escaped(request.command),
    ].join('\n');
    this.size = size; this.redraw = redraw; this.done = done; this.key = key;
    this.page = 0; this.pages = []; this.seen = new Set(); this.dimensions = '';
    this.ready = false; this.closed = false;
  }
  invalidate() { this.ready = false; }
  finish(accepted) {
    if (this.closed) return;
    this.closed = true; this.done(accepted);
  }
  render(width) {
    const { columns, rows } = this.size();
    const dimensions = `${width}:${columns}:${rows}`;
    const usable = width >= 64 && columns >= width && rows >= 12;
    if (dimensions !== this.dimensions) {
      this.dimensions = dimensions; this.page = 0; this.seen.clear();
      const lines = this.text.split('\n').flatMap(line => {
        const chunks = [];
        for (let start = 0; start < line.length; start += Math.max(1, width)) chunks.push(line.slice(start, start + Math.max(1, width)));
        return chunks;
      });
      this.pages = [];
      const capacity = Math.max(1, rows - 8);
      for (let start = 0; start < lines.length; start += capacity) this.pages.push(lines.slice(start, start + capacity));
    }
    this.ready = usable;
    if (!usable) return ['Terminal too small; resize to 64x12.', 'Approval disabled. Esc/Enter: deny.']
      .slice(0, Math.max(0, rows - 2)).map(line => line.slice(0, Math.max(0, width)));
    this.seen.add(this.page);
    return [
      'Child command held - ONE decision',
      'Existing task/role authority only. No permission expansion.',
      `Page ${this.page + 1}/${this.pages.length}; ${this.seen.size}/${this.pages.length} pages displayed`,
      ...this.pages[this.page],
      '-'.repeat(width),
      'PgDn/n: next | PgUp/p: back | Home/End: first/last',
      this.canApprove() ? 'a: approve this command | Enter/Esc/d: deny (default)' : 'Approval locked: display every page. Enter/Esc/d: deny',
    ];
  }
  canApprove() { return this.ready && this.page === this.pages.length - 1 && this.seen.size === this.pages.length; }
  handleInput(data) {
    if (this.closed) return;
    if (this.key(data, 'escape') || this.key(data, 'enter') || this.key(data, 'ctrl+c') || data === 'd') return this.finish(false);
    const { columns, rows } = this.size();
    // A resize between drawing and input cannot approve the old layout.
    if (!this.ready || this.dimensions !== `${columns}:${columns}:${rows}`) { this.ready = false; this.redraw(); return; }
    if (data === 'a' && this.canApprove()) return this.finish(true);
    if (data === 'n' || this.key(data, 'pageDown') || this.key(data, 'down')) this.page = Math.min(this.page + 1, this.pages.length - 1);
    else if (data === 'p' || this.key(data, 'pageUp') || this.key(data, 'up')) this.page = Math.max(0, this.page - 1);
    else if (this.key(data, 'home')) this.page = 0;
    else if (this.key(data, 'end')) this.page = this.pages.length - 1;
    else return;
    // Input bursts must not skip unseen pages or approve before the new page renders.
    this.ready = false; this.redraw();
  }
}
