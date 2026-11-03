import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority } from '@/features/authority/useAuthority';
import { matchApplicationRoute } from '@/routes/applicationRoutes';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type { DirectoryUser, UserDirectoryService } from '@/features/users/UserDirectoryService';

const requirements = matchApplicationRoute('/app/admin/users')?.requirements;
type State =
  | { kind: 'loading' }
  | { kind: 'retired' }
  | { kind: 'ready'; user: DirectoryUser }
  | { kind: 'error'; reason: 'forbidden' | 'missing' | 'unavailable' };

/** Owns one freshly read detail, fenced by the sole authority scope and never navigation history. */
export default function UserDetail({
  userId,
  service,
  cache,
  onBack
}: {
  userId: string;
  service: Pick<UserDirectoryService, 'detail' | 'retry'> &
    Partial<Pick<UserDirectoryService, 'subscribe'>>;
  cache: AuthorityCache;
  onBack: () => void;
}) {
  const authority = useAuthority(cache);
  const allowed = evaluateAccess(authority, requirements) === 'allowed';
  const [retained, setState] = useState<State>({ kind: 'loading' });
  const [attempt, setAttempt] = useState(0);
  const state: State = allowed ? retained : { kind: 'retired' };
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    heading.current?.focus({ preventScroll: true });
    const scope = cache.captureScope();
    const controller = new AbortController();
    let active = true;
    const retire = () => {
      controller.abort();
      if (active) setState({ kind: 'retired' });
    };
    if (!scope.isCurrent() || evaluateAccess(cache.getSnapshot(), requirements) !== 'allowed')
      return;
    scope.signal.addEventListener('abort', retire, { once: true });
    const unsubscribe = service.subscribe?.(retire);
    void service.detail(userId, controller.signal).then((result) => {
      if (!active || controller.signal.aborted || !scope.isCurrent()) return;
      if (result.ok) setState({ kind: 'ready', user: result.value });
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
  }, [cache, service, userId, attempt]);
  const retry = () => {
    if (evaluateAccess(cache.getSnapshot(), requirements) !== 'allowed') return;
    service.retry();
    setState({ kind: 'loading' });
    setAttempt((value) => value + 1);
  };
  return (
    <section className="users-page" aria-labelledby="user-detail-heading">
      <h1 id="user-detail-heading" ref={heading} tabIndex={-1}>
        User detail
      </h1>
      <p>
        Current installation user state. Read only; lifecycle and role changes are not available.
      </p>
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
      <Button disabled={!allowed || state.kind === 'loading'} onClick={retry}>
        {state.kind === 'ready' ? 'Reload user detail' : 'Retry user detail'}
      </Button>
    </section>
  );
}
