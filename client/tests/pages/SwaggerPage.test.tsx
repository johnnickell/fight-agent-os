import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { mountSwagger } from '@/features/swagger/mountSwagger';
import { SwaggerService } from '@/features/swagger/SwaggerService';
import SwaggerPage from '@/pages/SwaggerPage';

import { principal } from '../authorityFixtures';

vi.mock('@/features/swagger/mountSwagger', () => ({ mountSwagger: vi.fn(() => vi.fn()) }));
const caches: AuthorityCache[] = [];
afterEach(() => {
  caches.splice(0).forEach((cache) => cache.dispose());
  vi.clearAllMocks();
});
async function fixture(available = true) {
  const cache = new AuthorityCache(() =>
    Promise.resolve({
      status: 'principal',
      principal: { ...principal, permissions: ['READ_SWAGGER'] }
    })
  );
  caches.push(cache);
  await cache.load();
  const fetch = vi.fn(() =>
    Promise.resolve(
      new Response('{"status":"error","message":"Internal server error."}', {
        status: 503,
        headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
      })
    )
  );
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: available },
    { fetch, accessTokens: { getAccessToken: () => 'synthetic.access.signature' } }
  );
  return {
    cache,
    fetch,
    service: new SwaggerService(
      api,
      cache,
      available,
      { accessRejected: () => {}, authorityChanged: () => {} },
      'https://example.test'
    )
  };
}
it('shows honest disabled availability without fetching or mounting the explorer', async () => {
  const f = await fixture(false);
  render(<SwaggerPage service={f.service} cache={f.cache} />);
  expect(screen.getByText('Documentation disabled')).toBeVisible();
  expect(f.fetch).not.toHaveBeenCalled();
  expect(mountSwagger).not.toHaveBeenCalled();
});
it('renders safe errors and only retries on explicit demand', async () => {
  const f = await fixture();
  render(<SwaggerPage service={f.service} cache={f.cache} />);
  expect(await screen.findByText('Documentation unavailable')).toBeVisible();
  expect(f.fetch).toHaveBeenCalledTimes(1);
  fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
  await waitFor(() => expect(f.fetch).toHaveBeenCalledTimes(2));
  expect(mountSwagger).not.toHaveBeenCalled();
});
it('does not mount a late response after navigation teardown', async () => {
  const f = await fixture();
  let resolve: ((response: Response) => void) | undefined;
  f.fetch.mockImplementation(
    () =>
      new Promise((done) => {
        resolve = done;
      })
  );
  const view = render(<SwaggerPage service={f.service} cache={f.cache} />);
  await waitFor(() => expect(resolve).toBeDefined());
  view.unmount();
  await act(async () => {
    resolve?.(
      new Response(
        JSON.stringify({
          status: 'success',
          data: { viewer_version: 1, document_path: '/api/v1/documentation/openapi' }
        }),
        { headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } }
      )
    );
    await Promise.resolve();
  });
  expect(f.fetch).toHaveBeenCalledTimes(1);
  expect(mountSwagger).not.toHaveBeenCalled();
});

it('mounts only a current complete document and retires the library on navigation', async () => {
  const f = await fixture();
  const document = {
    openapi: '3.0.3',
    info: { title: 'Private API', version: '1' },
    paths: {},
    'x-source-sha256': 'a'.repeat(64)
  };
  f.fetch.mockResolvedValueOnce(
    new Response(
      JSON.stringify({
        status: 'success',
        data: { viewer_version: 1, document_path: '/api/v1/documentation/openapi' }
      }),
      { headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } }
    )
  );
  f.fetch.mockResolvedValueOnce(
    new Response(JSON.stringify(document), {
      headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
    })
  );
  const view = render(<SwaggerPage service={f.service} cache={f.cache} />);
  await waitFor(() => expect(mountSwagger).toHaveBeenCalledOnce());
  expect(vi.mocked(mountSwagger).mock.calls[0]?.[1]).toEqual(document);
  const stop: unknown = vi.mocked(mountSwagger).mock.results[0]?.value;
  view.unmount();
  expect(stop).toHaveBeenCalledOnce();
});

it('contains renderer initialization failure without exposing its private error or partial document', async () => {
  const f = await fixture();
  const document = {
    openapi: '3.0.3',
    info: { title: 'Private API', version: '1' },
    paths: {},
    'x-source-sha256': 'a'.repeat(64)
  };
  const headers = { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' };
  f.fetch.mockResolvedValueOnce(
    new Response(
      JSON.stringify({
        status: 'success',
        data: { viewer_version: 1, document_path: '/api/v1/documentation/openapi' }
      }),
      { headers }
    )
  );
  f.fetch.mockResolvedValueOnce(new Response(JSON.stringify(document), { headers }));
  vi.mocked(mountSwagger).mockImplementationOnce(() => {
    throw new Error('private-renderer-marker');
  });
  const view = render(<SwaggerPage service={f.service} cache={f.cache} />);
  expect(await screen.findByText('Documentation unavailable')).toBeVisible();
  expect(view.container).not.toHaveTextContent('private-renderer-marker');
  expect(view.container.querySelector('.swagger-viewer')).not.toBeVisible();
});
