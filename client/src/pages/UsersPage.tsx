import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority } from '@/features/authority/useAuthority';
import { matchApplicationRoute } from '@/routes/applicationRoutes';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type {
  DirectoryPage,
  DirectoryUser,
  UserDirectoryService
} from '@/features/users/UserDirectoryService';

const usersRoute = matchApplicationRoute('/app/admin/users');
type State =
  | { kind: 'loading' }
  | { kind: 'retired' }
  | { kind: 'error'; forbidden: boolean }
  | { kind: 'ready'; data: DirectoryPage };

/** Owns one volatile page under the sole principal's scope, never browser persistence. */
export default function UsersPage({
  service,
  cache
}: {
  service: Pick<UserDirectoryService, 'load' | 'retry'>;
  cache: AuthorityCache;
}) {
  const authority = useAuthority(cache);
  const allowed = evaluateAccess(authority, usersRoute?.requirements) === 'allowed';
  const [page, setPage] = useState(1);
  const [attempt, setAttempt] = useState(0);
  const [retained, setState] = useState<State>({ kind: 'loading' });
  const state: State = allowed ? retained : { kind: 'retired' };
  const heading = useRef<HTMLHeadingElement>(null);
  const focusAfterRead = useRef(false);
  useEffect(() => {
    const scope = cache.captureScope();
    const controller = new AbortController();
    let active = true;
    const retire = () => {
      controller.abort();
      if (active) setState({ kind: 'retired' });
    };
    if (
      !scope.isCurrent() ||
      evaluateAccess(cache.getSnapshot(), usersRoute?.requirements) !== 'allowed'
    )
      return;
    scope.signal.addEventListener('abort', retire, { once: true });
    void service.load(page, 25, controller.signal).then((result) => {
      if (!active || controller.signal.aborted || !scope.isCurrent()) return;
      if (result.ok) setState({ kind: 'ready', data: result.value });
      else if (result.error.kind !== 'cancelled')
        setState({ kind: 'error', forbidden: result.error.kind === 'authorization' });
      if (focusAfterRead.current) {
        focusAfterRead.current = false;
        heading.current?.focus({ preventScroll: true });
      }
    });
    return () => {
      active = false;
      scope.signal.removeEventListener('abort', retire);
      controller.abort();
    };
  }, [service, cache, page, attempt]);
  const reload = () => {
    if (evaluateAccess(cache.getSnapshot(), usersRoute?.requirements) !== 'allowed') return;
    service.retry();
    focusAfterRead.current = true;
    setState({ kind: 'loading' });
    setAttempt((value) => value + 1);
  };
  const move = (next: number) => {
    if (evaluateAccess(cache.getSnapshot(), usersRoute?.requirements) !== 'allowed') return;
    focusAfterRead.current = true;
    setState({ kind: 'loading' });
    setPage(next);
  };
  return (
    <section className="users-page" aria-labelledby="users-heading">
      <header className="users-header">
        <div>
          <h1 id="users-heading" ref={heading} tabIndex={-1}>
            Users
          </h1>
          <p>Installation-wide identities and current role assignments.</p>
        </div>
        <nav aria-label="User invitation destinations" className="users-actions">
          <Destination cache={cache} path="/app/admin/invite">
            Invite User
          </Destination>
          <Destination cache={cache} path="/app/admin/invite-super-admin">
            Invite Super Admin
          </Destination>
        </nav>
      </header>
      <p>
        Includes pending, active, disabled and deleted users, oldest created first with a stable ID
        tie-break. Search, filtering, sorting and lifecycle or role changes are not available. All
        times are UTC.
      </p>
      <p>
        Invitation links open registered destinations that explain their unavailable workflows; no
        invitation is sent here.
      </p>
      {state.kind === 'loading' ? (
        <ContentState
          kind="loading"
          title="Loading users"
          message="Reading one authorized page of the directory."
        />
      ) : state.kind === 'error' || state.kind === 'retired' ? (
        <ContentState
          kind="error"
          title={
            state.kind === 'retired'
              ? 'User view cleared'
              : state.forbidden
                ? 'Access forbidden'
                : 'Users unavailable'
          }
          message="No user rows are retained. Retry explicitly after current access is verified."
        />
      ) : (
        <>
          <p role="status" aria-atomic="true">
            Page {state.data.page} of {state.data.totalPages}. {state.data.totalRecords}{' '}
            {state.data.totalRecords === 1 ? 'user' : 'users'}.
          </p>
          {state.data.records.length === 0 ? (
            <ContentState
              kind="empty"
              title="No users on this page"
              message="The directory may have changed. Return to the first page or reload to check again."
            />
          ) : (
            <table className="users-table" role="table">
              <caption>Installation users — current page</caption>
              <thead>
                <tr role="row">
                  <th scope="col">Email</th>
                  <th scope="col">Lifecycle</th>
                  <th scope="col">Roles</th>
                  <th scope="col">Dates (UTC)</th>
                  <th scope="col">Invitation</th>
                </tr>
              </thead>
              <tbody>
                {state.data.records.map((user) => (
                  <UserRow key={user.userId} user={user} cache={cache} />
                ))}
              </tbody>
            </table>
          )}
          <nav aria-label="User pages" className="users-pagination">
            <Button disabled={!allowed || page === 1} onClick={() => move(page - 1)}>
              Previous page
            </Button>
            <Button
              disabled={!allowed || page >= Math.min(state.data.totalPages, 1000)}
              onClick={() => move(page + 1)}
            >
              Next page
            </Button>
            {page !== 1 && (
              <Button disabled={!allowed} onClick={() => move(1)}>
                First page
              </Button>
            )}
          </nav>
          {state.data.totalPages > 1000 && (
            <p>Only the first 1,000 pages are available through this bounded directory.</p>
          )}
        </>
      )}
      <Button disabled={!allowed || state.kind === 'loading'} onClick={reload}>
        {state.kind === 'error' || state.kind === 'retired' ? 'Retry user list' : 'Reload users'}
      </Button>
    </section>
  );
}

