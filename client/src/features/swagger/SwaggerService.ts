import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { hasExactKeys } from '@/api/decodeResponse';
import { CatalogService, decodeCatalogRole } from '@/features/authority/CatalogService';
import { decodePrincipalResponse } from '@/features/authority/CurrentPrincipalService';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import {
  decodeSwaggerDocument,
  supportsSwaggerOperation
} from '@/features/swagger/SwaggerDocument';

import type { ApiClient } from '@/api/ApiClient';
import type { AuthorityCache, AuthorityScope } from '@/features/authority/AuthorityCache';
import type { SwaggerDocument } from '@/features/swagger/SwaggerDocument';
import type { SwaggerRequest, SwaggerResponse } from '@/features/swagger/SwaggerSystem';
import type { safeDiagnostics } from '@/SafeDiagnostics';

const requirements = [{ kind: 'permissions', all: ['READ_SWAGGER'] }] as const;
const uuid = '[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}';

/** Uses the sole application's fixed-path transport and context fences, never another credential store. */
export class SwaggerService {
  readonly #catalog: CatalogService;
  constructor(
    private readonly api: ApiClient,
    private readonly authority: AuthorityCache,
    readonly available: boolean,
    private readonly lifecycle: Readonly<{
      accessRejected: () => void;
      authorityChanged: () => void;
    }>,
    private readonly origin: string,
    private readonly diagnostics: ReturnType<typeof safeDiagnostics> = () => {}
  ) {
    this.#catalog = new CatalogService(api);
  }

