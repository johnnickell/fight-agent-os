import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { hasExactKeys } from '@/api/decodeResponse';

import type { ApiClient } from '@/api/ApiClient';
import type { AuthorityCache } from '@/features/authority/AuthorityCache';

function decodeChange(value: unknown): true | null {
  return hasExactKeys(value, ['changed']) && value.changed === true ? true : null;
}

/** Uses the sole authority graph; retains neither candidates nor a retry queue. */
export class PasswordChangeService {
  readonly #api: ApiClient;
  readonly #authority: AuthorityCache;
  readonly #terminal: () => void;
  readonly #changed: () => void;
  #pending = false;
  #completed = false;
  constructor(
    api: ApiClient,
    authority: AuthorityCache,
    terminal: () => void,
    changed: () => void = terminal
  ) {
    this.#api = api;
    this.#authority = authority;
    this.#terminal = terminal;
    this.#changed = changed;
  }
  completed(): boolean {
    return this.#completed;
  }
  clearCompletion(): void {
    this.#completed = false;
  }
  async submit(values: Readonly<Record<string, string>>): Promise<ApiResult<true>> {
    if (this.#pending) return apiFailure('cancelled');
    const scope = this.#authority.captureScope();
    if (!scope.isCurrent()) return apiFailure('authentication');
    this.#pending = true;
    try {
      const result = await this.#api.mutate(
        'POST',
        '/auth/password/change',
        {
          current_password: values.currentPassword ?? '',
          password: values.password ?? '',
          password_confirmation: values.passwordConfirmation ?? ''
        },
        decodeChange
      );
      if (result.ok) {
        // A committed mutation is terminal even after edits, reset, navigation or routine /me renewal.
        this.#completed = true;
        this.#changed();
      } else if (result.error.kind === 'authentication' && scope.isCurrent()) {
        this.#terminal();
      }
      return result;
    } finally {
      this.#pending = false;
    }
  }
}
