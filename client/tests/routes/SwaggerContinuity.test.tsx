import { act, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';

import { createApplicationSession } from '@/features/auth/useApplicationSession';
import { mountSwagger } from '@/features/swagger/mountSwagger';
import { ApplicationView } from '@/routes/ApplicationJourney';

import { jsonResponse } from '../activationFixtures';
import { deferred } from '../apiFixtures';

vi.mock('@/features/swagger/mountSwagger', () => ({ mountSwagger: vi.fn(() => vi.fn()) }));
const owners: ReturnType<typeof createApplicationSession>[] = [];
afterEach(() => {
  owners.splice(0).forEach((owner) => {
    owner.intent.dispose();
    owner.coordinator.dispose();
    owner.authority.dispose();
    owner.schemas.clear();
  });
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  vi.clearAllMocks();
});

const document = {
  openapi: '3.0.3' as const,
  info: { title: 'Private API', version: '1' },
  paths: { '/api/v1/me': { get: {} } },
  'x-source-sha256': 'a'.repeat(64)
};

async function mount() {
  const identity = crypto.randomUUID();
  let token = 0;
  const principal = () =>
    jsonResponse({
      user_id: identity,
      email: 'swagger@example.test',
      roles: ['ROLE_SUPER_ADMIN'],
      permissions: ['READ_SWAGGER']
    });
  const fetch = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>((url) => {
    if (url.endsWith('/auth/csrf')) {
      const expiry = Math.floor(Date.now() / 1000) + 900;
      return Promise.resolve(
        jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
      );
    }
    if (url.endsWith('/auth/refresh'))
      return Promise.resolve(
        jsonResponse({
          access_token: `header.payload.signature${++token}`,
          issued_at: 1000,
          expires_at: 1900
        })
      );
    if (url.endsWith('/me')) return Promise.resolve(principal());
    if (url.endsWith('/documentation/bootstrap'))
      return Promise.resolve(
        jsonResponse({ viewer_version: 1, document_path: '/api/v1/documentation/openapi' })
      );
    if (url.endsWith('/documentation/openapi'))
      return Promise.resolve(
        new Response(JSON.stringify(document), {
          headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
        })
      );
    throw new Error('Unexpected application request');
  });
  vi.stubGlobal('fetch', fetch);
  const owner = createApplicationSession({
    schemaVersion: 2,
    apiBasePath: '/api/v1',
    appDebug: false,
    swaggerAvailable: true
  });
  owners.push(owner);
  const navigate = vi.fn();
  render(<ApplicationView session={owner} pathname="/app/swagger" navigate={navigate} />);
  await act(() => owner.coordinator.restore());
  await waitFor(() => expect(mountSwagger).toHaveBeenCalledOnce());
  return { owner, fetch, principal, navigate };
}

it('retains the frame during background verification but retires and reloads private Swagger under fresh authority', async () => {
  const f = await mount();
  const main = screen.getByRole('main');
  const focus = screen.getByRole('button', { name: 'Sign out' });
  focus.focus();
  const scope = vi.mocked(mountSwagger).mock.calls[0]?.[3];
  const stop: unknown = vi.mocked(mountSwagger).mock.results[0]?.value;
  const pending = deferred<Response>();
  f.fetch.mockImplementationOnce(() => pending.promise);
  act(() => f.owner.authority.resume());
  expect(scope?.isCurrent()).toBe(false);
  expect(screen.getByRole('main')).toBe(main);
  expect(focus).toHaveFocus();
  expect(f.navigate).not.toHaveBeenCalled();
  await act(async () => {
    pending.resolve(f.principal());
    await f.owner.authority.load();
  });
  await waitFor(() => expect(mountSwagger).toHaveBeenCalledTimes(2));
  expect(stop).toHaveBeenCalledOnce();
  expect(vi.mocked(mountSwagger).mock.calls[1]?.[3].isCurrent()).toBe(true);
  expect(screen.getByRole('main')).toBe(main);
  expect(focus).toHaveFocus();
});

it('uses renewed memory credentials without replay and removes private data on logout during a late load', async () => {
  const f = await mount();
  const oldScope = vi.mocked(mountSwagger).mock.calls[0]?.[3];
  await act(() => f.owner.coordinator.restore());
  await waitFor(() => expect(mountSwagger).toHaveBeenCalledTimes(2));
  const scope = vi.mocked(mountSwagger).mock.calls[1]?.[3];
  if (!scope || !oldScope) throw new Error('Missing mounted scope');
  const before = f.fetch.mock.calls.length;
  await f.owner.swagger.execute({ url: '/api/v1/me', method: 'get' }, document, oldScope);
  expect(f.fetch).toHaveBeenCalledTimes(before);
  const response = await f.owner.swagger.execute(
    { url: '/api/v1/me', method: 'get' },
    document,
    scope
  );
  expect(response.status).toBe(200);
  expect(f.fetch).toHaveBeenCalledTimes(before + 1);
  const init = f.fetch.mock.calls.at(-1)?.[1];
  expect(new Headers(init?.headers).get('Authorization')).toBe('Bearer header.payload.signature2');
  const pending = deferred<Response>();
  f.fetch.mockImplementationOnce(() => pending.promise);
  act(() => f.owner.authority.resume());
  await act(() => f.owner.authority.signal('logout'));
  await act(async () => {
    pending.resolve(f.principal());
    await pending.promise;
  });
  expect(scope.isCurrent()).toBe(false);
  expect(mountSwagger).toHaveBeenCalledTimes(2);
  expect(screen.queryByRole('region', { name: 'Private API explorer' })).not.toBeInTheDocument();
});