function Destination({
  cache,
  path,
  children,
  label
}: {
  cache: AuthorityCache;
  path: string;
  children: React.ReactNode;
  label?: string;
}) {
  const authority = useAuthority(cache);
  const route = matchApplicationRoute(path);
  if (!route || evaluateAccess(authority, route.requirements) !== 'allowed') return null;
  return (
    <a
      href={route.pathname}
      {...(label ? { 'aria-label': label } : {})}
      onClick={(event) => {
        if (evaluateAccess(cache.getSnapshot(), route.requirements) !== 'allowed')
          event.preventDefault();
      }}
    >
      {children}
    </a>
  );
}

function UserRow({ user, cache }: { user: DirectoryUser; cache: AuthorityCache }) {
  const lifecycle = {
    pending_activation: 'Pending activation',
    active: 'Active',
    disabled: 'Disabled',
    deleted: 'Deleted'
  }[user.state];
  return (
    <tr role="row">
      <th scope="row" role="rowheader">
        <span className="users-email">{user.email}</span>
      </th>
      <td data-label="Lifecycle">
        <span className={`user-state user-state--${user.state}`}>{lifecycle}</span>
      </td>
      <td data-label="Roles">
        {user.roles.length === 0 ? (
          'No roles assigned'
        ) : (
          <ul className="user-roles">
            {user.roles.map((role) => (
              <li key={role.name}>
                {role.name} <span>({role.managed ? 'Managed' : 'Custom'})</span>
              </li>
            ))}
          </ul>
        )}
      </td>
      <td data-label="Dates (UTC)">
        <dl className="user-dates">
          {(
            [
              ['Created', user.createdAt],
              ['Updated', user.updatedAt]
            ] as const
          ).map(([label, value]) => (
            <div key={label}>
              <dt>{label}</dt>
              <dd>
                <time dateTime={value}>{value.replace('T', ' ').replace('+00:00', ' UTC')}</time>
              </dd>
            </div>
          ))}
        </dl>
      </td>
      <td data-label="Invitation">
        {user.state === 'pending_activation' ? (
          <Destination
            cache={cache}
            path="/app/admin/invitations"
            label={`Invitation status for ${user.email} (not available yet)`}
          >
            Invitation status <span>(not available yet)</span>
          </Destination>
        ) : (
          'Not pending'
        )}
      </td>
    </tr>
  );
}
