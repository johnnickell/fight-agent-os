import { useEffect, useRef, useState } from 'react';

import { Button } from '../components/Button.js';
import { ContentState } from '../components/ContentState.js';
import {
  DataTable,
  TablePageSize,
  TablePageSummary,
  TablePagination
} from '../components/DataTable.js';
import { tableQueryKey } from '../components/tableQuery.js';
import { useTableLocation } from '../components/useTableLocation.js';
import { readUsers, useAccountLinks } from '../features/auth/AccountLinks.js';
import { evaluateAccess } from '../features/authority/evaluateAccess.js';
import { useAuthority } from '../features/authority/useAuthority.js';
import { usersTablePolicy as policy } from '../features/users/usersTablePolicy.js';
import UserDetail from './UserDetail.js';

import type { CatalogOrdering } from '../api/ApiClient.js';
import type { TableColumn, TableData } from '../components/DataTable.js';
import type { AuthorityCache, AuthorityScope } from '../features/authority/AuthorityCache.js';
import type { AdministrationNotice } from '../features/users/UserAdministration.js';
import type { UserAdministrationService } from '../features/users/UserAdministrationService.js';
import type {
  DirectoryUser,
  UserDirectoryService
} from '../features/users/UserDirectoryService.js';

type Sort = CatalogOrdering['sort'];
const loading: TableData<DirectoryUser> = { kind: 'loading' };

