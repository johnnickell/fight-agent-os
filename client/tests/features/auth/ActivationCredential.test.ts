import { describe, expect, it, vi } from 'vitest';

import {
  captureActivation,
  discardActivationFragment,
  takeActivation
} from '@/features/auth/ActivationCredential';

const credential = 'a'.repeat(64);
const userId = crypto.randomUUID();
const valid = `#credential=${credential}&user_id=${userId}`;

describe('pre-render invitation capture', () => {
  it('replaces the complete URL and state before releasing material once', () => {
    const host = {};
    let replaced = false;
    const history = {
      replaceState: vi.fn(() => {
        replaced = true;
      })
    };
    captureActivation(host, { pathname: '/app/activate', hash: valid }, history);
    expect(replaced).toBe(true);
    expect(history.replaceState).toHaveBeenCalledWith(null, '', '/app/activate');
    const owner = takeActivation(host);
    expect(owner.current()?.credential === credential).toBe(true);
    expect(owner.current()?.userId === userId).toBe(true);
    expect(Object.keys(host)).toEqual([]);
    expect(JSON.stringify(owner)).toBe('{}');
    expect(takeActivation(host).current()).toBeNull();
    owner.clear();
    expect(owner.current()).toBeNull();
  });

  it.each([
    '',
    '#credential=',
    '#credential=x',
    '#other=x',
    `#credential=${credential}%0A&user_id=${userId}`,
    `#credential=${credential}&user_id=${userId}%0A`,
    `${valid}&extra=x`,
    `${valid}&credential=${credential}`,
    '#'.repeat(2048),
    '#credential=%00&user_id=missing'
  ])('clears malformed material without retaining input (%#)', (hash) => {
    const host = {};
    const history = { replaceState: vi.fn() };
    captureActivation(host, { pathname: '/app/activate', hash }, history);
    expect(takeActivation(host).current()).toBeNull();
    expect(history.replaceState).toHaveBeenCalledWith(null, '', '/app/activate');
  });

  it('discards later fragment material without replacing authority, while preserving the owned skip link', () => {
    const host = {};
    const history = { replaceState: vi.fn() };
    captureActivation(host, { pathname: '/app/activate', hash: valid }, history);
    expect(
      discardActivationFragment(
        host,
        { pathname: '/app/activate', hash: '#credential=later' },
        history
      )
    ).toBe(true);
    expect(takeActivation(host).current()).toBeNull();
    captureActivation(host, { pathname: '/app/activate', hash: valid }, history);
    expect(
      discardActivationFragment(host, { pathname: '/app/activate', hash: '#main-content' }, history)
    ).toBe(false);
    expect(takeActivation(host).current()).not.toBeNull();
    expect(discardActivationFragment(host, { pathname: '/app/activate', hash: '' }, history)).toBe(
      false
    );
    expect(discardActivationFragment(host, { pathname: '/app', hash: valid }, history)).toBe(false);
  });

  it('fails closed when URL replacement is unavailable and ignores unrelated routes', () => {
    const host = {};
    captureActivation(
      host,
      { pathname: '/app/activate', hash: valid },
      {
        replaceState: () => {
          throw new Error('blocked');
        }
      }
    );
    expect(takeActivation(host).current()).toBeNull();
    const history = { replaceState: vi.fn() };
    captureActivation(host, { pathname: '/app', hash: valid }, history);
    expect(history.replaceState).not.toHaveBeenCalled();
    expect(takeActivation(host).current()).toBeNull();
  });
});
