import { describe, expect, it, vi } from 'vitest';

import { clearLoginLocation } from '@/features/auth/LoginLocation';

describe('login URL retirement', () => {
  it('drops the entire query, fragment and history state without serializing any caller values', () => {
    const history = { replaceState: vi.fn() };
    expect(clearLoginLocation({ pathname: '/app/login' }, history)).toBe(true);
    expect(history.replaceState).toHaveBeenCalledExactlyOnceWith(null, '', '/app/login');
  });
  it('leaves unrelated routes and sensitive invitation intake to their owners', () => {
    const history = { replaceState: vi.fn() };
    expect(clearLoginLocation({ pathname: '/app/activate' }, history)).toBe(true);
    expect(history.replaceState).not.toHaveBeenCalled();
  });
  it('signals failure for a fixed safe navigation fallback without exposing the browser exception', () => {
    const history = {
      replaceState: () => {
        throw new Error('private');
      }
    };
    expect(clearLoginLocation({ pathname: '/app/login' }, history)).toBe(false);
  });
});
