import { apiFailure } from '../../api/ApiResult.js';
import { readUsers } from '../auth/AccountLinks.js';
import { CatalogService } from '../authority/CatalogService.js';
import { evaluateAccess } from '../authority/evaluateAccess.js';
import { decodeSessionsPage } from '../sessions/OwnedSessionsService.js';

import type { ApiClient } from '../../api/ApiClient.js';
import type { ApiResult } from '../../api/ApiResult.js';
import type { AuthorityCache, AuthorityState } from '../authority/AuthorityCache.js';
import type { DirectoryUser } from './UserDirectoryService.js';

const idPattern =
  /^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}(?![\s\S])/;
const superAdmin = 'ROLE_SUPER_ADMIN';
const pageSize = 25;
const lastAdminPage = 1000;
export type AdminRole = Readonly<{ id: string; name: string; managed: boolean }>;
export type AdminAction =
  | { kind: 'disable' | 'enable' }
  | { kind: 'restore'; state: 'active' | 'pending_activation' }
  | { kind: 'assign' | 'remove'; role: AdminRole }
  | { kind: 'revoke'; sessionId: string };

export function adminAccess(authority: AuthorityState, ...all: string[]): boolean {
  return (
    evaluateAccess(authority, readUsers) === 'allowed' &&
    evaluateAccess(authority, [{ kind: 'permissions', all }]) === 'allowed'
  );
}

export function actionAllowed(authority: AuthorityState, user: DirectoryUser, action: AdminAction) {
  if (authority.status !== 'authenticated' || authority.principal.userId === user.userId)
    return false;
  switch (action.kind) {
    case 'disable':
      return user.state === 'active' && adminAccess(authority, 'DISABLE_USERS');
    case 'enable':
      return user.state === 'disabled' && adminAccess(authority, 'ENABLE_USERS');
    case 'restore':
      return user.state === 'deleted' && adminAccess(authority, 'RESTORE_USERS');
    case 'revoke':
      return adminAccess(authority, 'READ_SESSIONS', 'DELETE_SESSIONS');
    case 'assign':
    case 'remove': {
      const present = user.roles.some((role) => role.name === action.role.name);
      const elevated = action.role.name === superAdmin;
      return (
        user.state === 'active' &&
        adminAccess(authority, 'READ_ROLES', 'MANAGE_USER_ROLES') &&
        (action.kind === 'remove'
          ? present && !elevated
          : !present &&
            (!elevated || (action.role.managed && adminAccess(authority, 'ASSIGN_SUPER_ADMIN'))))
      );
    }
  }
}

export function safeAdminReason(reason: string): boolean {
  return (
    reason.trim().length > 0 &&
    reason.length <= 500 &&
    ![...reason].some((character) => character.charCodeAt(0) < 32 || character === '\u007f') &&
    !/:\/\/|password|token|credential|secret|authorization|bearer|ciphertext|cookie/iu.test(reason)
  );
}

/** Calls bounded installation endpoints; neither reads nor writes retain authority or credentials. */
export class UserAdministrationService {
  private readonly catalog: CatalogService;
  private readonly deniedReads = new Set<string | undefined>();
  private readonly pending = new Set<string>();
  private readonly listeners = new Set<() => void>();

  constructor(
    private readonly api: ApiClient,
    private readonly cache: AuthorityCache,
    private readonly refresh: (
      userId: string | undefined,
      outcome: 'committed' | 'access-rejected' | 'authority-rejected' | 'unconfirmed'
    ) => void
  ) {
    this.catalog = new CatalogService(api);
  }

  subscribe = (listener: () => void) => {
    this.listeners.add(listener);
    return () => {
      this.listeners.delete(listener);
    };
  };

  isPending(userId: string): boolean {
    return this.pending.has(userId);
  }

  retryRead(userId?: string): void {
    this.deniedReads.delete(userId);
  }

  private readRejected(userId: string | undefined, result: ApiResult<unknown>): void {
    if (result.ok || this.deniedReads.has(userId)) return;
    if (result.error.kind !== 'authentication' && result.error.kind !== 'authorization') return;
    this.deniedReads.add(userId);
    this.refresh(
      userId,
      result.error.kind === 'authentication' ? 'access-rejected' : 'authority-rejected'
    );
  }

