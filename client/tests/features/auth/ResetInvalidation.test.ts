import { describe, expect, it, vi } from 'vitest';

import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { broadcastPasswordReset, decodeSessionMessage } from '@/features/auth/SessionChannel';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';
import { AuthorityCache } from '@/features/authority/AuthorityCache';

import type { SessionChannel } from '@/features/auth/SessionChannel';

describe('target-scoped terminal reset invalidation', () => {
  it('does not replace committed success when peer messaging or cleanup is denied', () => {
    const target = crypto.randomUUID();
    vi.stubGlobal('isSecureContext', true);
    vi.stubGlobal(
      'BroadcastChannel',
      class {
        postMessage() {
          throw new Error('denied');
        }
        close() {
          throw new Error('denied');
        }
      }
    );
    try {
      expect(() => broadcastPasswordReset(target)).not.toThrow();
    } finally {
      vi.unstubAllGlobals();
    }
  });
  it('retires same-user tokens/principal/intent without touching an unrelated principal or broadcasting global logout', async () => {
    const target = crypto.randomUUID();
    for (const userId of [target, crypto.randomUUID()]) {
      const authority = new AuthorityCache(() =>
        Promise.resolve({
          status: 'principal',
          principal: { userId, email: 'sample@example.test', roles: [], permissions: [] }
        })
      );
      await authority.load();
      const tokens = new MemoryAccessToken();
      tokens.accept('access.current.signature', performance.now());
      const channel: SessionChannel = { onmessage: null, postMessage: vi.fn(), close: vi.fn() };
      const clearIntent = vi.fn();
      const coordinator = new SessionCoordinator({
        authority,
        tokens,
        channel,
        clearIntent,
        session: { refresh: vi.fn(), logout: vi.fn(), dispose: vi.fn(), invalidate: vi.fn() },
        login: { submit: vi.fn(), dispose: vi.fn() }
      });
      const message = {
        version: 1,
        sender: 'f'.repeat(32),
        sequence: 1,
        kind: 'password-reset',
        userId: target
      };
      expect(decodeSessionMessage(message)).not.toBeNull();
      channel.onmessage?.({ data: message } as MessageEvent<unknown>);
      if (userId === target) {
        expect(tokens.getAccessToken()).toBeNull();
        expect(authority.getSnapshot().status).toBe('terminal');
        expect(coordinator.getSnapshot().status).toBe('terminal');
        expect(clearIntent).toHaveBeenCalledOnce();
      } else {
        expect(tokens.getAccessToken()).not.toBeNull();
        expect(authority.getSnapshot().status).toBe('authenticated');
        expect(clearIntent).not.toHaveBeenCalled();
      }
      expect(channel.postMessage).not.toHaveBeenCalled();
      coordinator.dispose();
      authority.dispose();
    }
    expect(
      decodeSessionMessage({
        version: 1,
        sender: 'f'.repeat(32),
        sequence: 1,
        kind: 'password-reset',
        userId: target,
        credential: 'secret'
      })
    ).toBeNull();
  });
});
