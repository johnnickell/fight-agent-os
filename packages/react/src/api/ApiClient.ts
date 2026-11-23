import { apiFailure, type ApiResult } from './ApiResult.js';
import { decodeResponse, type ResponseDecoder } from './decodeResponse.js';

import type { ApiConfiguration } from '../ApiConfiguration.js';

/**
 * Reads the current access credential from its volatile authentication owner
 */
export type AccessTokenProvider = Readonly<{
  getAccessToken: () => string | null;
}>;

export type CatalogOrdering = Readonly<{ sort: 'ID' | 'Created'; order: 'asc' | 'desc' }>;
export type PageOptions = Readonly<{ page: number; perPage: number; ordering?: CatalogOrdering }>;

export type GetOptions = Readonly<{
  signal?: AbortSignal;
  access?: 'required';
  /** Closed page and Access Control ordering options; no arbitrary query values. */
  pagination?: PageOptions;
}>;

export type ApiTransport = (url: string, init: RequestInit) => Promise<Response>;

/**
 * Owns same-origin GET and protected JSON transport without retries or persistence
 */
export class ApiClient {
  readonly #basePath: '/api/v1';
  readonly #fetch: ApiTransport;
  readonly #accessTokens: AccessTokenProvider | undefined;

  constructor(
    configuration: ApiConfiguration,
    dependencies: Readonly<{
      fetch?: ApiTransport;
      accessTokens?: AccessTokenProvider;
    }> = {}
  ) {
    if (configuration.schemaVersion !== 2 || configuration.apiBasePath !== '/api/v1') {
      throw new Error('Invalid API configuration');
    }
    this.#basePath = configuration.apiBasePath;
    this.#fetch = dependencies.fetch ?? globalThis.fetch.bind(globalThis);
    this.#accessTokens = dependencies.accessTokens;
  }

  /**
   * Resolves cancellation promptly even if a transport ignores AbortSignal
   */
  get<T>(
    path: string,
    decode: ResponseDecoder<T>,
    options: GetOptions = {}
  ): Promise<ApiResult<T>> {
    return this.#get(path, decode, options);
  }

  /** Reads bounded raw JSON from a same-origin API path using the current access token. */
  getProtectedJson<T>(
    path: string,
    decode: ResponseDecoder<T>,
    signal: AbortSignal
  ): Promise<ApiResult<T>> {
    return this.#get(path, decode, { access: 'required', signal }, true);
  }