  async load(scope: AuthorityScope): Promise<ApiResult<SwaggerDocument>> {
    if (!this.#allowed(scope)) return apiFailure('authorization');
    const bootstrap = await this.api.get(
      '/documentation/bootstrap',
      (value) =>
        hasExactKeys(value, ['viewer_version', 'document_path']) &&
        value.viewer_version === 1 &&
        value.document_path === '/api/v1/documentation/openapi'
          ? true
          : null,
      { access: 'required', signal: scope.signal }
    );
    if (!scope.isCurrent()) return apiFailure('cancelled');
    if (!bootstrap.ok) {
      this.#reject(bootstrap);
      return bootstrap;
    }
    const document = await this.api.getPrivateOpenApi(decodeSwaggerDocument, scope.signal);
    if (!scope.isCurrent()) return apiFailure('cancelled');
    this.#reject(document);
    this.diagnostics(document.ok ? 'documentation-ready' : 'documentation-unavailable');
    return document;
  }

  /** Sends at most one explicit supported operation; never forwards library headers or retries. */
  async execute(
    request: SwaggerRequest,
    document: SwaggerDocument,
    scope: AuthorityScope
  ): Promise<SwaggerResponse> {
    const denied = () => response(0, 'Operation unavailable. No request sent.');
    if (!this.#allowed(scope)) return denied();
    const target = this.#target(request, document);
    if (target === null) return denied();
    const { path, query, method } = target;
    let result: ApiResult<unknown>;
    if (method === 'post') {
      let body: unknown;
      try {
        if (typeof request.body !== 'string' || request.body.length > 4096) return denied();
        body = JSON.parse(request.body);
      } catch {
        return denied();
      }
      if (
        !hasExactKeys(body, ['name']) ||
        typeof body.name !== 'string' ||
        body.name.length > 255 ||
        !/^ROLE_[A-Z_]+$/.test(body.name)
      )
        return denied();
      // This endpoint uses Bearer JSON, not browser-cookie authority. Cookie/CSRF credential flows
      // remain unsupported here and stay with the shared session coordinator/CSRF service.
      result = await this.api.mutate('POST', '/roles', { name: body.name }, decodeCatalogRole, 201);
      if (scope.isCurrent()) {
        // Even uncertainty may mean a committed effect. Retire all private/form state, never replay.
        if (!result.ok && result.error.kind === 'authentication') this.lifecycle.accessRejected();
        else this.lifecycle.authorityChanged();
      }
      return response(0, 'View retired; the operation may have committed. No retry was sent.');
    }
    if (path === '/me')
      result = await this.api.get('/me', decodePrincipalResponse, {
        access: 'required',
        signal: scope.signal
      });
    else if (path === '/roles') result = await this.#catalog.roles(query, scope.signal);
    else if (path === '/permissions') result = await this.#catalog.permissions(query, scope.signal);
    else if (path.startsWith('/roles/'))
      result = await this.#catalog.role(path.slice(7), scope.signal);
    else result = await this.#catalog.permission(path.slice(13), scope.signal);
    if (!scope.isCurrent()) return response(0, 'View retired. No retry was sent.');
    this.#reject(result);
    this.diagnostics('documentation-operation');
    if (result.ok)
      return response(200, 'Completed', { status: 'success', data: wire(result.value) });
    return response(
      result.error.kind === 'authentication'
        ? 401
        : result.error.kind === 'authorization'
          ? 403
          : 0,
      'Operation failed. Retry only explicitly.'
    );
  }

  #allowed(scope: AuthorityScope): boolean {
    return (
      this.available &&
      scope.isCurrent() &&
      evaluateAccess(this.authority.getSnapshot(), requirements) === 'allowed'
    );
  }
  #reject(result: ApiResult<unknown>): void {
    if (!result.ok && result.error.kind === 'authentication') this.lifecycle.accessRejected();
    if (!result.ok && result.error.kind === 'authorization') this.lifecycle.authorityChanged();
  }
  #target(request: SwaggerRequest, document: SwaggerDocument) {
    if (
      typeof request.url !== 'string' ||
      request.url.length > 2048 ||
      request.url.startsWith('//') ||
      /[\\%#\s]/.test(request.url) ||
      [...request.url].some(
        (character) => character.charCodeAt(0) < 32 || character.charCodeAt(0) === 127
      ) ||
      /\/\.{1,2}(?:\/|$)/.test(request.url)
    )
      return null;
    let url: URL;
    try {
      url = new URL(request.url, this.origin);
    } catch {
      return null;
    }
    if (url.origin !== this.origin || url.username || url.password || url.hash) return null;
    const method = request.method?.toLowerCase() ?? 'get';
    const path = url.pathname;
    const template = path.replace(
      new RegExp(`/${uuid}$`),
      path.startsWith('/api/v1/roles/') ? '/{roleId}' : '/{permissionId}'
    );
    if (
      !supportsSwaggerOperation(template, method) ||
      !document.paths[template] ||
      !Object.hasOwn(document.paths[template], method)
    )
      return null;
    if (
      method === 'get' &&
      request.body !== undefined &&
      request.body !== null &&
      request.body !== ''
    )
      return null;
    const query = { page: 1, perPage: 25 };
    const parameters = [...url.searchParams];
    if (
      parameters.length &&
      (method !== 'get' || !['/api/v1/roles', '/api/v1/permissions'].includes(path))
    )
      return null;
    const seen = new Set<string>();
    for (const [key, value] of parameters) {
      if (seen.has(key) || !['page', 'per_page'].includes(key) || !/^[1-9][0-9]{0,3}$/.test(value))
        return null;
      seen.add(key);
      if (key === 'page') query.page = Number(value);
      else query.perPage = Number(value);
    }
    if (query.page > 1000 || query.perPage > 100) return null;
    return { path: path.slice('/api/v1'.length), query, method };
  }
}

/** Serializes only safe values constructed by existing exact feature codecs. */
function wire(value: unknown): unknown {
  const keys: Readonly<Record<string, string>> = {
    userId: 'user_id',
    roleId: 'role_id',
    permissionId: 'permission_id',
    perPage: 'per_page',
    totalPages: 'total_pages',
    totalRecords: 'total_records'
  };
  if (Array.isArray(value)) return value.map(wire);
  if (value !== null && typeof value === 'object')
    return Object.fromEntries(
      Object.entries(value).map(([key, child]) => [keys[key] ?? key, wire(child)])
    );
  return value;
}
function response(status: number, statusText: string, data: unknown = null): SwaggerResponse {
  return {
    url: '',
    status,
    statusText,
    headers: { 'content-type': 'application/json' },
    text: data === null ? statusText : JSON.stringify(data),
    data
  };
}
