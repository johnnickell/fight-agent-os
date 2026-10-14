import { Unavailable } from './readiness.mjs';

/** Reject duplicate (including escaped) member names before JSON.parse can erase answer identity */
export function parseProviderJson(text) {
  let cursor = 0;
  const fail = () => { throw new Unavailable('invalid-result'); };
  const space = () => { while (/[\x20\t\r\n]/.test(text[cursor] ?? '\0')) cursor++; };
  function string() {
    const start = cursor++;
    while (cursor < text.length) {
      const char = text[cursor++];
      if (char === '\\') cursor++;
      else if (char === '"') {
        try { return JSON.parse(text.slice(start, cursor)); } catch { fail(); }
      }
    }
    fail();
  }
  function value(depth) {
    if (depth > 32) fail();
    space();
    const char = text[cursor];
    if (char === '"') { string(); return; }
    if (char === '{' || char === '[') {
      const object = char === '{', end = object ? '}' : ']', keys = new Set();
      cursor++; space();
      if (text[cursor] === end) { cursor++; return; }
      while (cursor < text.length) {
        if (object) {
          space(); if (text[cursor] !== '"') fail();
          const key = string(); if (keys.has(key)) fail(); keys.add(key);
          space(); if (text[cursor++] !== ':') fail();
        }
        value(depth + 1); space();
        if (text[cursor] === end) { cursor++; return; }
        if (text[cursor++] !== ',') fail();
      }
      fail();
    }
    const start = cursor;
    while (cursor < text.length && !/[\x20\t\r\n,}\]]/.test(text[cursor])) cursor++;
    if (cursor === start) fail();
  }
  value(0); space(); if (cursor !== text.length) fail();
  try { return JSON.parse(text); } catch { fail(); }
}
