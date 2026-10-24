import { describe, expect, it, vi } from 'vitest';

import { jsonResponse } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';
import { resetAccepted, resetDependencies } from '../../resetRequestFixtures';

// The production service owns only the fixed public request, not eligibility or delivery.
describe('reset request service', () => {
  it.each([
    null,
    {},
    { accepted: false },
    { accepted: true, user_id: 'private' },
    [],
    { accepted: 'true' }
  ])('rejects malformed or identifying success %j', async (value) => {
    const deps = resetDependencies(() => Promise.resolve(jsonResponse(value)));
    const result = await deps.service.submit({ email: 'EXAMPLE@example.test' });
    expect(result).toMatchObject({ ok: false, error: { kind: 'protocol' } });
    deps.service.dispose();
  });
  it('suppresses concurrent submits but preserves an observed success after disposal', async () => {
    const pending = deferred<Response>();
    const post = vi.fn(() => pending.promise);
    const deps = resetDependencies(post);
    const first = deps.service.submit({ email: 'person@example.test' });
    await vi.waitFor(() => expect(post).toHaveBeenCalledOnce());
    expect(await deps.service.submit({ email: 'other@example.test' })).toMatchObject({
      ok: false,
      error: { kind: 'cancelled' }
    });
    deps.service.dispose();
    pending.resolve(resetAccepted());
    expect(await first).toMatchObject({ ok: true, value: true });
    expect(await deps.service.submit({ email: '' })).toMatchObject({
      ok: false,
      error: { kind: 'cancelled' }
    });
  });
  it('renews only CSRF protection after refusal and never automatically replays a mutation', async () => {
    const post = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ status: 'error', message: 'Forbidden.' }, 403, false))
      .mockResolvedValue(resetAccepted());
    const deps = resetDependencies(post);
    expect(await deps.service.submit({ email: '' })).toMatchObject({
      ok: false,
      error: { kind: 'authorization' }
    });
    expect(post).toHaveBeenCalledOnce();
    expect(await deps.service.submit({})).toMatchObject({ ok: true, value: true });
    expect(deps.calls.filter((url) => url.endsWith('/auth/csrf'))).toHaveLength(2);
    expect(JSON.parse((post.mock.calls[1]?.[1] as RequestInit).body as string)).toEqual({
      email: ''
    });
    deps.service.dispose();
  });
});
