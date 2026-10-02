import { describe, expect, it, vi } from 'vitest';

import { ApiClient, type ApiTransport } from '@/api/ApiClient';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';

import { configuration, deferred, jsonResponse } from '../../apiFixtures';
import { fixture } from './fixtures';

const response = (value: unknown) => jsonResponse({ status: 'success', data: value });

describe('schema read boundary', () => {
  it('loads a public form once, caches only metadata and clears on demand', async () => {
    const fetch = vi.fn<ApiTransport>().mockResolvedValue(response(fixture));
    const service = new PublicSchemaService(new ApiClient(configuration, { fetch }));
    expect((await service.load('example_form')).ok).toBe(true);
    expect((await service.load('example_form')).ok).toBe(true);
    expect(fetch).toHaveBeenCalledOnce();
    expect(fetch.mock.calls[0]?.[0]).toBe('/api/v1/validations/example_form');
    service.clear();
    await service.load('example_form');
    expect(fetch).toHaveBeenCalledTimes(2);
    expect(JSON.stringify(service)).toBe('{}');
  });

  it('fails explicitly for absent, inconsistent, malformed and cancelled reads', async () => {
    const fetch = vi
      .fn<ApiTransport>()
      .mockResolvedValueOnce(jsonResponse({ status: 'error', message: 'Not found.' }, 404))
      .mockResolvedValueOnce(response({ ...fixture, form_name: 'other_form' }))
      .mockResolvedValueOnce(response({ ...fixture, schema_version: 99 }));
    const service = new PublicSchemaService(new ApiClient(configuration, { fetch }));
    expect(await service.load('../example')).toEqual({ ok: false, reason: 'unsupported' });
    expect(await service.load('example_form')).toEqual({ ok: false, reason: 'not-found' });
    expect(await service.load('example_form')).toEqual({ ok: false, reason: 'unsupported' });
    expect(await service.load('example_form')).toEqual({ ok: false, reason: 'protocol' });
    expect(fetch).toHaveBeenCalledTimes(3);
  });

  it('rejects a late abort-ignoring fetch result without filling the cache', async () => {
    const pending = deferred<Response>();
    const fetch = vi
      .fn<ApiTransport>()
      .mockReturnValueOnce(pending.promise)
      .mockResolvedValueOnce(response(fixture));
    const service = new PublicSchemaService(new ApiClient(configuration, { fetch }));
    const controller = new AbortController();
    const first = service.load('example_form', controller.signal);
    controller.abort();
    expect(await first).toEqual({ ok: false, reason: 'cancelled' });
    pending.resolve(response(fixture));
    await pending.promise;
    expect((await service.load('example_form')).ok).toBe(true);
    expect(fetch).toHaveBeenCalledTimes(2);
  });
});
