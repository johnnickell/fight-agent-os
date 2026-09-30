import { describe, expect, it, vi } from 'vitest';

import { ApiClient, type ApiTransport } from '@/api/ApiClient';
import { apiFailure, type ApiResult } from '@/api/ApiResult';
import {
  type CsrfProof,
  CsrfProofService,
  decodeCsrfProof
} from '@/features/auth/CsrfProofService';

import {
  configuration,
  correlationId,
  csrfData,
  deferred,
  expiresAt,
  jsonResponse
} from '../../apiFixtures';

const model = Object.freeze({ proof: csrfData.proof, expiresAt });
const success: ApiResult<CsrfProof> = { ok: true, value: model, correlationId };

function setup(response = jsonResponse(), now: () => number = () => (expiresAt - 900) * 1000) {
  const fetch = vi.fn<ApiTransport>().mockResolvedValue(response);
  const client = new ApiClient(configuration, { fetch });
  const service = new CsrfProofService(client, now);
  return { client, service, fetch };
}

describe('CSRF bootstrap codec', () => {
  it('maps the exact proof/deadline to an immutable camelCase model', () => {
    expect(decodeCsrfProof(csrfData)).toEqual(model);
    expect(Object.isFrozen(decodeCsrfProof(csrfData))).toBe(true);
  });

  it.each([
    null,
    [],
    {},
    true,
    1,
    'proof',
    { proof: csrfData.proof },
    { expires_at: expiresAt },
    { ...csrfData, refresh_cookie: 'synthetic-cookie-secret' },
    { ...csrfData, expiresAt },
    ...[
      null,
      [],
      {},
      1,
      '',
      'canary',
      `${expiresAt}.${'A'.repeat(64)}`,
      `${expiresAt}.${'a'.repeat(63)}`,
      `0.${'a'.repeat(64)}`,
      `0${expiresAt}.${'a'.repeat(64)}`,
      `${csrfData.proof}\n`,
      `${expiresAt + 1}.${'a'.repeat(64)}`,
      `10000000000.${'a'.repeat(64)}`
    ].map((proof) => ({ ...csrfData, proof })),
    ...[null, [], {}, true, String(expiresAt), 0, -1, 1.5, NaN, Infinity, 10000000000].map(
      (expires_at) => ({ ...csrfData, expires_at })
    )
  ])('rejects invalid shapes, scalars, unknown fields and inconsistent proof expiry %#', (data) => {
    expect(decodeCsrfProof(data)).toBeNull();
  });
});

describe('volatile CSRF proof ownership', () => {
  it('starts empty, bootstraps only the real endpoint and clears without persistence', async () => {
    const { service, fetch } = setup();
    expect(service.current()).toBeNull();
    expect(await service.bootstrap()).toEqual(success);
    expect(service.current()).toEqual(model);
    expect(fetch.mock.calls[0]![0]).toBe('/api/v1/auth/csrf');
    expect(JSON.stringify(service)).toBe('{}');
    service.clear();
    expect(service.current()).toBeNull();
  });

  it('uses the default clock and expires at the exact Unix-second deadline', async () => {
    const now = vi.spyOn(Date, 'now').mockReturnValue(expiresAt * 1000 - 1);
    const fetch = vi.fn<ApiTransport>().mockResolvedValue(jsonResponse());
    const service = new CsrfProofService(new ApiClient(configuration, { fetch }));
    expect((await service.bootstrap()).ok).toBe(true);
    expect(service.current()).toEqual(model);
    now.mockReturnValue(expiresAt * 1000);
    expect(service.current()).toBeNull();
    now.mockReturnValue(expiresAt * 1000 - 1);
    expect(service.current()).toBeNull();
  });

  it.each([expiresAt * 1000, expiresAt * 1000 + 1, NaN, Infinity, -1])(
    'rejects expired or unusable-clock receipt %#',
    async (now) => {
      const { service } = setup(undefined, () => now);
      expect(await service.bootstrap()).toEqual(apiFailure('protocol', correlationId));
      expect(service.current()).toBeNull();
    }
  );

  it('removes an old proof immediately on replacement and propagates safe failure', async () => {
    const { service, fetch } = setup();
    await service.bootstrap();
    const pending = deferred<Response>();
    fetch.mockReturnValue(pending.promise);
    const second = service.bootstrap();
    expect(service.current()).toBeNull();
    pending.resolve(jsonResponse({ status: 'error', message: 'Forbidden.' }, 403));
    expect(await second).toEqual(apiFailure('authorization', correlationId));
    expect(service.current()).toBeNull();
  });

  it.each(['success', 'failure'] as const)(
    'ignores superseded fetch %s after newer proof',
    async (outcome) => {
      const first = deferred<Response>();
      const second = deferred<Response>();
      const { service, fetch } = setup();
      fetch.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
      const old = service.bootstrap();
      const current = service.bootstrap();
      expect(fetch.mock.calls[0]![1].signal?.aborted).toBe(true);
      second.resolve(jsonResponse());
      expect(await current).toEqual(success);
      if (outcome === 'success') first.resolve(jsonResponse());
      else first.reject(new Error('synthetic-secret'));
      expect(await old).toEqual(apiFailure('cancelled'));
      expect(service.current()).toEqual(model);
    }
  );

  it.each([success, apiFailure('authentication', correlationId)])(
    'fences an obsolete result independently of transport abort %#',
    async (obsolete) => {
      const { client, service } = setup();
      const first = deferred<ApiResult<CsrfProof>>();
      // A client double deliberately ignores cancellation: the service's generation
      // fence, not transport cooperation, must protect its owned state.
      vi.spyOn(client, 'get').mockReturnValueOnce(first.promise).mockResolvedValueOnce(success);
      const old = service.bootstrap();
      expect(await service.bootstrap()).toEqual(success);
      first.resolve(obsolete);
      expect(await old).toEqual(apiFailure('cancelled'));
      expect(service.current()).toEqual(model);
    }
  );

  it('does not repopulate after clear even if the client ignores abort', async () => {
    const { client, service } = setup();
    const pending = deferred<ApiResult<CsrfProof>>();
    vi.spyOn(client, 'get').mockReturnValue(pending.promise);
    const result = service.bootstrap();
    service.clear();
    pending.resolve(success);
    expect(await result).toEqual(apiFailure('cancelled'));
    expect(service.current()).toBeNull();
  });

  it('forwards external abort, removes its listener and permits an explicit later attempt', async () => {
    const { service, fetch } = setup();
    const pending = deferred<Response>();
    fetch.mockReturnValueOnce(pending.promise).mockResolvedValueOnce(jsonResponse());
    const controller = new AbortController();
    const remove = vi.spyOn(controller.signal, 'removeEventListener');
    const result = service.bootstrap(controller.signal);
    controller.abort('synthetic-secret');
    expect(await result).toEqual(apiFailure('cancelled'));
    expect(service.current()).toBeNull();
    expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
    expect(fetch.mock.calls[0]![1].signal?.aborted).toBe(true);
    expect(await service.bootstrap()).toEqual(success);
    pending.resolve(jsonResponse());
    await pending.promise;
    expect(service.current()).toEqual(model);
  });

  it('clears old state and avoids dispatch when already cancelled', async () => {
    const { service, fetch } = setup();
    await service.bootstrap();
    const controller = new AbortController();
    controller.abort();
    expect(await service.bootstrap(controller.signal)).toEqual(apiFailure('cancelled'));
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(service.current()).toBeNull();
  });

  it('rejects cancellation between client completion and state publication', async () => {
    const { client, service } = setup();
    const controller = new AbortController();
    vi.spyOn(client, 'get').mockImplementationOnce(() => {
      controller.abort();
      return Promise.resolve(success);
    });
    expect(await service.bootstrap(controller.signal)).toEqual(apiFailure('cancelled'));
    expect(service.current()).toBeNull();
  });
});

