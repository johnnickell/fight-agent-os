import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, expect, it, vi } from 'vitest';

import { ShellApplication } from '@/ShellApplication';

import { jsonResponse } from '../activationFixtures';

const repositoryPath = '/api/v1/workspaces/{workspace_id}/repositories';
const contract = {
  openapi: '3.0.3',
  info: { title: 'Disposable API', version: '1' },
  'x-source-sha256': 'a'.repeat(64),
  paths: {
    [repositoryPath]: {
      get: { summary: 'List repositories', responses: { 200: { description: 'Repositories' } } }
    },
    '/api/v1/me': {
      get: { summary: 'Read identity', responses: { 200: { description: 'Identity' } } }
    }
  }
};

afterEach(() => {
  vi.unstubAllGlobals();
  window.history.replaceState(null, '', '/');
});

it('keeps real embedded operation links local, with keyboard expansion and no operation dispatch', async () => {
  const identity = crypto.randomUUID();
  const fetch = vi.fn((url: string) => {
    if (url.endsWith('/auth/csrf')) {
      const expiry = Math.floor(Date.now() / 1000) + 900;
      return Promise.resolve(
        jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
      );
    }
    if (url.endsWith('/auth/refresh'))
      return Promise.resolve(
        jsonResponse({
          access_token: 'synthetic.fixture.access',
          issued_at: 1000,
          expires_at: 1900
        })
      );
    if (url.endsWith('/me'))
      return Promise.resolve(
        jsonResponse({
          user_id: identity,
          email: 'reader@example.test',
          roles: [],
          permissions: ['READ_SWAGGER', 'VIEW_DASHBOARD']
        })
      );
    if (url.endsWith('/documentation/bootstrap'))
      return Promise.resolve(
        jsonResponse({ viewer_version: 1, document_path: '/api/v1/documentation/openapi' })
      );
    if (url.endsWith('/documentation/openapi'))
      return Promise.resolve(
        new Response(JSON.stringify(contract), {
          headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
        })
      );
    throw new Error('Unexpected operation dispatch');
  });
  vi.stubGlobal('fetch', fetch);
  vi.stubGlobal('scrollTo', vi.fn());
  window.history.replaceState(null, '', '/app/swagger');
  const { container } = render(
    <ShellApplication
      configuration='{"schema_version":2,"api_base_path":"/api/v1","app_debug":false,"swagger_available":true}'
      pathname="/app/swagger"
    />
  );
  const user = userEvent.setup();
  await waitFor(() => expect(container.querySelector('.opblock-tag')).toBeVisible());
  const tag = container.querySelector('.opblock-tag');
  if (!tag) throw new Error('Missing operation group');
  fireEvent.click(tag);
  const button = await screen.findByRole('button', {
    name: `GET ${repositoryPath} List repositories`
  });
  const anchor = button.querySelector('a');
  if (!anchor) throw new Error('Missing real upstream operation anchor');
  const historyLength = window.history.length;
  const requestCount = fetch.mock.calls.length;
  await user.click(anchor);
  expect(window.location.pathname).toBe('/app/swagger');
  expect(window.history.length).toBe(historyLength);
  expect(button).toHaveAttribute('aria-expanded', 'true');
  const operation = button.closest('.opblock');
  if (!(operation instanceof HTMLElement)) throw new Error('Missing operation');
  expect(within(operation).queryByRole('button', { name: 'Try it out' })).not.toBeInTheDocument();
  button.focus();
  await user.keyboard('{Enter}');
  expect(button).toHaveAttribute('aria-expanded', 'false');
  await user.keyboard(' ');
  expect(button).toHaveAttribute('aria-expanded', 'true');
  const identityButton = screen.getByRole('button', { name: 'GET /api/v1/me Read identity' });
  const identityAnchor = identityButton.querySelector('a');
  if (!identityAnchor) throw new Error('Missing identity anchor');
  await user.click(identityAnchor);
  expect(identityButton).toHaveAttribute('aria-expanded', 'true');
  expect(window.location.pathname).toBe('/app/swagger');
  expect(window.history.length).toBe(historyLength);
  expect(fetch).toHaveBeenCalledTimes(requestCount);
  // Let upstream's debounced schema resolution settle before retiring its private store.
  await new Promise((resolve) => setTimeout(resolve, 400));
});
