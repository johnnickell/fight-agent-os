import { describe, expect, it, vi } from 'vitest';

import { jsonResponse } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';
import { passwordChangeDependencies } from '../../passwordChangeFixtures';

import type { ApiTransport } from '@/api/ApiClient';

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