describe('secret-safe boundary', () => {
  it('never reads cookies or writes credentials, response data or raw diagnostics to browser sinks', async () => {
    const logs = ['log', 'info', 'warn', 'error', 'debug'].map((method) =>
      vi.spyOn(console, method as 'log').mockImplementation(() => undefined)
    );
    const getCookie = vi.spyOn(document, 'cookie', 'get');
    const setCookie = vi.spyOn(document, 'cookie', 'set');
    const getStorage = vi.spyOn(Storage.prototype, 'getItem');
    const setStorage = vi.spyOn(Storage.prototype, 'setItem');
    const pushHistory = vi.spyOn(history, 'pushState');
    const replaceHistory = vi.spyOn(history, 'replaceState');
    const token = 'synthetic.access-secret';
    const cookie = 'synthetic-cookie-secret';
    const response = jsonResponse(
      { status: 'success', data: { ...csrfData, refresh_cookie: cookie } },
      200,
      {
        'Set-Cookie': `future-refresh=${cookie}; Secure; HttpOnly`,
        Authorization: `Bearer ${token}`,
        'X-CSRF-Proof': csrfData.proof,
        'X-Correlation-ID': 'unsafe-secret-correlation'
      }
    );
    const getHeader = vi.spyOn(response.headers, 'get');
    const fetch = vi
      .fn<ApiTransport>()
      .mockResolvedValueOnce(response)
      .mockRejectedValueOnce(
        new Error(`${token} ${cookie} ${csrfData.proof} https://secret.invalid?secret=canary`)
      )
      .mockResolvedValueOnce(jsonResponse());
    const client = new ApiClient(configuration, {
      fetch,
      accessTokens: { getAccessToken: () => token }
    });
    const service = new CsrfProofService(client, () => (expiresAt - 1) * 1000);
    expect(await service.bootstrap()).toEqual(apiFailure('protocol'));
    expect(await client.get('/auth/csrf', decodeCsrfProof, { access: 'required' })).toEqual(
      apiFailure('network')
    );
    expect(await service.bootstrap()).toEqual(success);
    expect(getHeader.mock.calls.map(([name]) => name)).toEqual([
      'X-Correlation-ID',
      'Content-Type',
      'Cache-Control'
    ]);
    expect(JSON.stringify({ client, service })).toBe('{"client":{},"service":{}}');
    for (const sink of [
      ...logs,
      getCookie,
      setCookie,
      getStorage,
      setStorage,
      pushHistory,
      replaceHistory
    ]) {
      expect(sink).not.toHaveBeenCalled();
    }
    const publicHeaders = new Headers(fetch.mock.calls[0]![1].headers);
    expect(publicHeaders.has('Authorization')).toBe(false);
    expect(publicHeaders.has('Cookie')).toBe(false);
    expect(publicHeaders.has('X-CSRF-Proof')).toBe(false);
  });
});
