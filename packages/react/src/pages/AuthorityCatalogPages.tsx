import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import BootstrapButton from 'react-bootstrap/Button';
import Modal from 'react-bootstrap/Modal';

import { AuthorityStatus } from '../components/AuthorityStatus.js';
import { Button } from '../components/Button.js';
import {
  DataTable,
  TablePageSize,
  TablePageSummary,
  TablePagination
} from '../components/DataTable.js';
import { maximumTablePage } from '../components/tableQuery.js';
import { useTableLocation } from '../components/useTableLocation.js';
import {
  authorityCatalogTablePolicy as policy,
  useCatalogRead
} from '../features/authority/catalogTable.js';
import { eligibleRolePermission } from '../features/authority/CustomRoleService.js';
import { evaluateAccess } from '../features/authority/evaluateAccess.js';
import { useAuthority } from '../features/authority/useAuthority.js';

import type { CatalogOrdering } from '../api/ApiClient.js';
import type { ApiResult } from '../api/ApiResult.js';
import type { TableColumn, TableData } from '../components/DataTable.js';
import type { AuthorityCache, AuthorityScope } from '../features/authority/AuthorityCache.js';
import type { AuthorityState } from '../features/authority/AuthorityCache.js';
import type {
  CatalogPermission,
  CatalogRole,
  CatalogService
} from '../features/authority/CatalogService.js';
import type { CatalogReader } from '../features/authority/catalogTable.js';
import type { CustomRoleService } from '../features/authority/CustomRoleService.js';
import type { AdministrationNotice } from '../features/users/UserAdministration.js';

const allowed = (state: AuthorityState, ...all: string[]) =>
  evaluateAccess(state, [{ kind: 'permissions', all }]) === 'allowed';

type CommonProps = {
  cache: AuthorityCache;
  catalog: Pick<CatalogService, 'roles' | 'permissions'>;
  locationRestoration?: Readonly<{ invalid: boolean }> | undefined;
};
type Sort = CatalogOrdering['sort'];

function useCatalogTable<Row>(
  cache: AuthorityCache,
  access: string,
  load: CatalogReader<Row>,
  locationRestoration: CommonProps['locationRestoration']
) {
  const authority = useAuthority(cache);
  const scope = cache.captureScope();
  const canRead = allowed(authority, access);
  const location = useTableLocation(policy, locationRestoration);
  const { query, current, restoreQuery } = location;
  const [attempt, retry] = useState(0);
  const pagination = useMemo(
    () => ({
      page: query.page,
      perPage: query.perPage,
      ordering: { sort: query.sort, order: query.order }
    }),
    [query]
  );
  const result = useCatalogRead(load, scope, canRead, pagination, current, query, attempt);
  const last = result?.ok ? Math.min(maximumTablePage, result.value.totalPages) : query.page;
  const [correction, setCorrection] = useState<{ scope: AuthorityScope; query: typeof query }>();
  const outside = last < query.page;
  const changedAgain = outside && correction?.scope === scope && correction.query === query;
  useEffect(() => {
    if (!outside || changedAgain || !scope.isCurrent() || current.current !== query) return;
    restoreQuery({ ...query, page: last });
    const corrected = current.current;
    queueMicrotask(() => setCorrection({ scope, query: corrected }));
  }, [changedAgain, current, last, outside, query, restoreQuery, scope]);
  const data: TableData<Row> = changedAgain
    ? {
        kind: 'error',
        title: 'The catalog changed again',
        message: 'Reload to check the current results.'
      }
    : !result || outside
      ? { kind: 'loading' }
      : result.ok
        ? { kind: 'ready', rows: result.value.records, total: result.value.totalRecords }
        : {
            kind: 'error',
            title: ['authentication', 'authorization'].includes(result.error.kind)
              ? 'Access denied'
              : 'Catalog unavailable',
            message: 'No rows are retained. Check your access and reload.'
          };
  return {
    authority,
    scope,
    location,
    data,
    canRead,
    isCurrentQuery: () => current.current === query,
    reload: () => {
      setCorrection(undefined);
      if (cache.canRetry()) void cache.load();
      retry((previous) => previous + 1);
    }
  };
}

