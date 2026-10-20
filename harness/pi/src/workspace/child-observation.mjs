import { lstatSync } from 'node:fs';
import { createConnection } from 'node:net';
import { isAbsolute } from 'node:path';
import { clean } from './presentation.mjs';

const version = 'fight-child-observation-v1';
const states = new Set(['starting', 'running', 'settled-awaiting-exit', 'completed', 'failed']);
const identityKeys = ['task', 'role', 'provider', 'model', 'thinking', 'worktree', 'attempt'];
const line = value => clean(value).replace(/\n/g, ' ');
const count = (value, amount = 1) => Math.min(Number.MAX_SAFE_INTEGER, value + amount);
// Field limits match Python's Unicode code points; framing/retention remain UTF-16 bounded.
const points = value => [...value].length;

function record(value) {
  if (!value || value.version !== version || !['snapshot', 'progress'].includes(value.kind) ||
    !Number.isSafeInteger(value.sequence) || value.sequence < 0 || !states.has(value.lifecycle) ||
    !Number.isFinite(value.heartbeatSeconds) || value.heartbeatSeconds <= 0 ||
    typeof value.text !== 'string' || points(value.text) > 600 ||
    !identityKeys.every(key => typeof value.identity?.[key] === 'string' && points(value.identity[key]) > 0 && points(value.identity[key]) <= 1100) ||
    (['completed', 'failed'].includes(value.lifecycle) ? !Number.isInteger(value.exitCode) || value.exitCode < 0 || value.exitCode > 255 || (value.lifecycle === 'completed') !== (value.exitCode === 0) : value.exitCode !== null)) {
    throw new Error('Invalid safe progress record');
  }
  // Copy only declared fields. No incoming object or extra field reaches the pane/model context.
  return { identity: Object.fromEntries(identityKeys.map(key => [key, line(value.identity[key])])),
    sequence: value.sequence, lifecycle: value.lifecycle, exitCode: value.exitCode,
    heartbeatSeconds: value.heartbeatSeconds, text: line(value.text), kind: value.kind };
}

export function observationPaths(paths) {
  if (paths.length > 8 || paths.some(path => !isAbsolute(path) || path.length > 1000 || /[\x00-\x1f\x7f]/.test(path)) || new Set(paths).size !== paths.length) {
    throw new Error('Use up to eight distinct absolute --observe-child socket paths');
  }
  return [...paths];
}

