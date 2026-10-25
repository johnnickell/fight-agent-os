import { describe, expect, it, vi } from 'vitest';

import { jsonResponse } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';
import { principal } from '../../authorityFixtures';
import { passwordChangeDependencies } from '../../passwordChangeFixtures';

import type { ApiTransport } from '@/api/ApiClient';
import type { PrincipalLoadResult } from '@/features/authority/AuthorityCache';

const values = {
  currentPassword: ' old value ',
  password: ' New example!9 ',
  passwordConfirmation: ' New example!9 '
};
describe('authenticated password-change service', () => {
  it('requires fresh sole-cache authority before dispatch and never retries', async () => {
    const post = vi.fn<ApiTransport>(() => Promise.resolve(jsonResponse({ changed: true })));
    const d = passwordChangeDependencies(post);
    expect((await d.service.submit(values)).ok).toBe(false);
    expect(post).not.toHaveBeenCalled();
    await d.authority.load();
    expect((await d.service.submit(values)).ok).toBe(true);
    expect(post).toHaveBeenCalledOnce();
    const [url, init] = post.mock.calls[0] ?? [];
    expect(url).toBe('/api/v1/auth/password/change');
    expect(new Headers(init?.headers).get('Authorization')).toBe(
      'Bearer synthetic.access.signature'
    );
    expect(new Headers(init?.headers).has('X-CSRF-Proof')).toBe(false);
    const body = init?.body;
    if (typeof body !== 'string') throw new Error('Expected a JSON request body');
    expect(JSON.parse(body)).toEqual({
      current_password: values.currentPassword,
      password: values.password,
      password_confirmation: values.passwordConfirmation
    });
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.authority.getSnapshot().status).toBe('terminal');
    expect(d.service.completed()).toBe(true);
    expect(JSON.stringify(d.service)).not.toContain(values.password);
    d.authority.dispose();
  });
  it('suppresses duplicates but cannot drop real success after authority expiry or local teardown', async () => {
    const pending = deferred<Response>();
    const post = vi.fn(() => pending.promise);
    const terminal = vi.fn();
    const d = passwordChangeDependencies(post, undefined, terminal);
    await d.authority.load();
    const first = d.service.submit(values);
    expect(await d.service.submit(values)).toMatchObject({
      ok: false,
      error: { kind: 'cancelled' }
    });
    await d.authority.signal('refresh-started');
    pending.resolve(jsonResponse({ changed: true }));
    expect((await first).ok).toBe(true);
    expect(terminal).toHaveBeenCalledOnce();
    expect(d.tokens.getAccessToken()).toBeNull();
    d.authority.dispose();
  });
  it('keeps still-authoritative sessions on safe rejection but handles 401 terminally', async () => {
    let status = 409;
    const d = passwordChangeDependencies(() =>
      Promise.resolve(
        jsonResponse(
          { status: 'error', message: status === 409 ? 'Conflict.' : 'Unauthorized.' },
          status,
          false
        )
      )
    );
    await d.authority.load();
    expect(await d.service.submit(values)).toMatchObject({
      ok: false,
      error: { kind: 'conflict' }
    });
    expect(d.tokens.getAccessToken()).not.toBeNull();
    expect(d.authority.getSnapshot().status).toBe('authenticated');
    status = 401;
    expect(await d.service.submit(values)).toMatchObject({
      ok: false,
      error: { kind: 'authentication' }
    });
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.authority.getSnapshot().status).toBe('terminal');
    d.authority.dispose();
  });
  it.each(['pending', 'completed'])(
    'handles 401 terminally after %s same-session principal renewal without replay',
    async (renewal) => {
      const pending = deferred<Response>();
      const principalRenewal = deferred<PrincipalLoadResult>();
      const post = vi.fn(() => pending.promise);
      const terminal = vi.fn();
      const loadPrincipal = vi.fn(() =>
        Promise.resolve<PrincipalLoadResult>({ status: 'principal', principal })
      );
      const d = passwordChangeDependencies(post, undefined, terminal, loadPrincipal);
      await d.authority.load();
      const result = d.service.submit(values);
      loadPrincipal.mockImplementation(() => principalRenewal.promise);
      d.authority.resume();
      if (renewal === 'completed') {
        principalRenewal.resolve({ status: 'principal', principal });
        await d.authority.load();
      }
      expect(d.authority.getSnapshot().status).toBe(
        renewal === 'completed' ? 'authenticated' : 'loading'
      );
      pending.resolve(jsonResponse({ status: 'error', message: 'Unauthorized.' }, 401, false));
      expect(await result).toMatchObject({ ok: false, error: { kind: 'authentication' } });
      expect(terminal).toHaveBeenCalledOnce();
      expect(d.tokens.getAccessToken()).toBeNull();
      expect(d.authority.getSnapshot().status).toBe('terminal');
      expect(post).toHaveBeenCalledOnce();
      expect(d.service.completed()).toBe(false);
      d.authority.dispose();
    }
  );
  it.each(['login', 'credentials', 'identity', 'refresh-started', 'logout', 'dispose'])(
    'ignores an old 401 after authentication context replacement or retirement: %s',
    async (replacement) => {
      const pending = deferred<Response>();
      const post = vi.fn(() => pending.promise);
      const terminal = vi.fn();
      const loadPrincipal = vi.fn(() =>
        Promise.resolve<PrincipalLoadResult>({ status: 'principal', principal })
      );
      const d = passwordChangeDependencies(post, undefined, terminal, loadPrincipal);
      await d.authority.load();
      const result = d.service.submit(values);
      if (replacement === 'login') {
        const attempt = d.authority.beginAuthentication();
        d.tokens.clear();
        d.tokens.accept('replacement.access.signature', performance.now());
        await attempt.acceptCredentials();
      } else if (replacement === 'credentials') {
        await d.authority.signal('refresh-started');
        d.tokens.clear();
        d.tokens.accept('replacement.access.signature', performance.now());
        await d.authority.signal('credentials-changed');
      } else if (replacement === 'identity') {
        loadPrincipal.mockResolvedValue({
          status: 'principal',
          principal: { ...principal, userId: crypto.randomUUID() }
        });
        await d.authority.signal('authority-changed');
      } else if (replacement === 'refresh-started') {
        d.tokens.clear();
        await d.authority.signal('refresh-started');
      } else if (replacement === 'logout') {
        d.tokens.clear();
        await d.authority.signal('logout');
      } else {
        d.tokens.clear();
        d.authority.dispose();
      }
      const state = d.authority.getSnapshot();
      const token = d.tokens.getAccessToken();
      pending.resolve(jsonResponse({ status: 'error', message: 'Unauthorized.' }, 401, false));
      expect(await result).toMatchObject({ ok: false, error: { kind: 'authentication' } });
      expect(terminal).not.toHaveBeenCalled();
      expect(d.authority.getSnapshot()).toBe(state);
      expect(d.tokens.getAccessToken()).toBe(token);
      expect(post).toHaveBeenCalledOnce();
      d.authority.dispose();
    }
  );
  it('does not accept token-shaped or extra response authority', async () => {
    const d = passwordChangeDependencies(() =>
      Promise.resolve(jsonResponse({ changed: true, access_token: 'synthetic' }))
    );
    await d.authority.load();
    expect(await d.service.submit(values)).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(d.service.completed()).toBe(false);
    d.authority.dispose();
  });
});
