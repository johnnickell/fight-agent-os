import { describe, expect, it, vi } from 'vitest';

import { decodeSessionAccess } from '@/features/auth/ActivationService';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';

import { activationDependencies, jsonResponse, passwordGuidance } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';

const success = () =>
  jsonResponse({ access_token: 'header.payload.signature', issued_at: 1000, expires_at: 1900 });

describe('activation transport and volatile session ownership', () => {
  it('sends one protected no-store mutation and retains access only in its shared memory seam', async () => {
    const post = vi.fn().mockResolvedValue(success());
    const deps = activationDependencies(post);
    const result = await deps.service.submit({
      password: 'synthetic-password',
      passwordConfirmation: 'synthetic-password'
    });
    expect(result).toEqual({ ok: true, value: true, correlationId: null });
    expect(post).toHaveBeenCalledOnce();
    const [url, init] = post.mock.calls[0]! as [string, RequestInit];
    expect(url).toBe('/api/v1/auth/activate');
    expect(init).toMatchObject({
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      cache: 'no-store',
      redirect: 'error',
      referrerPolicy: 'no-referrer'
    });
    const headers = new Headers(init.headers);
    expect(headers.get('Content-Type')).toBe('application/json');
    expect(headers.get('X-CSRF-Proof')).toMatch(/^[0-9]+\.[a-f0-9]{64}$/);
    expect(headers.has('Authorization')).toBe(false);
    expect(headers.has('Cookie')).toBe(false);
    expect(Object.keys(JSON.parse(init.body as string) as object)).toEqual([
      'user_id',
      'credential',
      'password',
      'password_confirmation'
    ]);
    expect(deps.credential.current()).toBeNull();
    expect(deps.tokens.getAccessToken() === 'header.payload.signature').toBe(true);
    expect(JSON.stringify(deps.tokens)).toBe('{}');
    deps.service.dispose();
    expect(deps.tokens.getAccessToken()).toBeNull();
  });

  it('clears terminal grants, keeps retryable policy/throttle material transiently, and never echoes failures', async () => {
    for (const status of [409, 429, 422]) {
      const response =
        status === 422
          ? { status: 'fail', data: { fields: { 'body.password': [passwordGuidance] } } }
          : { status: 'error', message: status === 409 ? 'Conflict.' : 'Too many requests.' };
      const deps = activationDependencies(() =>
        Promise.resolve(jsonResponse(response, status, false))
      );
      const result = await deps.service.submit({
        password: 'marker',
        passwordConfirmation: 'marker'
      });
      expect(result.ok).toBe(false);
      expect(JSON.stringify(result).includes('marker')).toBe(false);
      expect(deps.service.usable()).toBe(status !== 409);
      expect(deps.tokens.getAccessToken()).toBeNull();
      deps.service.dispose();
    }
  });

  it('does not restore memory authority after unmount even if a server mutation completes', async () => {
    const pending = deferred<Response>();
    const deps = activationDependencies(() => pending.promise);
    const result = deps.service.submit({ password: 'marker', passwordConfirmation: 'marker' });
    await new Promise((resolve) => setTimeout(resolve, 0));
    deps.service.dispose();
    pending.resolve(success());
    expect((await result).ok).toBe(true);
    expect(deps.tokens.getAccessToken()).toBeNull();
    expect(deps.credential.current()).toBeNull();
  });

  it('rejects extra refresh material and unsafe session timing without retaining raw responses', () => {
    expect(
      decodeSessionAccess({
        access_token: 'header.payload.signature',
        issued_at: 1000,
        expires_at: 1900,
        refresh: 'private'
      })
    ).toBeNull();
    expect(
      decodeSessionAccess({
        access_token: 'header.payload.signature',
        issued_at: 1000,
        expires_at: 1901
      })
    ).toBeNull();
    expect(
      decodeSessionAccess({ access_token: 'invalid', issued_at: 1000, expires_at: 1900 })
    ).toBeNull();
    expect(
      decodeSessionAccess({
        access_token: 'header.payload.signature\n',
        issued_at: 1000,
        expires_at: 1900
      })
    ).toBeNull();
  });

  it('expires volatile authority conservatively without relying on the browser wall clock', () => {
    let now = 100;
    const tokens = new MemoryAccessToken(() => now);
    tokens.accept('synthetic', now);
    now += 839999;
    expect(tokens.getAccessToken()).not.toBeNull();
    ++now;
    expect(tokens.getAccessToken()).toBeNull();
  });
});