function CatalogTable<Row>({
  view,
  columns,
  id,
  caption
}: {
  view: ReturnType<typeof useCatalogTable<Row>>;
  columns: readonly TableColumn<Row, Sort>[];
  id: (row: Row) => string;
  caption: string;
}) {
  const { data, location } = view;
  if (!view.canRead)
    return (
      <AuthorityStatus
        state={view.authority}
        decision={view.authority.status === 'authenticated' ? 'forbidden' : 'unavailable'}
        onRetry={view.reload}
      />
    );
  return (
    <>
      {location.notice && <p role="status">Unsupported table options were reset.</p>}
      <DataTable
        policy={policy}
        initial={location.query}
        restore={location.restore}
        onChange={(query, reason) => location.change(query, reason)}
        data={data}
        columns={columns}
        rowKey={id}
        caption={caption}
      >
        <TablePageSize />
        <TablePageSummary />
        <TablePagination />
      </DataTable>
      {data.kind === 'error' && <Button onClick={view.reload}>Reload catalog</Button>}
    </>
  );
}

const permissionType = (permission: CatalogPermission) =>
  permission.tier === 'SUPER_ADMIN_ONLY'
    ? 'Super Admin only — not delegable'
    : permission.tier === 'UNKNOWN'
      ? 'Unknown tier — not delegable'
      : eligibleRolePermission(permission)
        ? 'Admin safe — eligible for custom roles'
        : 'Custom — not delegable';

const permissionColumns: readonly TableColumn<CatalogPermission, Sort>[] = [
  {
    key: 'name',
    label: 'Permission (creation order)',
    sort: 'Created',
    rowHeader: (row) => row.name,
    cell: (row) => <span className="text-break">{row.name}</span>
  },
  { key: 'id', label: 'ID', sort: 'ID', cell: (row) => row.permissionId },
  { key: 'kind', label: 'Source', cell: (row) => (row.managed ? 'Managed' : 'Custom') },
  { key: 'tier', label: 'Delegation tier', cell: permissionType }
];

export function PermissionsPage({ cache, catalog, locationRestoration }: CommonProps) {
  const load = useCallback<CatalogReader<CatalogPermission>>(
    (page, signal) => catalog.permissions(page, signal),
    [catalog]
  );
  const view = useCatalogTable(cache, 'READ_PERMISSIONS', load, locationRestoration);
  return (
    <section>
      <h1>Permissions</h1>
      <p>Browse permission definitions. This catalog is read-only.</p>
      <CatalogTable
        view={view}
        columns={permissionColumns}
        id={(row) => row.permissionId}
        caption="Permission catalog"
      />
    </section>
  );
}

type Intent =
  { kind: 'create' } | { kind: 'rename' | 'remove' | 'grant' | 'revoke'; role: CatalogRole };
const requirements = {
  create: ['CREATE_ROLES'],
  rename: ['UPDATE_ROLES'],
  remove: ['DELETE_ROLES'],
  grant: ['MANAGE_ROLE_PERMISSIONS', 'READ_PERMISSIONS'],
  revoke: ['MANAGE_ROLE_PERMISSIONS', 'READ_PERMISSIONS']
};
const labels = {
  create: 'Create role',
  rename: 'Rename role',
  remove: 'Remove role',
  grant: 'Grant permission',
  revoke: 'Revoke permission'
};

function resultNotice(result: ApiResult<unknown>): AdministrationNotice {
  if (result.ok)
    return {
      error: false,
      message: 'The server confirmed the operation.'
    };
  const { kind } = result.error;
  if (kind === 'conflict' || kind === 'not-found')
    return {
      error: true,
      message:
        'The target changed or the operation conflicts with current state. Review the refreshed catalog before trying again.'
    };
  if (kind === 'authorization' || kind === 'authentication')
    return { error: true, message: 'The server denied the operation.' };
  if (kind === 'validation')
    return { error: true, message: 'The server did not accept the submitted values.' };
  return {
    error: true,
    message:
      'The operation could not be confirmed. Review current server state before trying again.'
  };
}

