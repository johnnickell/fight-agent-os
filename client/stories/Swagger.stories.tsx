import { useEffect, useState } from 'react';

import { expect, userEvent, waitFor, within } from 'storybook/test';

import { ApiClient } from '@/api/ApiClient';
import { Button } from '@/components/Button';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { SwaggerService } from '@/features/swagger/SwaggerService';
import SwaggerPage from '@/pages/SwaggerPage';

import type { Meta, StoryObj } from '@storybook/react-vite';

const document = {
  openapi: '3.0.3',
  info: { title: 'Disposable API catalog', version: '1' },
  'x-source-sha256': 'a'.repeat(64),
  servers: [{ url: '/' }],
  paths: {
    '/api/v1/roles': {
      post: {
        tags: ['Authority'],
        operationId: 'createCustomRole',
        summary: 'Create a custom Role',
        requestBody: {
          required: true,
          content: {
            'application/json': {
              schema: {
                type: 'object',
                required: ['name'],
                properties: { name: { type: 'string' } }
              }
            }
          }
        },
        responses: { 201: { description: 'Created Role' } }
      }
    },
    '/api/v1/me': {
      get: {
        tags: ['Identity'],
        operationId: 'readCurrentIdentity',
        summary: 'Read current identity',
        description: 'Uses current session authority only when explicitly executed.',
        responses: { 200: { description: 'Safe identity projection' } }
      }
    }
  }
};
function Example({
  available = true,
  unavailable = false,
  recheck = false
}: {
  available?: boolean;
  unavailable?: boolean;
  recheck?: boolean;
}) {
  const [state] = useState(() => {
    const userId = crypto.randomUUID();
    const cache = new AuthorityCache(() =>
      Promise.resolve({
        status: 'principal',
        principal: {
          userId,
          email: 'catalog@example.test',
          roles: [],
          permissions: ['READ_SWAGGER']
        }
      })
    );
    const api = new ApiClient(
      { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: available },
      {
        accessTokens: { getAccessToken: () => 'synthetic.catalog.access' },
        fetch: (url) =>
          Promise.resolve(
            new Response(
              JSON.stringify(
                unavailable
                  ? { status: 'error', message: 'Internal server error.' }
                  : url.endsWith('/bootstrap')
                    ? {
                        status: 'success',
                        data: { viewer_version: 1, document_path: '/api/v1/documentation/openapi' }
                      }
                    : url.endsWith('/openapi')
                      ? document
                      : {
                          status: 'success',
                          data: {
                            user_id: userId,
                            email: 'catalog@example.test',
                            roles: [],
                            permissions: ['READ_SWAGGER']
                          }
                        }
              ),
              {
                status: unavailable ? 503 : 200,
                headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
              }
            )
          )
      }
    );
    return {
      cache,
      service: new SwaggerService(
        api,
        cache,
        available,
        {
          accessRejected: () => {},
          authorityChanged: () => {
            void cache.signal('authority-changed');
          }
        },
        window.location.origin
      )
    };
  });
  const [ready, setReady] = useState(false);
  useEffect(() => {
    let current = true;
    void state.cache.load().then(() => {
      if (current) setReady(true);
    });
    return () => {
      current = false;
      state.cache.dispose();
    };
  }, [state]);
  return ready ? (
    <>
      {recheck && <Button onClick={state.cache.resume}>Recheck access</Button>}
      <SwaggerPage service={state.service} cache={state.cache} />
    </>
  ) : (
    <p>Checking access</p>
  );
}
const meta = { title: 'Application/Swagger', component: Example } satisfies Meta<typeof Example>;
export default meta;
type Story = StoryObj<typeof meta>;
export const CollapsedLight: Story = {
  play: async ({ canvasElement }) => {
    await waitFor(() => expect(canvasElement.querySelector('.opblock-tag')).toBeVisible());
    await expect(canvasElement.querySelector('.opblock')).toBeNull();
  }
};
export const CollapsedDark: Story = { ...CollapsedLight, parameters: { mode: 'dark' } };
export const NarrowDark: Story = {
  ...CollapsedDark,
  globals: { viewport: { value: 'narrow', isRotated: false } }
};
export const ExplicitRead: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await waitFor(() => expect(canvasElement.querySelector('.opblock-tag')).toBeVisible());
    const tag = canvasElement.querySelector<HTMLElement>('.opblock-tag[data-tag="Identity"]');
    if (!tag) throw new Error('Missing tag');
    await userEvent.click(tag);
    await userEvent.click(await canvas.findByRole('button', { name: /GET \/api\/v1\/me/i }));
    await userEvent.click(await canvas.findByRole('button', { name: 'Try it out' }));
    await userEvent.click(canvas.getByRole('button', { name: 'Execute' }));
    await waitFor(() => expect(canvasElement.querySelector('.live-responses-table')).toBeVisible());
    await expect(canvasElement.textContent).not.toContain('synthetic.catalog.access');
  }
};
export const CreateRoleInput: Story = {
  parameters: { mode: 'dark' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await waitFor(() =>
      expect(canvasElement.querySelector('.opblock-tag[data-tag="Authority"]')).toBeVisible()
    );
    const tag = canvasElement.querySelector<HTMLElement>('.opblock-tag[data-tag="Authority"]');
    if (!tag) throw new Error('Missing tag');
    await userEvent.click(tag);
    await userEvent.click(await canvas.findByRole('button', { name: /POST \/api\/v1\/roles/i }));
    await userEvent.click(await canvas.findByRole('button', { name: 'Try it out' }));
    await userEvent.clear(canvas.getByRole('textbox', { name: 'Request JSON body' }));
    await userEvent.type(
      canvas.getByRole('textbox', { name: 'Request JSON body' }),
      '{{"name":"ROLE_CATALOG_SAMPLE"}'
    );
  }
};
export const RenewedAuthority: Story = {
  args: { recheck: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await waitFor(() => expect(canvasElement.querySelector('.opblock-tag')).toBeVisible());
    const previous = canvasElement.querySelector<HTMLElement>('.opblock-tag[data-tag="Authority"]');
    if (!previous) throw new Error('Missing tag');
    await userEvent.click(previous);
    await userEvent.click(await canvas.findByRole('button', { name: /POST \/api\/v1\/roles/i }));
    await userEvent.click(await canvas.findByRole('button', { name: 'Try it out' }));
    await userEvent.type(
      canvas.getByRole('textbox', { name: 'Request JSON body' }),
      'private-draft-marker'
    );
    await userEvent.click(canvas.getByRole('button', { name: 'Recheck access' }));
    await waitFor(async () => {
      const current = canvasElement.querySelector('.opblock-tag');
      await expect(current).toBeVisible();
      await expect(previous.isConnected).toBe(false);
    });
    await expect(canvasElement.querySelector('.opblock')).toBeNull();
    await expect(canvasElement.textContent).not.toContain('private-draft-marker');
    await expect(canvasElement.textContent).not.toContain('synthetic.catalog.access');
  }
};
export const Disabled: Story = { args: { available: false } };
export const Unavailable: Story = { args: { unavailable: true } };