  #get<T>(
    path: string,
    decode: ResponseDecoder<T>,
    options: GetOptions,
    rawJson = false
  ): Promise<ApiResult<T>> {
    const { signal } = options;
    if (signal?.aborted) return Promise.resolve(apiFailure('cancelled'));
    return new Promise((resolve) => {
      let finished = false;
      const finish = (result: ApiResult<T>) => {
        if (finished) return;
        finished = true;
        signal?.removeEventListener('abort', abort);
        resolve(result);
      };
      const abort = () => finish(apiFailure('cancelled'));
      signal?.addEventListener('abort', abort, { once: true });
      void this.#request(path, decode, options, undefined, rawJson).then(finish, () =>
        finish(apiFailure('system'))
      );
    });
  }

  /** Sends one explicit JSON mutation; never retries or cancels an observed server success. */
  post<T>(
    path: string,
    body: Readonly<Record<string, unknown>>,
    csrfProof: string,
    decode: ResponseDecoder<T>
  ): Promise<ApiResult<T>> {
    if (!/^[1-9][0-9]{0,9}\.[a-f0-9]{64}$/.test(csrfProof))
      return Promise.resolve(apiFailure('protocol'));
    return this.#request(path, decode, {}, { method: 'POST', body, csrfProof }).catch(() =>
      apiFailure('system')
    );
  }

  /** Sends one Bearer-authorized definition mutation without retry or cookie-derived authority. */
  mutate<T>(
    method: 'POST' | 'PATCH' | 'DELETE',
    path: string,
    body: Readonly<Record<string, unknown>> | null,
    decode: ResponseDecoder<T>,
    successStatus: 200 | 201 = 200
  ): Promise<ApiResult<T>> {
    return this.#request(
      path,
      decode,
      { access: 'required' },
      { method, body, successStatus }
    ).catch(() => apiFailure('system'));
  }

  async #request<T>(
    path: string,
    decode: ResponseDecoder<T>,
    options: GetOptions,
    mutation?: Readonly<{
      method: 'POST' | 'PATCH' | 'DELETE';
      body: Readonly<Record<string, unknown>> | null;
      csrfProof?: string;
      successStatus?: 200 | 201;
    }>,
    rawJson = false
  ): Promise<ApiResult<T>> {
    // Paths belong to feature services, not user input. No raw query, fragment,
    // encoding, dot segments, backslash, authority or alternative origin is supported.
    if (path.length > 2048 || !/^\/(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+(?![\s\S])/.test(path)) {
      return apiFailure('protocol');
    }
    let query = '';
    if (options.pagination) {
      const { page, perPage } = options.pagination;
      if (
        !Number.isInteger(page) ||
        page < 1 ||
        page > 1000 ||
        !Number.isInteger(perPage) ||
        perPage < 1 ||
        perPage > 100
      )
        return apiFailure('protocol');
      const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
      const ordering = options.pagination.ordering;
      if (ordering !== undefined) {
        if (
          (ordering.sort !== 'ID' && ordering.sort !== 'Created') ||
          (ordering.order !== 'asc' && ordering.order !== 'desc')
        )
          return apiFailure('protocol');
        params.set('sort', ordering.sort);
        params.set('order', ordering.order);
      }
      query = `?${params.toString()}`;
    }
    const headers = new Headers({ Accept: 'application/json' });
    headers.set('X-Correlation-ID', crypto.randomUUID().replaceAll('-', ''));
    if (options.access === 'required') {
      const token = this.#accessTokens?.getAccessToken();
      if (
        typeof token !== 'string' ||
        token.length > 8192 ||
        !/^[a-zA-Z0-9\-._~+/]+=*(?![\s\S])/.test(token)
      ) {
        return apiFailure('authentication');
      }
      headers.set('Authorization', `Bearer ${token}`);
    }
    if (mutation && mutation.body !== null) {
      headers.set('Content-Type', 'application/json');
      if (mutation.csrfProof) headers.set('X-CSRF-Proof', mutation.csrfProof);
    }
    let response: Response;
    try {
      response = await this.#fetch(`${this.#basePath}${path}${query}`, {
        method: mutation?.method ?? 'GET',
        headers,
        ...(mutation && mutation.body !== null ? { body: JSON.stringify(mutation.body) } : {}),
        credentials: 'same-origin',
        mode: 'same-origin',
        cache: 'no-store',
        redirect: 'error',
        referrerPolicy: 'no-referrer',
        ...(options.signal ? { signal: options.signal } : {})
      });
    } catch {
      return apiFailure('network');
    }
    if (options.signal?.aborted) return apiFailure('cancelled');
    const correlation = response.headers.get('X-Correlation-ID');
    const correlationId =
      correlation !== null && /^[a-f0-9]{32}(?![\s\S])/.test(correlation) ? correlation : null;
    if (
      response.redirected ||
      response.headers.get('Content-Type') !== 'application/json' ||
      response.headers.get('Cache-Control') !== 'no-store'
    ) {
      return apiFailure('protocol', correlationId);
    }
    let source: string;
    try {
      source = await response.text();
    } catch {
      return apiFailure('network', correlationId);
    }
    if (options.signal?.aborted) return apiFailure('cancelled');
    try {
      if (rawJson && source.length > 2_097_152) return apiFailure('protocol', correlationId);
      const envelope: unknown = JSON.parse(source);
      if (rawJson && response.status === 200) {
        const value = decode(envelope);
        return value === null
          ? apiFailure('protocol', correlationId)
          : { ok: true, value, correlationId };
      }
      return decodeResponse(
        response.status,
        envelope,
        decode,
        correlationId,
        mutation?.successStatus ?? 200
      );
    } catch {
      // JSON/parser/codec exceptions can contain credentials or entire response bodies.
      return apiFailure('protocol', correlationId);
    }
  }
}
