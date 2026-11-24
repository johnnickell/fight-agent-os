import { useEffect, useRef, useState } from 'react';

import { Button } from '../components/Button.js';
import { ContentState } from '../components/ContentState.js';
import { readUsers } from '../features/auth/AccountLinks.js';
import { evaluateAccess } from '../features/authority/evaluateAccess.js';
import { useAuthority } from '../features/authority/useAuthority.js';
import { UserAdministration } from '../features/users/UserAdministration.js';

import type { AuthorityCache, AuthorityScope } from '../features/authority/AuthorityCache.js';
import type { AdministrationNotice } from '../features/users/UserAdministration.js';
import type { UserAdministrationService } from '../features/users/UserAdministrationService.js';
import type { DirectoryUser } from '../features/users/UserDirectoryService.js';
import type { UserDirectoryService } from '../features/users/UserDirectoryService.js';

type State =
  | { kind: 'loading' }
  | { kind: 'retired' }
  | { kind: 'ready'; user: DirectoryUser; scope: AuthorityScope }
  | { kind: 'error'; reason: 'forbidden' | 'missing' | 'unavailable' };

/** Owns one freshly read detail, fenced by the sole authority scope and never navigation history. */
export default function UserDetail({
  userId,
  service,
  cache,
  onBack,
  administration,
  notice,
  onOutcome
}: {
  userId: string;
  service: Pick<UserDirectoryService, 'detail' | 'retry'> &
    Partial<Pick<UserDirectoryService, 'subscribe'>>;
  cache: AuthorityCache;
  onBack: () => void;
  administration?: UserAdministrationService | undefined;
  notice?: AdministrationNotice | undefined;
  onOutcome?: ((notice: AdministrationNotice) => void) | undefined;
}) {
  const authority = useAuthority(cache);
  const allowed = evaluateAccess(authority, readUsers) === 'allowed';
  const [retained, setState] = useState<State>({ kind: 'loading' });
  const [attempt, setAttempt] = useState(0);
  const scope = cache.captureScope();
  const state: State = !allowed
    ? { kind: 'retired' }
    : retained.kind === 'ready' && retained.scope !== scope
      ? { kind: 'loading' }
      : retained;
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    heading.current?.focus({ preventScroll: true });
  }, [attempt, userId]);
  useEffect(() => {
    const controller = new AbortController();
    let active = true;
    const retire = () => {
      controller.abort();
      if (active) setState({ kind: 'retired' });
    };
    if (
      !allowed ||
      !scope.isCurrent() ||
      evaluateAccess(cache.getSnapshot(), readUsers) !== 'allowed'
    )
      return;
    scope.signal.addEventListener('abort', retire, { once: true });
    const unsubscribe = service.subscribe?.(retire);
    void service.detail(userId, controller.signal).then((result) => {
      if (!active || controller.signal.aborted || !scope.isCurrent()) return;
      if (result.ok) setState({ kind: 'ready', user: result.value, scope });
      else if (result.error.kind !== 'cancelled')
        setState({
          kind: 'error',
          reason:
            result.error.kind === 'authorization'
              ? 'forbidden'
              : result.error.kind === 'not-found'
                ? 'missing'
                : 'unavailable'
        });
    });
    return () => {
      active = false;
      unsubscribe?.();
      scope.signal.removeEventListener('abort', retire);
      controller.abort();
    };
  }, [cache, service, userId, attempt, allowed, scope]);
  const retry = () => {
    if (evaluateAccess(cache.getSnapshot(), readUsers) !== 'allowed') return;
    service.retry();
    setState({ kind: 'loading' });
    setAttempt((value) => value + 1);
  };
  return (
    <section className="users-page" aria-labelledby="user-detail-heading">
      <h1 id="user-detail-heading" ref={heading} tabIndex={-1}>
        User detail
      </h1>
      <p>Current installation user state and permitted administrative actions.</p>
      {notice && <p role={notice.error ? 'alert' : 'status'}>{notice.message}</p>}
      <Button disabled={!allowed} onClick={onBack}>
        Back to user list
      </Button>
      {state.kind === 'loading' ? (
        <ContentState
          kind="loading"
          title="Loading user detail"
          message="Reading current safe user state."
        />
      ) : state.kind !== 'ready' ? (
        <ContentState
          kind="error"
          title={
            state.kind === 'retired'
              ? 'User detail cleared'
              : state.reason === 'forbidden'
                ? 'Access forbidden'
                : state.reason === 'missing'
                  ? 'User not found'
                  : 'User detail unavailable'
          }
          message="No detail is retained. Return to the directory or explicitly retry after verifying current access."
        />
      ) : (
        <dl className="user-dates">
          <div>
            <dt>User ID</dt>
            <dd>{state.user.userId}</dd>
          </div>
          <div>
            <dt>Email</dt>
            <dd className="users-email">{state.user.email}</dd>
          </div>
          <div>
            <dt>Lifecycle</dt>
            <dd>{state.user.state.replaceAll('_', ' ')}</dd>
          </div>
          <div>
            <dt>Roles</dt>
            <dd>
              {state.user.roles.length === 0 ? (
                'No roles assigned'
              ) : (
                <ul className="user-roles">
                  {state.user.roles.map((role) => (
                    <li key={role.name}>
                      {role.name} ({role.managed ? 'Managed' : 'Custom'})
                    </li>
                  ))}
                </ul>
              )}
            </dd>
          </div>
          {(
            [
              ['Created', state.user.createdAt],
              ['Updated', state.user.updatedAt]
            ] as const
          ).map(([label, value]) => (
            <div key={label}>
              <dt>{label} (UTC)</dt>
              <dd>
                <time dateTime={value}>{value.replace('T', ' ').replace('+00:00', ' UTC')}</time>
              </dd>
            </div>
          ))}
        </dl>
      )}
      {state.kind === 'ready' && administration && onOutcome && (
        <UserAdministration
          user={state.user}
          service={administration}
          cache={cache}
          onOutcome={onOutcome}
        />
      )}
      <Button disabled={!allowed || state.kind === 'loading'} onClick={retry}>
        {state.kind === 'ready' ? 'Reload user detail' : 'Retry user detail'}
      </Button>
    </section>
  );
}