export function RolesPage({
  cache,
  catalog,
  mutations,
  locationRestoration,
  notice: hostNotice,
  onOutcome
}: CommonProps & {
  mutations: CustomRoleService;
  notice?: AdministrationNotice | undefined;
  onOutcome?: ((notice: AdministrationNotice) => void) | undefined;
}) {
  const load = useCallback<CatalogReader<CatalogRole>>(
    (page, signal) => catalog.roles(page, signal),
    [catalog]
  );
  const view = useCatalogTable(cache, 'READ_ROLES', load, locationRestoration);
  const { authority, scope, location, isCurrentQuery } = view;
  const [intent, setIntent] = useState<{
    target: Intent;
    scope: AuthorityScope;
    query: typeof location.query;
  }>();
  const [notice, setNotice] = useState<{ value: AdministrationNotice; current: () => boolean }>();
  const [busy, setBusy] = useState(false);
  const pending = useRef(false);
  const heading = useRef<HTMLHeadingElement>(null);
  const mounted = useRef(false);
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);
  const can = (kind: Intent['kind']) => view.canRead && allowed(authority, ...requirements[kind]);
  const visible =
    intent?.scope === scope && intent.query === location.query && can(intent.target.kind);
  if (intent && !visible) setIntent(undefined);
  const admit = (target: Intent) =>
    scope.isCurrent() &&
    isCurrentQuery() &&
    allowed(cache.getSnapshot(), 'READ_ROLES', ...requirements[target.kind]) &&
    (!('role' in target) || !target.role.managed);
  const open = (target: Intent) => {
    if (!pending.current && admit(target)) setIntent({ target, scope, query: location.query });
  };
  const submit = async (target: Intent, name: string, permission?: CatalogPermission) => {
    if (pending.current || !admit(target)) return;
    const current = cache.captureAuthenticationContext();
    pending.current = true;
    setBusy(true);
    const result =
      target.kind === 'create'
        ? await mutations.create(name)
        : target.kind === 'rename'
          ? await mutations.rename(target.role, name)
          : target.kind === 'remove'
            ? await mutations.remove(target.role, true)
            : permission
              ? await mutations.changePermission(target.role, permission, target.kind, true)
              : null;
    pending.current = false;
    if (mounted.current) {
      setBusy(false);
      setIntent(undefined);
      heading.current?.focus();
    }
    if (result && current()) {
      const value = resultNotice(result);
      if (onOutcome) onOutcome(value);
      else if (mounted.current) setNotice({ value, current });
    }
  };
  const outcome = hostNotice ?? (notice?.current() ? notice.value : undefined);
  const rowActions = (role: CatalogRole) =>
    role.managed ? (
      'Managed roles cannot be changed.'
    ) : (
      <div className="d-flex flex-wrap gap-2">
        {(['rename', 'remove', 'grant', 'revoke'] as const).map(
          (kind) =>
            can(kind) && (
              <Button
                key={kind}
                disabled={busy}
                aria-label={`${labels[kind]} for ${role.name}`}
                onClick={() => open({ kind, role })}
              >
                {labels[kind]}
              </Button>
            )
        )}
        {!(['rename', 'remove', 'grant', 'revoke'] as const).some(can) && 'No available changes.'}
      </div>
    );
  const columns: TableColumn<CatalogRole, Sort>[] = [
    {
      key: 'name',
      label: 'Role (creation order)',
      sort: 'Created',
      rowHeader: (row) => row.name,
      cell: (row) => <span className="text-break">{row.name}</span>
    },
    { key: 'id', label: 'ID', sort: 'ID', cell: (row) => row.roleId },
    { key: 'kind', label: 'Source', cell: (row) => (row.managed ? 'Managed' : 'Custom') },
    { key: 'actions', label: 'Actions', cell: rowActions }
  ];
  return (
    <section>
      <h1 ref={heading} tabIndex={-1}>
        Roles
      </h1>
      <p>Managed roles are read-only. Changes to custom roles apply installation-wide.</p>
      {outcome && <p role={outcome.error ? 'alert' : 'status'}>{outcome.message}</p>}
      {busy && <p role="status">Waiting for the server to confirm the operation.</p>}
      {can('create') && (
        <Button disabled={busy} onClick={() => open({ kind: 'create' })}>
          Create role
        </Button>
      )}
      <CatalogTable view={view} columns={columns} id={(row) => row.roleId} caption="Role catalog" />
      {visible && (
        <RoleConfirmation
          target={intent.target}
          scope={scope}
          catalog={catalog}
          busy={busy}
          close={() => setIntent(undefined)}
          submit={submit}
        />
      )}
    </section>
  );
}

