import { afterEach, describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { decodeSwaggerDocument } from '@/features/swagger/SwaggerDocument';
import { SwaggerService } from '@/features/swagger/SwaggerService';

import { principal } from '../../authorityFixtures';

const configuration = {
  schemaVersion: 2,
  apiBasePath: '/api/v1',
  appDebug: false,
  swaggerAvailable: true
} as const;
const document = {
  openapi: '3.0.3',
  info: { title: 'Private API', version: '1' },
  'x-source-sha256': 'a'.repeat(64),
  servers: [{ url: '/' }],
  paths: {
    '/api/v1/me': { get: {} },
    '/api/v1/roles': { get: {}, post: {} },
    '/api/v1/roles/{roleId}': { get: {} },
    '/api/v1/permissions': { get: {} },
    '/api/v1/permissions/{permissionId}': { get: {} }
  }
} as const;
const json = (value: unknown, status = 200) =>
  new Response(JSON.stringify(value), {
    status,
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
  });
const bootstrap = {
  status: 'success',
  data: { viewer_version: 1, document_path: '/api/v1/documentation/openapi' }
};
const identity = {
  status: 'success',
  data: { user_id: principal.userId, email: principal.email, roles: [], permissions: [] }
};
const owners: AuthorityCache[] = [];
afterEach(() => {
  for (const owner of owners.splice(0)) owner.dispose();
});
async function fixture(available = true, permissions = ['READ_SWAGGER']) {
  let token: string | null = 'synthetic.first';
  const authority = new AuthorityCache(() =>
    Promise.resolve({ status: 'principal', principal: { ...principal, permissions } })
  );
  owners.push(authority);
  await authority.load();
  const fetch = vi.fn<(url: string, init: RequestInit) => Promise<Response>>((url) =>
    Promise.resolve(
      json(url.endsWith('/bootstrap') ? bootstrap : url.endsWith('/openapi') ? document : identity)
    )
  );
  const api = new ApiClient(configuration, {
    fetch,
    accessTokens: { getAccessToken: () => token }
  });
  const lifecycle = { accessRejected: vi.fn(), authorityChanged: vi.fn() };
  const service = new SwaggerService(api, authority, available, lifecycle, 'https://example.test');
  return {
    authority,
    fetch,
    lifecycle,
    service,
    setToken: (value: string | null) => {
      token = value;
    }
  };
}

describe('guarded documentation transport', () => {
  it('reads bootstrap and complete raw document with latest credentials at each send', async () => {
    const f = await fixture();
    f.fetch.mockImplementation((url) => {
      const value = url.endsWith('/bootstrap') ? bootstrap : document;
      f.setToken('synthetic.latest');
      return Promise.resolve(json(value));
    });
    expect(await f.service.load(f.authority.captureScope())).toMatchObject({
      ok: true,
      value: document
    });
    expect(f.fetch.mock.calls.map((call) => call[0])).toEqual([
      '/api/v1/documentation/bootstrap',
      '/api/v1/documentation/openapi'
    ]);
    expect(new Headers(f.fetch.mock.calls[0]?.[1].headers).get('Authorization')).toBe(
      'Bearer synthetic.first'
    );
    expect(new Headers(f.fetch.mock.calls[1]?.[1].headers).get('Authorization')).toBe(
      'Bearer synthetic.latest'
    );
    for (const [, init] of f.fetch.mock.calls)
      expect(init).toMatchObject({
        credentials: 'same-origin',
        mode: 'same-origin',
        cache: 'no-store',
        redirect: 'error',
        referrerPolicy: 'no-referrer'
      });
  });
  it.each([
    [false, ['READ_SWAGGER']],
    [true, []]
  ] as const)(
    'does not fetch under unavailable eligibility or permission (%s)',
    async (available, permissions) => {
      const f = await fixture(available, [...permissions]);
      expect(await f.service.load(f.authority.captureScope())).toMatchObject({ ok: false });
      expect(f.fetch).not.toHaveBeenCalled();
    }
  );
  it('requires an exact protected bootstrap before fetching the document', async () => {
    const f = await fixture();
    f.fetch.mockResolvedValue(
      json({
        status: 'success',
        data: { viewer_version: 1, document_path: 'https://example.invalid/spec' }
      })
    );
    expect(await f.service.load(f.authority.captureScope())).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(f.fetch).toHaveBeenCalledTimes(1);
  });
  it.each([401, 403])(
    'handles server %s without replay and signals only the shared lifecycle',
    async (status) => {
      const f = await fixture();
      f.fetch.mockResolvedValue(
        json({ status: 'error', message: status === 401 ? 'Unauthorized.' : 'Forbidden.' }, status)
      );
      await f.service.load(f.authority.captureScope());
      expect(f.fetch).toHaveBeenCalledTimes(1);
      expect(f.lifecycle.accessRejected).toHaveBeenCalledTimes(status === 401 ? 1 : 0);
      expect(f.lifecycle.authorityChanged).toHaveBeenCalledTimes(status === 403 ? 1 : 0);
    }
  );
  it('fences even an abort-ignoring late document after logout', async () => {
    const f = await fixture();
    let resolve: ((response: Response) => void) | undefined;
    f.fetch.mockImplementation((url) =>
      url.endsWith('/bootstrap')
        ? Promise.resolve(json(bootstrap))
        : new Promise((done) => {
            resolve = done;
          })
    );
    const pending = f.service.load(f.authority.captureScope());
    await vi.waitFor(() => expect(resolve).toBeDefined());
    void f.authority.signal('logout');
    resolve?.(json(document));
    expect(await pending).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(f.lifecycle.accessRejected).not.toHaveBeenCalled();
  });
  it('uses the newest token for explicit safe reads without passing library headers', async () => {
    const f = await fixture();
    f.setToken('synthetic.newest');
    const result = await f.service.execute(
      { url: 'https://example.test/api/v1/me', method: 'GET' },
      document,
      f.authority.captureScope()
    );
    expect(result.status).toBe(200);
    expect(JSON.parse(result.text)).toEqual(identity);
    expect(result.text).not.toContain('synthetic.newest');
    expect(new Headers(f.fetch.mock.calls[0]?.[1].headers).get('Authorization')).toBe(
      'Bearer synthetic.newest'
    );
  });
  it.each([
    'https://foreign.test/api/v1/me',
    '//foreign.test/api/v1/me',
    'http://example.test/api/v1/me',
    'https://user:password@example.test/api/v1/me',
    '/api/v1/../v1/me',
    '/api/v1/%6de',
    '/api/v1/me#fragment',
    '/api/v1/me?access_token=marker',
    '/api/v1/me?x=1',
    '/api/v1/roles?page=1&page=2',
    '/api/v1/roles?page=0',
    '/api/v1/roles?per_page=101',
    '/api/v1/auth/login',
    '/api/v1/documentation/openapi',
    'javascript:alert(1)',
    '/api\\v1/me'
  ])('rejects an unsupported target before transport: %s', async (url) => {
    const f = await fixture();
    expect(
      (await f.service.execute({ url, method: 'GET' }, document, f.authority.captureScope())).status
    ).toBe(0);
    expect(f.fetch).not.toHaveBeenCalled();
  });
  it('refuses operations absent from the current owned document', async () => {
    const f = await fixture();
    await f.service.execute(
      { url: '/api/v1/me' },
      { ...document, paths: {} },
      f.authority.captureScope()
    );
    expect(f.fetch).not.toHaveBeenCalled();
  });
  it.each(['POST', 'PATCH', 'DELETE'])(
    'refuses unsupported %s without a request',
    async (method) => {
      const f = await fixture();
      await f.service.execute(
        { url: '/api/v1/me', method, body: '{}' },
        document,
        f.authority.captureScope()
      );
      expect(f.fetch).not.toHaveBeenCalled();
    }
  );
  it.each([
    '{"name":"ROLE_SAFE","password":"marker"}',
    '{',
    '{"name":"invalid"}',
    '{}',
    JSON.stringify({ name: 'ROLE_SAFE\n' })
  ])('rejects unsupported creation input without forwarding %s', async (body) => {
    const f = await fixture();
    await f.service.execute(
      { url: '/api/v1/roles', method: 'POST', body },
      document,
      f.authority.captureScope()
    );
    expect(f.fetch).not.toHaveBeenCalled();
  });
  it.each(['failure', 'uncertain'])(
    'sends one creation and retires authority on %s; never replays',
    async (outcome) => {
      const f = await fixture();
      f.fetch.mockImplementation(() =>
        outcome === 'failure'
          ? Promise.resolve(json({ status: 'error', message: 'Forbidden.' }, 403))
          : Promise.reject(new Error('private transport failure'))
      );
      const result = await f.service.execute(
        { url: '/api/v1/roles', method: 'POST', body: '{"name":"ROLE_DISPOSABLE"}' },
        document,
        f.authority.captureScope()
      );
      expect(f.fetch).toHaveBeenCalledTimes(1);
      expect(f.fetch.mock.calls[0]?.[1]).toMatchObject({
        method: 'POST',
        body: '{"name":"ROLE_DISPOSABLE"}',
        redirect: 'error'
      });
      expect(f.lifecycle.authorityChanged).toHaveBeenCalled();
      expect(result.text).toContain('may have committed');
      expect(result.text).not.toContain('private transport failure');
    }
  );
  it('does not display a late read response or let its rejection affect a replacement context', async () => {
    const f = await fixture();
    let resolve: ((response: Response) => void) | undefined;
    f.fetch.mockImplementation(
      () =>
        new Promise((done) => {
          resolve = done;
        })
    );
    const pending = f.service.execute({ url: '/api/v1/me' }, document, f.authority.captureScope());
    void f.authority.signal('credentials-changed');
    resolve?.(json({ status: 'error', message: 'Unauthorized.' }, 401));
    expect((await pending).text).toContain('retired');
    expect(f.lifecycle.accessRejected).not.toHaveBeenCalled();
  });
});

describe('private document decoder', () => {
  it('accepts the supported complete shape', () =>
    expect(decodeSwaggerDocument(document)).toBe(document));
  it.each([
    null,
    {},
    { ...document, openapi: '3.1.0' },
    { ...document, 'x-source-sha256': '' },
    { ...document, 'x-source-sha256': 'a'.repeat(64) + '\n' },
    { ...document, paths: { '/api/v1/me\n': { get: {} } } },
    { ...document, servers: [{ url: 'https://example.invalid' }] },
    { ...document, paths: { '/api/v1/me': { get: { servers: [{ url: '//external' }] } } } },
    {
      ...document,
      components: { schemas: { Unsafe: { $ref: 'https://example.invalid/schema' } } }
    },
    { ...document, paths: { '/other': {} } }
  ])('refuses unsafe or incomplete resolver input', (value) =>
    expect(decodeSwaggerDocument(value)).toBeNull()
  );
});
