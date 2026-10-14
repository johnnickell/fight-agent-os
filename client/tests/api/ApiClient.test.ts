import { describe, expect, it, vi } from 'vitest';

import { ApiClient, type ApiTransport } from '@/api/ApiClient';
import { apiFailure } from '@/api/ApiResult';
import { decodeCsrfProof } from '@/features/auth/CsrfProofService';

import {
  configuration,
  correlationId,
  csrfData,
  deferred,
  expiresAt,
  jsonResponse
} from '../apiFixtures';

function setup(response = jsonResponse()) {
  const fetch = vi.fn<ApiTransport>().mockResolvedValue(response);
  const client = new ApiClient(configuration, { fetch });
  return { client, fetch };
}

// Future protected operations below exercise the shared transport only, not a server endpoint.
describe('shared API transport', () => {
  it('maps the real bootstrap and sends only permitted same-origin GET metadata', async () => {
    const { client, fetch } = setup();
    expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual({
      ok: true,
      value: { proof: csrfData.proof, expiresAt },
      correlationId
    });
    expect(fetch).toHaveBeenCalledTimes(1);
    const [url, init] = fetch.mock.calls[0]!;
    expect(url).toBe('/api/v1/auth/csrf');
    expect(init).toMatchObject({
      method: 'GET',
      credentials: 'same-origin',
      mode: 'same-origin',
      cache: 'no-store',
      redirect: 'error',
      referrerPolicy: 'no-referrer'
    });
    expect(init).not.toHaveProperty('body');
    const headers = new Headers(init.headers);
    expect([...headers.keys()]).toEqual(['accept', 'x-correlation-id']);
    expect(headers.get('Accept')).toBe('application/json');
    expect(headers.get('X-Correlation-ID')).toMatch(/^[a-f0-9]{32}$/);
  });

  it('uses browser fetch by default and snapshots its validated base configuration', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse());
    const config = { ...configuration };
    const client = new ApiClient(config);
    Object.assign(config, { apiBasePath: '//example.invalid' });
    expect((await client.get('/auth/csrf', decodeCsrfProof)).ok).toBe(true);
    expect(fetch.mock.calls[0]![0]).toBe('/api/v1/auth/csrf');
  });

  it.each([
    { schemaVersion: 2, apiBasePath: '/api/v1' },
    { schemaVersion: 1, apiBasePath: 'https://example.invalid/secret' }
  ])('rejects a bypassed configuration without echoing it %#', (config) => {
    const error = (() => {
      try {
        new ApiClient(config as typeof configuration);
      } catch (caught) {
        return caught;
      }
    })();
    expect(error).toEqual(new Error('Invalid API configuration'));
  });

  it.each([
    '',
    '/',
    '//example.invalid/private',
    'https://example.invalid',
    '/auth/../secret',
    '/auth/./csrf',
    '/auth/%2e%2e/secret',
    '/auth\\csrf',
    '/auth/csrf?secret=canary',
    '/auth/csrf#canary',
    '/auth/csrf\n',
    '/auth/csrf\r',
    '/auth/csrf\0',
    '/auth//csrf',
    `/${'x'.repeat(2048)}`
  ])('rejects unsafe or unsupported paths without dispatch %#', async (path) => {
    const { client, fetch } = setup();
    expect(await client.get(path, decodeCsrfProof)).toEqual(apiFailure('protocol'));
    expect(fetch).not.toHaveBeenCalled();
  });

  it('reads current memory credentials only for explicitly protected operations', async () => {
    let token = 'synthetic.first';
    const getAccessToken = vi.fn(() => token);
    const fetch = vi.fn<ApiTransport>().mockImplementation(() => Promise.resolve(jsonResponse()));
    const client = new ApiClient(configuration, {
      fetch,
      accessTokens: { getAccessToken }
    });
    await client.get('/auth/csrf', decodeCsrfProof);
    expect(getAccessToken).not.toHaveBeenCalled();
    await client.get('/auth/csrf', decodeCsrfProof, { access: 'required' });
    token = 'synthetic.second';
    await client.get('/auth/csrf', decodeCsrfProof, { access: 'required' });
    expect(
      fetch.mock.calls.map(([, init]) => new Headers(init.headers).get('Authorization'))
    ).toEqual([null, 'Bearer synthetic.first', 'Bearer synthetic.second']);
    expect(JSON.stringify(client)).toBe('{}');
  });

  it.each([null, '', ' ', 'Bearer value', 'value\n', 'value\r\nCookie: canary', 'a'.repeat(8193)])(
    'fails closed on missing or malformed memory access credentials %#',
    async (token) => {
      const fetch = vi.fn<ApiTransport>();
      const client = new ApiClient(configuration, {
        fetch,
        accessTokens: { getAccessToken: () => token }
      });
      expect(await client.get('/auth/csrf', decodeCsrfProof, { access: 'required' })).toEqual(
        apiFailure('authentication')
      );
      expect(fetch).not.toHaveBeenCalled();
    }
  );

  it('does not dispatch a protected request without a provider', async () => {
    const { client, fetch } = setup();
    expect(await client.get('/auth/csrf', decodeCsrfProof, { access: 'required' })).toEqual(
      apiFailure('authentication')
    );
    expect(fetch).not.toHaveBeenCalled();
  });

  it('sanitizes provider exceptions without retaining their cause', async () => {
    const fetch = vi.fn<ApiTransport>();
    const client = new ApiClient(configuration, {
      fetch,
      accessTokens: {
        getAccessToken: () => {
          throw new Error('synthetic-secret');
        }
      }
    });
    expect(await client.get('/auth/csrf', decodeCsrfProof, { access: 'required' })).toEqual(
      apiFailure('system')
    );
    expect(fetch).not.toHaveBeenCalled();
  });

  it.each([
    [400, 'Bad request.', 'bad-request'],
    [401, 'Unauthorized.', 'authentication'],
    [403, 'Forbidden.', 'authorization'],
    [404, 'Not found.', 'not-found'],
    [405, 'Method not allowed.', 'method-not-allowed'],
    [409, 'Conflict.', 'conflict'],
    [410, 'Gone.', 'gone'],
    [429, 'Too many requests.', 'rate-limited'],
    [500, 'Internal server error.', 'system'],
    [503, 'Internal server error.', 'system']
  ] as const)('normalizes HTTP %s without returning server text', async (status, message, kind) => {
    const { client, fetch } = setup(jsonResponse({ status: 'error', message }, status));
    expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual(
      apiFailure(kind, correlationId)
    );
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it.each([400, 422])(
    'retains only bounded structured validation %s detail for feature-owned safe mapping',
    async (status) => {
      const { client } = setup(
        jsonResponse(
          {
            status: 'fail',
            data: {
              fields: {
                'body.profile_name': ['synthetic-secret', 'Invalid input.']
              }
            }
          },
          status
        )
      );
      expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual(
        apiFailure('validation', correlationId, {
          'body.profile_name': ['synthetic-secret', 'Invalid input.']
        })
      );
    }
  );

  it.each<readonly [number, unknown]>([
    [200, null],
    [200, []],
    [200, true],
    [200, 1],
    [200, 'success'],
    [200, {}],
    [200, { status: 'success' }],
    [200, { status: 'success', data: csrfData, cookie: 'secret' }],
    [200, { status: 'success', data: null }],
    [200, { status: 'error', message: 'Forbidden.' }],
    [403, { status: 'success', data: csrfData }],
    [401, { status: 'error', message: 'Forbidden.' }],
    [500, { status: 'error', message: 'Internal path /secret' }],
    [500, { status: 'error', message: 'Internal server error.', data: 'secret' }],
    [418, { status: 'error', message: 'Internal server error.' }],
    [201, { status: 'success', data: csrfData }],
    [200, { status: 'fail', data: { fields: { body: ['Invalid input.'] } } }],
    [403, { status: 'fail', data: { fields: { body: ['Invalid input.'] } } }],
    ...[
      null,
      [],
      {},
      { fields: {} },
      { fields: [] },
      { fields: null },
      { fields: { body: [] } },
      { fields: { body: [''] } },
      { fields: { body: [1] } },
      { fields: { body: ['x', 'x'] } },
      { fields: { body: 'x' } },
      { fields: { 'bad field': ['x'] } },
      { fields: { 'body\n': ['x'] } },
      { fields: { body: ['x'] }, secret: 'canary' }
    ].map((data) => [400, { status: 'fail', data }] as const)
  ])('rejects malformed or contradictory envelopes %#', async (status, body) => {
    const { client } = setup(jsonResponse(body, status));
    expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual(
      apiFailure('protocol', correlationId)
    );
  });

  it.each(['{', '', '<html>synthetic-secret</html>'])(
    'rejects malformed JSON %#',
    async (source) => {
      const response = jsonResponse();
      vi.spyOn(response, 'text').mockResolvedValue(source);
      const { client } = setup(response);
      expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual(
        apiFailure('protocol', correlationId)
      );
    }
  );

  it.each([
    { 'Content-Type': 'text/html' },
    { 'Content-Type': 'application/problem+json' },
    { 'Content-Type': '' },
    { 'Cache-Control': 'public, max-age=300' },
    { 'Cache-Control': '' }
  ])('requires the documented JSON and no-store response headers %#', async (headers) => {
    const { client } = setup(jsonResponse(undefined, 200, headers));
    expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual(
      apiFailure('protocol', correlationId)
    );
  });

  it('rejects a followed redirect even from a custom transport', async () => {
    const response = jsonResponse();
    Object.defineProperty(response, 'redirected', { value: true });
    const { client } = setup(response);
    expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual(
      apiFailure('protocol', correlationId)
    );
  });

  it.each([
    null,
    'canary-secret',
    'a'.repeat(31),
    'A'.repeat(32),
    `${correlationId},${correlationId}`
  ])('discards absent or unsafe correlation %#', async (header) => {
    const response = jsonResponse();
    if (header === null) response.headers.delete('X-Correlation-ID');
    else response.headers.set('X-Correlation-ID', header);
    const { client } = setup(response);
    expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual({
      ok: true,
      value: { proof: csrfData.proof, expiresAt },
      correlationId: null
    });
  });

  it('sanitizes codec exceptions', async () => {
    const { client } = setup();
    expect(
      await client.get('/auth/csrf', () => {
        throw new Error('synthetic-secret');
      })
    ).toEqual(apiFailure('protocol', correlationId));
  });

  it('normalizes fetch rejection without retry or raw exception', async () => {
    const fetch = vi
      .fn<ApiTransport>()
      .mockRejectedValue(new Error('https://secret.invalid?token=canary'));
    const client = new ApiClient(configuration, { fetch });
    expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual(apiFailure('network'));
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('distinguishes body transport failure from malformed JSON', async () => {
    const response = jsonResponse();
    vi.spyOn(response, 'text').mockRejectedValue(new Error('synthetic-secret'));
    const { client } = setup(response);
    expect(await client.get('/auth/csrf', decodeCsrfProof)).toEqual(
      apiFailure('network', correlationId)
    );
  });
});

describe('cancellation', () => {
  it('does not dispatch already-aborted work', async () => {
    const { client, fetch } = setup();
    const controller = new AbortController();
    controller.abort('synthetic-secret');
    expect(
      await client.get('/auth/csrf', decodeCsrfProof, {
        signal: controller.signal
      })
    ).toEqual(apiFailure('cancelled'));
    expect(fetch).not.toHaveBeenCalled();
  });

  it.each(['success', 'failure'] as const)(
    'settles before an abort-ignoring fetch, ignores late %s',
    async (outcome) => {
      const pending = deferred<Response>();
      const fetch = vi.fn<ApiTransport>().mockReturnValue(pending.promise);
      const client = new ApiClient(configuration, { fetch });
      const controller = new AbortController();
      const remove = vi.spyOn(controller.signal, 'removeEventListener');
      const decode = vi.fn(decodeCsrfProof);
      const result = client.get('/auth/csrf', decode, {
        signal: controller.signal
      });
      expect(fetch.mock.calls[0]![1].signal).toBe(controller.signal);
      controller.abort(new Error('synthetic-secret'));
      expect(await result).toEqual(apiFailure('cancelled'));
      expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
      if (outcome === 'success') pending.resolve(jsonResponse());
      else pending.reject(new Error('synthetic-secret'));
      await pending.promise.catch(() => undefined);
      expect(decode).not.toHaveBeenCalled();
      expect(await result).toEqual(apiFailure('cancelled'));
    }
  );

  it('settles during a pending body and never decodes its late result', async () => {
    const body = deferred<string>();
    const response = jsonResponse();
    const text = vi.spyOn(response, 'text').mockReturnValue(body.promise);
    const { client } = setup(response);
    const decode = vi.fn(decodeCsrfProof);
    const controller = new AbortController();
    const result = client.get('/auth/csrf', decode, {
      signal: controller.signal
    });
    await Promise.resolve();
    expect(text).toHaveBeenCalledOnce();
    controller.abort();
    expect(await result).toEqual(apiFailure('cancelled'));
    body.resolve(JSON.stringify({ status: 'success', data: csrfData }));
    await body.promise;
    expect(decode).not.toHaveBeenCalled();
  });

  it('removes the abort listener after ordinary completion', async () => {
    const { client } = setup();
    const controller = new AbortController();
    const remove = vi.spyOn(controller.signal, 'removeEventListener');
    expect(
      (
        await client.get('/auth/csrf', decodeCsrfProof, {
          signal: controller.signal
        })
      ).ok
    ).toBe(true);
    expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
  });
});
