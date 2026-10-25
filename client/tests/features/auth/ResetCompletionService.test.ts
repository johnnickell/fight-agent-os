import { describe, expect, it, vi } from 'vitest';

import { jsonResponse, passwordGuidance } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';
import { resetCompletionDependencies } from '../../resetCompletionFixtures';

const values = { password: 'synthetic-candidate', passwordConfirmation: 'synthetic-candidate' };
describe('reset completion transport and terminal ownership', () => {
  it('sends one protected mutation, no Authorization and no session response or stored authority', async () => {
    const notify = vi.fn();
    const post = vi.fn().mockResolvedValue(jsonResponse({ completed: true }));
    const d = resetCompletionDependencies(post, undefined, notify);
    expect((await d.service.submit(values)).ok).toBe(true);
    const [url, init] = post.mock.calls[0]! as [string, RequestInit];
    expect(url).toBe('/api/v1/auth/password-reset/complete');
    expect(init).toMatchObject({
      method: 'POST',
      cache: 'no-store',
      credentials: 'same-origin',
      mode: 'same-origin',
      redirect: 'error',
      referrerPolicy: 'no-referrer'
    });
    expect(new Headers(init.headers).has('Authorization')).toBe(false);
    expect(Object.keys(JSON.parse(init.body as string) as object)).toEqual([
      'user_id',
      'credential',
      'password',
      'password_confirmation'
    ]);
    expect(notify).toHaveBeenCalledExactlyOnceWith(d.userId);
    expect(d.credential.current()).toBeNull();
    expect(JSON.stringify(d.service)).toBe('{}');
    expect(localStorage.length + sessionStorage.length).toBe(0);
  });
  it('retains only retryable material and clears terminal failures without echoing candidate values', async () => {
    for (const status of [409, 429, 422, 500]) {
      const d = resetCompletionDependencies(() =>
        Promise.resolve(
          jsonResponse(
            status === 422
              ? { status: 'fail', data: { fields: { 'body.password': [passwordGuidance] } } }
              : {
                  status: 'error',
                  message:
                    status === 409
                      ? 'Conflict.'
                      : status === 429
                        ? 'Too many requests.'
                        : 'Internal server error.'
                },
            status,
            false
          )
        )
      );
      const result = await d.service.submit(values);
      expect(result.ok).toBe(false);
      expect(JSON.stringify(result)).not.toContain(values.password);
      expect(d.service.usable()).toBe(status !== 409);
      d.service.dispose();
      expect(d.credential.current()).toBeNull();
    }
  });
  it('preserves actual success and target invalidation after teardown, never restoring authority', async () => {
    const pending = deferred<Response>();
    const post = vi.fn(() => pending.promise);
    const notify = vi.fn();
    const d = resetCompletionDependencies(post, undefined, notify);
    const result = d.service.submit(values);
    await vi.waitFor(() => expect(post).toHaveBeenCalledOnce());
    d.service.dispose();
    pending.resolve(jsonResponse({ completed: true }));
    expect((await result).ok).toBe(true);
    expect(notify).toHaveBeenCalledExactlyOnceWith(d.userId);
    expect(d.credential.current()).toBeNull();
  });
  it('rejects token-bearing or extra completion responses and never retries', async () => {
    const post = vi
      .fn()
      .mockResolvedValue(jsonResponse({ completed: true, access_token: 'synthetic' }));
    const d = resetCompletionDependencies(post);
    expect(await d.service.submit(values)).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(post).toHaveBeenCalledOnce();
  });
});
