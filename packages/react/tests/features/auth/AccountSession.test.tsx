import { StrictMode } from 'react';

import {
  apiFailure,
  createAccountSession,
  MemoryAccessToken,
  useAccountSession
} from '@fight/react/account';
import { principal } from '@fixtures/authorityFixtures';
import { ApiClient } from '@fixtures/transport';
import { act, renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const runtime = {
  schemaVersion: 2,
  apiBasePath: '/api/v1',
  appDebug: false,
  swaggerAvailable: false
} as const;

function setup() {
  const intent = { clear: vi.fn(), dispose: vi.fn() };
  const session = createAccountSession(runtime, () => intent);
  const restore = vi
    .spyOn(session.coordinator, 'restore')
    .mockResolvedValue(apiFailure('cancelled'));
  return { session, intent, restore };
}

describe('shared account refusal and invalidation policy', () => {
  it('does not send a protected raw JSON read without an access token', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    const { session } = setup();
    try {
      expect(
        await session.transport.getProtectedJson(
          '/reports/summary',
          () => true,
          new AbortController().signal
        )
      ).toEqual(apiFailure('authentication'));
      expect(fetch).not.toHaveBeenCalled();
    } finally {
      session.authority.dispose();
      session.coordinator.dispose();
    }
  });

  it('requests local session recovery rather than loading principal without an access token', async () => {
    const get = vi.spyOn(ApiClient.prototype, 'get');
    const { session, restore } = setup();
    try {
      await session.authority.load();
      expect(restore).toHaveBeenCalledOnce();
      expect(get).not.toHaveBeenCalled();
      expect(session.authority.getSnapshot()).toEqual({ status: 'error', reason: 'network' });
    } finally {
      session.authority.dispose();
      session.coordinator.dispose();
    }
  });

  it('uses the verified principal read without restoring when access is usable', async () => {
    vi.spyOn(MemoryAccessToken.prototype, 'getAccessToken').mockReturnValue('synthetic.token');
    const get = vi.spyOn(ApiClient.prototype, 'get').mockResolvedValue({
      ok: true,
      value: principal,
      correlationId: null
    });
    const { session, restore } = setup();
    try {
      await session.authority.load();
      expect(session.authority.getSnapshot()).toEqual({ status: 'authenticated', principal });
      expect(get).toHaveBeenCalledExactlyOnceWith('/me', expect.any(Function), {
        access: 'required',
        signal: expect.any(AbortSignal) as unknown
      });
      expect(restore).not.toHaveBeenCalled();
    } finally {
      session.authority.dispose();
      session.coordinator.dispose();
    }
  });

  it.each([
    ['users', 'authentication'],
    ['users', 'authorization'],
    ['invitations', 'authentication'],
    ['invitations', 'authorization'],
    ['sessions', 'authentication'],
    ['sessions', 'authorization']
  ] as const)('routes %s %s rejection to the matching owner', async (feature, kind) => {
    vi.spyOn(ApiClient.prototype, 'get').mockResolvedValue(apiFailure(kind));
    const { session, restore } = setup();
    const changed = vi.spyOn(session.coordinator, 'authorityChanged').mockImplementation(() => {});
    vi.spyOn(session.authority, 'getSnapshot').mockReturnValue({
      status: 'authenticated',
      principal: { ...principal, permissions: ['READ_USERS'] }
    });
    vi.spyOn(session.authority, 'captureScope').mockReturnValue({
      signal: new AbortController().signal,
      isCurrent: () => true
    });
    try {
      const result =
        feature === 'invitations'
          ? await session.invitations.load(principal.userId)
          : await session[feature].load();
      expect(result).toEqual(apiFailure(kind));
      expect(restore).toHaveBeenCalledTimes(kind === 'authentication' ? 1 : 0);
      expect(changed).toHaveBeenCalledTimes(kind === 'authorization' ? 1 : 0);
    } finally {
      session.authority.dispose();
      session.coordinator.dispose();
    }
  });

  it.each(['authentication', 'authorization'] as const)(
    'routes issuance %s rejection to the matching owner',
    async (kind) => {
      vi.spyOn(ApiClient.prototype, 'mutate').mockResolvedValue(apiFailure(kind));
      const { session, restore } = setup();
      const changed = vi
        .spyOn(session.coordinator, 'authorityChanged')
        .mockImplementation(() => {});
      vi.spyOn(session.authority, 'getSnapshot').mockReturnValue({
        status: 'authenticated',
        principal: { ...principal, permissions: ['INVITE_USERS'] }
      });
      vi.spyOn(session.authority, 'captureScope').mockReturnValue({
        signal: new AbortController().signal,
        isCurrent: () => true
      });
      try {
        expect(await session.invitationIssuance.submit('recipient@example.test')).toEqual({
          kind: 'rejected',
          reason: kind
        });
        expect(restore).toHaveBeenCalledTimes(kind === 'authentication' ? 1 : 0);
        expect(changed).toHaveBeenCalledTimes(kind === 'authorization' ? 1 : 0);
      } finally {
        session.authority.dispose();
        session.coordinator.dispose();
      }
    }
  );

  it('invalidates only the issued target status and the directory after committed issuance', async () => {
    const userId = crypto.randomUUID();
    vi.spyOn(ApiClient.prototype, 'mutate').mockResolvedValue({
      ok: true,
      correlationId: null,
      value: { userId }
    });
    const { session } = setup();
    vi.spyOn(session.authority, 'getSnapshot').mockReturnValue({
      status: 'authenticated',
      principal: { ...principal, permissions: ['INVITE_USERS'] }
    });
    vi.spyOn(session.authority, 'captureScope').mockReturnValue({
      signal: new AbortController().signal,
      isCurrent: () => true
    });
    const directory = vi.fn();
    const target = vi.fn();
    const unrelated = vi.fn();
    const unsubscribes = [
      session.users.subscribe(directory),
      session.invitations.subscribe(userId, target),
      session.invitations.subscribe(crypto.randomUUID(), unrelated)
    ];
    try {
      expect(await session.invitationIssuance.submit('recipient@example.test')).toEqual({
        kind: 'accepted',
        userId
      });
      expect(directory).toHaveBeenCalledOnce();
      expect(target).toHaveBeenCalledOnce();
      expect(unrelated).not.toHaveBeenCalled();
    } finally {
      for (const unsubscribe of unsubscribes) unsubscribe();
      session.authority.dispose();
      session.coordinator.dispose();
    }
  });
});

