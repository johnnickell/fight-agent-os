import { describe, expect, it } from 'vitest';

import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';

describe('conservative volatile access budget', () => {
  it('subtracts full request delay and never uses the browser wall clock', () => {
    let monotonic = 10_000;
    const tokens = new MemoryAccessToken(() => monotonic);
    expect(tokens.accept('access.local.signature', 0)).toBe(true);
    expect(tokens.remaining()).toBe(830_000);
    monotonic = 840_000;
    expect(tokens.getAccessToken()).toBeNull();
  });
  it('rejects delayed or nonfinite peer hints rather than granting a fresh lifetime', () => {
    const tokens = new MemoryAccessToken(() => 100);
    expect(tokens.accept('access.local.signature', 0, tokens.generation, 100)).toBe(false);
    expect(tokens.accept('access.local.signature', 0, tokens.generation, NaN)).toBe(false);
    expect(tokens.getAccessToken()).toBeNull();
  });
  it('retires authority at a backwards monotonic clock discontinuity before a protected read', () => {
    let monotonic = 100;
    const tokens = new MemoryAccessToken(() => monotonic);
    tokens.accept('access.local.signature', monotonic);
    expect(tokens.getAccessToken()).not.toBeNull();
    monotonic = 99;
    expect(tokens.getAccessToken()).toBeNull();
  });
});
