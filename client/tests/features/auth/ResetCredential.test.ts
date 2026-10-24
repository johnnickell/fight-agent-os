import { describe, expect, it, vi } from 'vitest';

import { captureReset, discardResetFragment, takeReset } from '@/features/auth/ResetCredential';

const path = '/app/reset-password/complete';
const userId = crypto.randomUUID();
const fragment = (credential: string) => `#user_id=${userId}&credential=${credential}`;
describe('pre-render reset possession intake', () => {
  it('accepts bounded unchanged UTF-8 material, strips the whole URL/history and transfers once', () => {
    for (const value of ['opaque +&=% fragment', ' é '.repeat(800), 'x'.repeat(4096)]) {
      const host = {};
      const history = { replaceState: vi.fn() };
      expect(
        captureReset(host, { pathname: path, hash: fragment(encodeURIComponent(value)) }, history)
      ).toBe(true);
      expect(history.replaceState).toHaveBeenCalledWith(null, '', path);
      const owner = takeReset(host);
      expect(owner.current()?.credential === value).toBe(true);
      expect(owner.current()?.userId).toBe(userId);
      expect(JSON.stringify(owner)).toBe('{}');
      expect(takeReset(host).current()).toBeNull();
      owner.clear();
      expect(owner.current()).toBeNull();
    }
  });
  it('rejects missing, malformed, duplicate, unknown, reordered and oversized fields while removing history', () => {
    for (const hash of [
      '',
      '#credential=x',
      fragment(''),
      fragment('%'),
      fragment('%GG'),
      fragment('%FF'),
      fragment('raw+plus'),
      fragment('x&extra=y'),
      fragment('x&credential=y'),
      `#credential=x&user_id=${userId}`,
      fragment('x'.repeat(4097)),
      fragment(encodeURIComponent('é'.repeat(2049))),
      `#user_id=not-a-uuid&credential=x`
    ]) {
      const host = {};
      const history = { replaceState: vi.fn() };
      captureReset(host, { pathname: path, hash }, history);
      expect(history.replaceState).toHaveBeenCalledWith(null, '', path);
      expect(takeReset(host).current()).toBeNull();
    }
  });
  it('denies intake when replacement fails and ignores other route identities', () => {
    const host = {};
    expect(
      captureReset(
        host,
        { pathname: path, hash: fragment('x') },
        {
          replaceState: () => {
            throw new Error();
          }
        }
      )
    ).toBe(false);
    expect(takeReset(host).current()).toBeNull();
    const history = { replaceState: vi.fn() };
    captureReset(host, { pathname: '/app/login', hash: fragment('x') }, history);
    expect(history.replaceState).not.toHaveBeenCalled();
  });
  it('never adopts later fragments, but preserves the owned skip link', () => {
    const host = {};
    const history = { replaceState: vi.fn() };
    captureReset(host, { pathname: path, hash: fragment('x') }, history);
    expect(
      discardResetFragment(host, { pathname: path, hash: fragment('replacement') }, history)
    ).toBe(true);
    expect(takeReset(host).current()).toBeNull();
    expect(discardResetFragment(host, { pathname: path, hash: '#main-content' }, history)).toBe(
      false
    );
    expect(discardResetFragment(host, { pathname: path, hash: '' }, history)).toBe(false);
  });
});