describe('account document lifecycle', () => {
  it('restores through a live graph after Strict Mode replays the mount effect', async () => {
    const get = vi.spyOn(ApiClient.prototype, 'get').mockResolvedValue(apiFailure('network'));
    const dispose = vi.fn();
    const create = vi.fn(() => createAccountSession(runtime, () => ({ clear: vi.fn(), dispose })));
    const { result, unmount } = renderHook(() => useAccountSession(create), {
      wrapper: StrictMode
    });
    const session = result.current.session;
    if (!session) throw new Error('Expected a mounted session');
    expect(create).toHaveBeenCalledTimes(2);
    expect(dispose).toHaveBeenCalledOnce();
    await act(async () => {
      expect(await session.coordinator.restore()).not.toEqual(apiFailure('cancelled'));
    });
    expect(get).toHaveBeenCalled();

    unmount();
    expect(dispose).toHaveBeenCalledTimes(2);
    expect(await session.coordinator.restore()).toEqual(apiFailure('cancelled'));
  });

  it('pauses a hidden document and resumes once when it becomes visible', () => {
    const hidden = vi.spyOn(document, 'hidden', 'get').mockReturnValue(true);
    const { session, restore } = setup();
    const pause = vi.spyOn(session.coordinator, 'pause');
    const resume = vi.spyOn(session.coordinator, 'resume');
    const foreground = vi.spyOn(session.authority, 'setForeground');
    const { unmount } = renderHook(() => useAccountSession(() => session));
    expect(pause).toHaveBeenCalledOnce();
    expect(foreground).toHaveBeenLastCalledWith(false);
    expect(restore).toHaveBeenCalledOnce();

    void act(() => window.dispatchEvent(new Event('focus')));
    expect(resume).not.toHaveBeenCalled();
    hidden.mockReturnValue(false);
    void act(() => {
      document.dispatchEvent(new Event('visibilitychange'));
      window.dispatchEvent(new Event('focus'));
    });
    expect(resume).toHaveBeenCalledOnce();
    expect(foreground).toHaveBeenLastCalledWith(true);

    hidden.mockReturnValue(true);
    void act(() => document.dispatchEvent(new Event('visibilitychange')));
    expect(pause).toHaveBeenCalledTimes(2);
    expect(foreground).toHaveBeenLastCalledWith(false);
    unmount();
  });

  it('retires immediately on pagehide and stops listening to foreground changes', () => {
    const hidden = vi.spyOn(document, 'hidden', 'get').mockReturnValue(false);
    const { session, intent } = setup();
    const retireCoordinator = vi.spyOn(session.coordinator, 'dispose');
    const retireAuthority = vi.spyOn(session.authority, 'dispose');
    const clearSchemas = vi.spyOn(session.schemas, 'clear');
    const foreground = vi.spyOn(session.authority, 'setForeground');
    const { result } = renderHook(() => useAccountSession(() => session));
    expect(result.current.retired).toBe(false);

    void act(() => window.dispatchEvent(new Event('pagehide')));
    expect(result.current.retired).toBe(true);
    expect(result.current.session).toBeNull();
    expect(intent.dispose).toHaveBeenCalledOnce();
    expect(retireCoordinator).toHaveBeenCalledOnce();
    expect(retireAuthority).toHaveBeenCalledOnce();
    expect(clearSchemas).toHaveBeenCalledOnce();

    foreground.mockClear();
    hidden.mockReturnValue(true);
    void act(() => document.dispatchEvent(new Event('visibilitychange')));
    expect(foreground).not.toHaveBeenCalled();
  });
});