export class ChildObservation {
  constructor(paths, changed = () => {}, {
    connect = path => createConnection({ path }), inspect = lstatSync, now = Date.now,
  } = {}) {
    this.paths = observationPaths(paths); this.changed = changed; this.connect = connect; this.inspect = inspect; this.now = now;
    this.index = 0; this.generation = 0; this.socket = null; this.open = false; this.paused = false;
    this.pinned = new Map(); this.history = []; this.units = 0; this.omitted = 0; this.gaps = 0;
    this.state = 'unavailable'; this.notice = 'No child selected'; this.last = null; this.receivedAt = null;
  }
  start() { if (this.paths.length) this.select(0); }
  disconnect(notice = 'Reader closed; child execution unchanged') {
    this.generation++; const socket = this.socket; this.socket = null;
    if (socket) socket.destroy();
    this.state = 'disconnected'; this.notice = notice; this.changed();
  }
  select(index) {
    if (!this.paths.length) return;
    this.disconnect(); this.index = (index + this.paths.length) % this.paths.length;
    this.open = true; this.paused = false; this.last = null; this.receivedAt = null;
    this.history = []; this.units = 0; this.omitted = 0; this.gaps = 0;
    this.subscribe();
  }
  subscribe() {
    const generation = ++this.generation, path = this.paths[this.index];
    this.state = 'connecting'; this.notice = 'Connecting to explicitly selected local monitor'; this.changed();
    try {
      const info = this.inspect(path);
      if (!info.isSocket() || info.uid !== process.getuid() || (info.mode & 0o077) !== 0) throw new Error('Endpoint must be an owner-only local socket');
      const socket = this.connect(path); this.socket = socket; socket.setEncoding('utf8');
      let buffer = '', first = true, sequence;
      const current = () => this.generation === generation && this.socket === socket;
      const fail = notice => { if (current()) { buffer = ''; this.disconnect(notice); } };
      socket.on('data', chunk => {
        if (!current()) return;
        try {
          buffer += chunk;
          let end;
          while ((end = buffer.indexOf('\n')) >= 0) {
            if (end > 32768) throw new Error('Oversized safe progress record');
            const item = record(JSON.parse(buffer.slice(0, end))); buffer = buffer.slice(end + 1);
            if (first && item.kind !== 'snapshot' || !first && item.kind !== 'progress') throw new Error('Missing stream snapshot');
            const identity = JSON.stringify(item.identity), pinned = this.pinned.get(path);
            if (pinned && pinned !== identity) throw new Error('Endpoint attempt identity changed; reopen with explicit operator selection');
            this.pinned.set(path, identity);
            if (!first && item.sequence <= sequence) throw new Error('Stale observation sequence');
            if (first) {
              if (this.last && item.sequence < this.last.sequence) throw new Error('Stale reconnect snapshot');
              this.notice = 'Live snapshot; earlier progress is not replayed';
              if (this.last) this.gaps = count(this.gaps, Math.max(0, item.sequence - this.last.sequence - 1));
              else this.gaps = count(this.gaps, item.sequence);
            } else this.gaps = count(this.gaps, Math.max(0, item.sequence - sequence - 1));
            first = false; sequence = item.sequence;
            this.last = item; this.receivedAt = this.now(); this.state = 'connected';
            if (this.paused) this.omitted = count(this.omitted);
            else this.append(item.text);
          }
          if (buffer.length > 32768) throw new Error('Oversized partial safe progress record');
          this.changed();
        } catch { fail('Invalid, stale or oversized observation; liveness unknown'); }
      });
      socket.on('error', () => fail('Monitor unavailable; liveness unknown (no automatic retry)'));
      socket.on('close', () => fail(buffer ? 'Stream lost mid-record; gap and liveness unknown' : 'Monitor disconnected; last known state only'));
    } catch { this.state = 'disconnected'; this.notice = 'Unavailable: use an existing owner-only monitor socket; no child launched'; this.changed(); }
  }
  append(text) {
    this.history.push(text); this.units += text.length;
    while (this.history.length > 100 || this.units > 32768) {
      this.units -= this.history.shift().length; this.omitted = count(this.omitted);
    }
  }
  action(type) {
    if (type === 'previous' || type === 'next') this.select(this.index + (type === 'next' ? 1 : -1));
    else if (type === 'pause') { this.paused = !this.paused; this.changed(); }
    else if (type === 'toggle') {
      this.open = !this.open;
      if (this.open && this.paths.length) this.subscribe(); else this.disconnect();
    } else if (type === 'reconnect' && this.paths.length) { this.disconnect(); this.open = true; this.subscribe(); }
  }
  view() {
    if (!this.paths.length) return 'Observation only — child agent\nUnavailable: no child selected\nSupply explicit --observe-child /checkout/var/...sock\nNo discovery, launch or child control.';
    const item = this.last, identity = item?.identity;
    const stale = this.state === 'connected' && this.receivedAt !== null && this.now() - this.receivedAt > Math.max(5000, item.heartbeatSeconds * 3000);
    const state = stale ? 'unknown liveness — heartbeat overdue' : this.state;
    return ['Observation only — untrusted child status',
      `Reader ${state}${this.paused ? ' · DISPLAY PAUSED (reader drains)' : ''} · selection ${this.index + 1}/${this.paths.length}`,
      '[ / ] select · p pause · c close/open · r reconnect',
      identity ? `${identity.role} · configured ${identity.provider}/${identity.model} · ${identity.thinking}` : 'Role/model: unknown until monitor snapshot',
      this.paused ? 'Progress display paused; lifecycle/heartbeat still observed' : `Latest displayed: ${this.history.at(-1) ?? '(waiting)'}`,
      identity ? `TASK ${identity.task}\nWorktree ${identity.worktree}\nAttempt ${identity.attempt}` : `Endpoint ${line(this.paths[this.index])}`,
      item ? `Last known: ${item.lifecycle}${item.exitCode === null ? '' : ` (exit ${item.exitCode}; not acceptance)`} · sequence ${item.sequence}` : 'Child lifecycle: unknown',
      'Provider-reported model: unavailable in safe monitor contract',
      this.notice, `Gaps/earlier events: ${this.gaps} · omitted/paused updates: ${this.omitted}`,
      ...this.history].join('\n');
  }
  close() { this.open = false; this.disconnect(); }
}
