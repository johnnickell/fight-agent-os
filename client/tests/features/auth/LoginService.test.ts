import { describe, expect, it, vi } from 'vitest';

import { jsonResponse } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';
import { loginDependencies, loginRejected, loginSuccess } from '../../loginFixtures';

const values = { email: 'Person@Example.test', password: 'é' };

describe('explicit memory-only login', () => {
  it.each([false, true])(
    'sends exact boolean remember %s, canonical ASCII email and unmodified existing password',
    async (remember) => {
      const post = vi.fn().mockResolvedValue(loginSuccess());
      const deps = loginDependencies(post);
      const result = await deps.service.submit(values, remember);
      expect(result).toEqual({ ok: true, value: true, correlationId: null });
      expect(post).toHaveBeenCalledOnce();
      const [url, init] = post.mock.calls[0] as [string, RequestInit];
      expect(url).toBe('/api/v1/auth/login');
      expect(JSON.parse(init.body as string)).toEqual({
        email: 'person@example.test',
        password: 'é',
        remember
      });
      expect(new Headers(init.headers).has('Authorization')).toBe(false);
      expect(deps.tokens.getAccessToken()).toBe('header.payload.signature');
      expect(JSON.stringify(deps.service)).not.toContain('header.payload.signature');
      expect(JSON.stringify(result)).not.toContain('header.payload.signature');
      expect(localStorage.length).toBe(0);
      expect(sessionStorage.length).toBe(0);
      deps.service.dispose();
      expect(deps.tokens.getAccessToken()).toBeNull();
    }
  );

  it('makes no automatic replay, coalesces pending user submissions and fences teardown success', async () => {
    const pending = deferred<Response>();
    const post = vi.fn().mockImplementation(() => pending.promise);
    const deps = loginDependencies(post);
    const first = deps.service.submit(values, false);
    await vi.waitFor(() => expect(post).toHaveBeenCalledOnce());
    expect((await deps.service.submit(values, true)).ok).toBe(false);
    deps.service.dispose();
    pending.resolve(loginSuccess());
    expect(await first).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(deps.tokens.getAccessToken()).toBeNull();
    expect(post).toHaveBeenCalledOnce();
    expect(await deps.service.submit(values, false)).toMatchObject({ ok: false });
  });

  it('rotates proof ownership after success and reboots only explicitly after a browser refusal', async () => {
    const post = vi
      .fn()
      .mockResolvedValueOnce(loginSuccess())
      .mockResolvedValueOnce(jsonResponse({ status: 'error', message: 'Forbidden.' }, 403, false))
      .mockResolvedValue(loginRejected());
    const deps = loginDependencies(post);
    await deps.service.submit(values, false);
    await deps.service.submit(values, false);
    await deps.service.submit(values, false);
    expect(deps.csrfCalls).toHaveLength(3);
    expect(post).toHaveBeenCalledTimes(3);
    deps.service.dispose();
  });

  it('rejects malformed success and refresh material without adopting credentials', async () => {
    const post = vi.fn().mockResolvedValue(
      jsonResponse({
        access_token: 'header.payload.signature',
        issued_at: 1000,
        expires_at: 1900,
        refresh_token: 'private'
      })
    );
    const deps = loginDependencies(post);
    expect(await deps.service.submit(values, false)).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(deps.tokens.getAccessToken()).toBeNull();
    deps.service.dispose();
  });
});