function RoleConfirmation({
  target,
  scope,
  catalog,
  busy,
  close,
  submit
}: {
  target: Intent;
  scope: AuthorityScope;
  catalog: CommonProps['catalog'];
  busy: boolean;
  close: () => void;
  submit: (target: Intent, name: string, permission?: CatalogPermission) => Promise<void>;
}) {
  const title = useId();
  const description = useId();
  const cancel = useRef<HTMLButtonElement>(null);
  const role = 'role' in target ? target.role : undefined;
  const needsName = target.kind === 'create' || target.kind === 'rename';
  const needsPermission = target.kind === 'grant' || target.kind === 'revoke';
  const [name, setName] = useState(needsName ? (role?.name ?? '') : '');
  const [permission, selectPermission] = useState<CatalogPermission>();
  const valid =
    (!needsName ||
      (name.length <= 255 && /^ROLE_[A-Z_]+(?![\s\S])/.test(name) && name !== role?.name)) &&
    (!needsPermission || permission !== undefined);
  return (
    <Modal
      show
      animation={false}
      onShow={() => cancel.current?.focus()}
      onHide={() => {
        if (!busy) close();
      }}
      onEscapeKeyDown={(event: Event) => event.stopPropagation()}
      keyboard={!busy}
      backdrop={busy ? 'static' : true}
      aria-labelledby={title}
      aria-describedby={description}
    >
      <Modal.Header>
        <Modal.Title id={title} className="text-break">
          {labels[target.kind]}
          {role ? `: ${role.name}` : ''}
        </Modal.Title>
      </Modal.Header>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          if (valid && !busy) void submit(target, name, permission);
        }}
      >
        <Modal.Body>
          <p id={description}>Confirm this change for the whole installation.</p>
          {busy && <p role="status">Waiting for the server to confirm the operation.</p>}
          {needsName && (
            <label className="d-block">
              Role name (ROLE_ followed by capital letters or underscores)
              <input
                className="form-control"
                autoComplete="off"
                value={name}
                maxLength={255}
                required
                disabled={busy}
                onChange={(event) => setName(event.target.value)}
              />
            </label>
          )}
          {needsPermission && (
            <PermissionPicker
              catalog={catalog}
              scope={scope}
              disabled={busy}
              selected={permission}
              select={selectPermission}
            />
          )}
        </Modal.Body>
        <Modal.Footer>
          <BootstrapButton
            ref={cancel}
            type="button"
            variant="secondary"
            onClick={close}
            disabled={busy}
          >
            Cancel
          </BootstrapButton>
          <Button type="submit" disabled={busy || !valid}>
            {labels[target.kind]}
          </Button>
        </Modal.Footer>
      </form>
    </Modal>
  );
}

function PermissionPicker({
  catalog,
  scope,
  disabled,
  selected,
  select
}: {
  catalog: CommonProps['catalog'];
  scope: AuthorityScope;
  disabled: boolean;
  selected: CatalogPermission | undefined;
  select: (permission?: CatalogPermission) => void;
}) {
  const load = useCallback<CatalogReader<CatalogPermission>>(
    (page, signal) => catalog.permissions(page, signal),
    [catalog]
  );
  const [query, setQuery] = useState({ page: 1, perPage: 25 });
  const current = useRef(query);
  const result = useCatalogRead(load, scope, true, query, current, query);
  const rows = result?.ok ? result.value.records : [];
  function go(page: number) {
    select(undefined);
    current.current = { ...query, page };
    setQuery(current.current);
  }
  return (
    <>
      <p>
        The API does not list current associations. Choose the permission and confirm the requested
        operation; the server checks current state.
      </p>
      {!result ? (
        <p role="status">Loading permissions.</p>
      ) : !result.ok ? (
        <p role="alert">Permissions unavailable. Cancel to return to the catalog.</p>
      ) : (
        <>
          {rows.length ? (
            <label className="d-block">
              Permission
              <select
                className="form-select"
                disabled={disabled}
                value={selected?.permissionId ?? ''}
                onChange={(event) =>
                  select(
                    rows.find(
                      (row) =>
                        row.permissionId === event.target.value && eligibleRolePermission(row)
                    )
                  )
                }
              >
                <option value="">Choose a permission</option>
                {rows.map((row) => (
                  <option
                    key={row.permissionId}
                    value={row.permissionId}
                    disabled={!eligibleRolePermission(row)}
                  >
                    {row.name} — {permissionType(row)}
                  </option>
                ))}
              </select>
            </label>
          ) : (
            <p role="status">There are no permissions on this page.</p>
          )}
          <p role="status">
            Permission page {query.page} of {result.value.totalPages}
          </p>
          <div className="d-flex flex-wrap gap-2">
            <Button disabled={disabled || query.page <= 1} onClick={() => go(query.page - 1)}>
              Previous permissions
            </Button>
            <Button
              disabled={
                disabled || query.page >= Math.min(maximumTablePage, result.value.totalPages)
              }
              onClick={() => go(query.page + 1)}
            >
              Next permissions
            </Button>
          </div>
        </>
      )}
    </>
  );
}