  async roles(page: number, signal: AbortSignal) {
    const scope = this.cache.captureScope();
    if (
      !scope.isCurrent() ||
      !adminAccess(this.cache.getSnapshot(), 'READ_ROLES') ||
      this.deniedReads.has(undefined)
    )
      return apiFailure('authorization');
    const result = await this.catalog.roles({ page, perPage: pageSize }, signal);
    if (signal.aborted || !scope.isCurrent()) return apiFailure('cancelled');
    this.readRejected(undefined, result);
    if (!result.ok) return result;
    return {
      ...result,
      value: {
        rows: result.value.records.map(({ roleId, name, managed }) => ({
          id: roleId,
          name,
          managed
        })),
        totalPages: Math.min(lastAdminPage, result.value.totalPages)
      }
    };
  }

  async sessions(userId: string, page: number, signal: AbortSignal) {
    const scope = this.cache.captureScope();
    const authority = this.cache.getSnapshot();
    if (
      !scope.isCurrent() ||
      authority.status !== 'authenticated' ||
      authority.principal.userId === userId ||
      !adminAccess(authority, 'READ_SESSIONS') ||
      this.deniedReads.has(userId)
    )
      return apiFailure('authorization');
    if (!idPattern.test(userId)) return apiFailure('protocol');
    const result = await this.api.get(
      `/users/${userId}/sessions`,
      decodeSessionsPage(page, pageSize, 'other'),
      {
        access: 'required',
        pagination: { page, perPage: pageSize },
        signal
      }
    );
    if (signal.aborted || !scope.isCurrent()) return apiFailure('cancelled');
    this.readRejected(userId, result);
    if (!result.ok) return result;
    return {
      ...result,
      value: {
        rows: result.value.records,
        totalPages: Math.min(lastAdminPage, result.value.totalPages)
      }
    };
  }

  async change(
    user: DirectoryUser,
    action: AdminAction,
    reason: string,
    onSettled?: (result: ApiResult<true>) => void
  ): Promise<ApiResult<true>> {
    const scope = this.cache.captureScope();
    if (!scope.isCurrent() || !actionAllowed(this.cache.getSnapshot(), user, action))
      return apiFailure('authorization');
    if (!idPattern.test(user.userId)) return apiFailure('protocol');
    if (this.isPending(user.userId)) return apiFailure('conflict');
    let path = `/users/${user.userId}`;
    let method: 'POST' | 'PUT' | 'DELETE' = 'POST';
    let body: Readonly<Record<string, unknown>> = { confirm: true };
    switch (action.kind) {
      case 'assign':
      case 'remove':
        if (!idPattern.test(action.role.id)) return apiFailure('protocol');
        path += `/roles/${action.role.id}`;
        method = action.kind === 'assign' ? 'PUT' : 'DELETE';
        if (action.role.name === superAdmin) body = { ...body, confirm_super_admin: true };
        break;
      case 'revoke':
        if (!idPattern.test(action.sessionId) || !safeAdminReason(reason))
          return apiFailure('protocol');
        path += `/sessions/${action.sessionId}`;
        method = 'DELETE';
        body = { reason: reason.trim() };
        break;
      case 'restore':
        path += '/restore';
        body = { ...body, restoration_state: action.state };
        break;
      default:
        path += `/${action.kind}`;
    }
    const sameAuthentication = this.cache.captureAuthenticationContext();
    this.pending.add(user.userId);
    this.listeners.forEach((notify) => notify());
    try {
      const result = await this.api.mutate(method, path, body, (value) =>
        value === null ? (true as const) : null
      );
      const visible = scope.isCurrent();
      try {
        if (visible) onSettled?.(result);
      } finally {
        // A dispatched write can settle after navigation or principal refresh. Do not
        // adopt its response into another scope; retire affected views instead.
        this.refresh(
          user.userId,
          result.ok && sameAuthentication()
            ? 'committed'
            : !result.ok && result.error.kind === 'authentication' && sameAuthentication()
              ? 'access-rejected'
              : 'unconfirmed'
        );
      }
      return visible ? result : apiFailure('cancelled');
    } finally {
      this.pending.delete(user.userId);
      this.listeners.forEach((notify) => notify());
    }
  }
}
