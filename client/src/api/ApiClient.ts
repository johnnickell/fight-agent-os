import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { decodeResponse, type ResponseDecoder } from '@/api/decodeResponse';

import type { RuntimeConfiguration } from '@/runtimeConfiguration';

/**
 * Reads the current access credential from its volatile authentication owner
 */
export type AccessTokenProvider = Readonly<{
  getAccessToken: () => string | null;
}>;

export type GetOptions = Readonly<{
  signal?: AbortSignal;
  access?: 'required';
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
    configuration: RuntimeConfiguration,
    dependencies: Readonly<{
      fetch?: ApiTransport;
      accessTokens?: AccessTokenProvider;
    }> = {}
  ) {
    if (configuration.schemaVersion !== 1 || configuration.apiBasePath !== '/api/v1') {
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
      void this.#request(path, decode, options).then(finish, () => finish(apiFailure('system')));
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
    return this.#request(path, decode, {}, { body, csrfProof }).catch(() => apiFailure('system'));
  }

  async #request<T>(
    path: string,
    decode: ResponseDecoder<T>,
    options: GetOptions,
    mutation?: Readonly<{ body: Readonly<Record<string, unknown>>; csrfProof: string }>
  ): Promise<ApiResult<T>> {
    // Paths belong to feature services, not user input. No query, fragment, encoding,
    // dot segments, backslash, authority or alternative origin is supported yet.
    if (path.length > 2048 || !/^\/(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+(?![\s\S])/.test(path)) {
      return apiFailure('protocol');
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
    if (mutation) {
      headers.set('Content-Type', 'application/json');
      headers.set('X-CSRF-Proof', mutation.csrfProof);
    }
    let response: Response;
    try {
      response = await this.#fetch(`${this.#basePath}${path}`, {
        method: mutation ? 'POST' : 'GET',
        headers,
        ...(mutation ? { body: JSON.stringify(mutation.body) } : {}),
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
      const envelope: unknown = JSON.parse(source);
      return decodeResponse(response.status, envelope, decode, correlationId);
    } catch {
      // JSON/parser/codec exceptions can contain credentials or entire response bodies.
      return apiFailure('protocol', correlationId);
    }
  }
}