/** Owns directory navigation and reads under the current verified principal. */
export default function UsersPage({
  service,
  cache,
  locationRestoration,
  administration,
  detailId,
  onOpenDetail,
  onCloseDetail,
  notice,
  onOutcome
}: {
  service: Pick<UserDirectoryService, 'load' | 'detail' | 'retry'> &
    Partial<Pick<UserDirectoryService, 'subscribe'>>;
  cache: AuthorityCache;
  locationRestoration?: Readonly<{ invalid: boolean }> | undefined;
  administration?: UserAdministrationService | undefined;
  detailId?: string | undefined;
  onOpenDetail?: ((userId: string) => void) | undefined;
  onCloseDetail?: (() => void) | undefined;
  notice?: AdministrationNotice | undefined;
  onOutcome?: ((notice: AdministrationNotice) => void) | undefined;
}) {
  const authority = useAuthority(cache);
  const allowed = evaluateAccess(authority, readUsers) === 'allowed';
  const scope = cache.captureScope();
  const location = useTableLocation(policy, locationRestoration);
  const { query, current, restoreQuery } = location;
  const [selected, select] = useState<{
    id: string;
    scope: AuthorityScope;
    query: typeof query;
  } | null>(null);
  const activeDetail = !allowed
    ? null
    : (detailId ?? (selected?.scope === scope && selected.query === query ? selected.id : null));
  const [attempt, setAttempt] = useState(0);
  const [retained, setRetained] = useState<{
    query: typeof query;
    scope: AuthorityScope;
    attempt: number;
    data: TableData<DirectoryUser>;
  }>();
  const data =
    allowed && retained?.scope === scope && retained.query === query && retained.attempt === attempt
      ? retained.data
      : loading;
  const correction = useRef<{ key: string; scope: AuthorityScope; attempt: number } | null>(null);
  const heading = useRef<HTMLHeadingElement>(null);
  const focusAfterRead = useRef(false);
  const inFlight = useRef<AbortController | null>(null);
  useEffect(() => {
    if (!allowed || !scope.isCurrent() || activeDetail !== null) return;
    const key = tableQueryKey(query);
    const controller = new AbortController();
    inFlight.current = controller;
    const retire = () => controller.abort();
    scope.signal.addEventListener('abort', retire, { once: true });
    const publish = (next: TableData<DirectoryUser>) => {
      setRetained({ query, scope, attempt, data: next });
      if (focusAfterRead.current) {
        focusAfterRead.current = false;
        heading.current?.focus({ preventScroll: true });
      }
    };
    const isCurrent = () =>
      !controller.signal.aborted && scope.isCurrent() && current.current === query;
    const read = async () => {
      try {
        const result = await service.load(query.page, query.perPage, controller.signal, {
          sort: query.sort,
          order: query.order
        });
        if (!isCurrent()) return;
        if (!result.ok) {
          if (result.error.kind !== 'cancelled')
            publish({
              kind: 'error',
              title:
                result.error.kind === 'authorization' ? 'Access forbidden' : 'Users unavailable',
              message: 'No user rows are retained. Retry after current access is verified.'
            });
          return;
        }
        const lastPage = Math.max(1, Math.ceil(result.value.totalRecords / query.perPage));
        if (query.page > lastPage) {
          const previous = correction.current;
          if (previous?.key === key && previous.scope === scope && previous.attempt === attempt) {
            publish({
              kind: 'error',
              title: 'The user directory changed again',
              message: 'Reload users to check the current page.'
            });
          } else {
            const corrected = { ...query, page: lastPage };
            correction.current = { key: tableQueryKey(corrected), scope, attempt };
            restoreQuery(corrected);
          }
          return;
        }
        correction.current = null;
        publish({ kind: 'ready', rows: result.value.records, total: result.value.totalRecords });
      } catch {
        if (isCurrent())
          publish({
            kind: 'error',
            title: 'Users unavailable',
            message: 'Reload users to try again.'
          });
      }
    };
    void read();
    return () => {
      scope.signal.removeEventListener('abort', retire);
      controller.abort();
    };
  }, [service, scope, allowed, query, current, restoreQuery, attempt, activeDetail]);
  useEffect(() => {
    if (!allowed || !scope.isCurrent()) return;
    const retire = () =>
      queueMicrotask(() => {
        setRetained((saved) => (saved?.scope === scope ? undefined : saved));
        select((saved) => (saved?.scope === scope ? null : saved));
      });
    scope.signal.addEventListener('abort', retire, { once: true });
    return () => scope.signal.removeEventListener('abort', retire);
  }, [allowed, scope]);
  useEffect(
    () =>
      service.subscribe?.(() => {
        inFlight.current?.abort();
        setRetained(undefined);
        setAttempt((value) => value + 1);
      }),
    [service]
  );

  const admitted = () =>
    scope.isCurrent() && evaluateAccess(cache.getSnapshot(), readUsers) === 'allowed';
  const reload = () => {
    if (!admitted()) return;
    inFlight.current?.abort();
    service.retry();
    focusAfterRead.current = true;
    setAttempt((value) => value + 1);
  };
  if (activeDetail !== null)
    return (
      <UserDetail
        key={activeDetail}
        userId={activeDetail}
        service={service}
        cache={cache}
        administration={administration}
        notice={notice}
        onOutcome={onOutcome}
        onBack={() => {
          if (!admitted()) return;
          if (detailId && onCloseDetail) {
            onCloseDetail();
            return;
          }
          focusAfterRead.current = true;
          select(null);
        }}
      />
    );
  const columns: TableColumn<DirectoryUser, Sort>[] = [
    {
      key: 'email',
      label: 'Email',
      rowHeader: (user) => user.email,
      cell: (user) => (
        <>
          <span className="users-email">{user.email}</span>{' '}
          <Button
            aria-label={`View details for ${user.email}`}
            onClick={() => {
              if (!admitted()) return;
              setRetained(undefined);
              if (onOpenDetail) onOpenDetail(user.userId);
              else select({ id: user.userId, scope, query });
            }}
          >
            View details
          </Button>
        </>
      )
    },
    { key: 'identity', label: 'ID', sort: 'ID', cell: (user) => user.userId },
    {
      key: 'state',
      label: 'Lifecycle',
      cell: (user) => (
        <span className={`user-state user-state--${user.state}`}>
          {
            {
              pending_activation: 'Pending activation',
              active: 'Active',
              disabled: 'Disabled',
              deleted: 'Deleted'
            }[user.state]
          }
        </span>
      )
    },
    {
      key: 'roles',
      label: 'Roles',
      cell: (user) =>
        user.roles.length === 0 ? (
          'No roles assigned'
        ) : (
          <ul className="user-roles">
            {user.roles.map((role) => (
              <li key={role.name}>
                {role.name} <span>({role.managed ? 'Managed' : 'Custom'})</span>
              </li>
            ))}
          </ul>
        )
    },
    {
      key: 'created',
      label: 'Created (UTC)',
      sort: 'Created',
      cell: (user) => <DateValue value={user.createdAt} />
    },
    {
      key: 'updated',
      label: 'Updated (UTC)',
      cell: (user) => <DateValue value={user.updatedAt} />
    },
    {
      key: 'invitation',
      label: 'Invitation',
      cell: (user) =>
        user.state === 'pending_activation' ? (
          <Destination
            cache={cache}
            destination="invitation"
            userId={user.userId}
            label={`Invitation status for ${user.email}`}
          >
            Invitation status
          </Destination>
        ) : (
          'Not pending'
        )
    }
  ];
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
          <Destination cache={cache} destination="invite">
            Invite User
          </Destination>
          <Destination cache={cache} destination="inviteElevated">
            Invite Super Admin
          </Destination>
        </nav>
      </header>
      <p>
        Includes pending, active, disabled and deleted users. Search and filtering are not
        available.
      </p>
      <p>
        Invitation status shows current user and delivery state. This read does not send an
        invitation.
      </p>
      {location.notice && (
        <p role="alert">
          Some directory options in the URL were invalid or unsupported and were reset.
        </p>
      )}
      {allowed ? (
        <DataTable
          policy={policy}
          initial={query}
          restore={location.restore}
          columns={columns}
          rowKey={(user) => user.userId}
          className="users-table"
          caption="Installation users — current page"
          data={data}
          onChange={(next, reason) => {
            if (!admitted()) return;
            inFlight.current?.abort();
            correction.current = null;
            focusAfterRead.current = reason === 'page';
            location.change(next, reason);
          }}
        >
          <TablePageSize />
          <TablePagination label="User pages" />
          <TablePageSummary />
        </DataTable>
      ) : (
        <ContentState
          kind="error"
          title="User view cleared"
          message="No rows are retained without verified access."
        />
      )}
      <Button disabled={!allowed || data.kind === 'loading'} onClick={reload}>
        {data.kind === 'error' || !allowed ? 'Retry user list' : 'Reload users'}
      </Button>
    </section>
  );
}

function DateValue({ value }: { value: string }) {
  return <time dateTime={value}>{value.replace('T', ' ').replace('+00:00', ' UTC')}</time>;
}

function Destination({
  cache,
  destination,
  userId,
  children,
  label
}: {
  cache: AuthorityCache;
  destination: 'invite' | 'inviteElevated' | 'invitation';
  userId?: string;
  children: React.ReactNode;
  label?: string;
}) {
  const authority = useAuthority(cache);
  const route = useAccountLinks()(destination, userId);
  if (!route || evaluateAccess(authority, route.requirements) !== 'allowed') return null;
  return (
    <a
      href={route.href}
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
