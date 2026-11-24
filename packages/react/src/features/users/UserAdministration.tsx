import { useCallback, useEffect, useId, useRef, useState } from 'react';
import BootstrapButton from 'react-bootstrap/Button';
import Modal from 'react-bootstrap/Modal';

import { Button } from '../../components/Button.js';
import { useAuthority } from '../authority/useAuthority.js';
import { sessionDescription } from '../sessions/RevokeSessionConfirmation.js';
import { actionAllowed, adminAccess, safeAdminReason } from './UserAdministrationService.js';

import type { ApiResult } from '../../api/ApiResult.js';
import type { AuthorityCache } from '../authority/AuthorityCache.js';
import type { AdminAction, UserAdministrationService } from './UserAdministrationService.js';
import type { DirectoryUser } from './UserDirectoryService.js';

type Selection = { action: AdminAction; label: string; target?: string };
export type AdministrationNotice = Readonly<{ message: string; error: boolean }>;

function AdminRecords<T>({
  title,
  load,
  children
}: {
  title: string;
  load: (
    page: number,
    signal: AbortSignal
  ) => Promise<ApiResult<{ rows: readonly T[]; totalPages: number }>>;
  children: (rows: readonly T[]) => React.ReactNode;
}) {
  const id = useId();
  const [request, setRequest] = useState({ page: 1 });
  const [response, setResponse] = useState<{
    request: typeof request;
    load: typeof load;
    result: Awaited<ReturnType<typeof load>>;
  }>();
  const result =
    response?.request === request && response.load === load ? response.result : undefined;
  useEffect(() => {
    const controller = new AbortController();
    void load(request.page, controller.signal).then((next) => {
      if (controller.signal.aborted) return;
      if (next.ok && request.page > next.value.totalPages) {
        setRequest({ page: next.value.totalPages });
        return;
      }
      setResponse({ request, load, result: next });
    });
    return () => controller.abort();
  }, [request, load]);
  return (
    <section className="border rounded p-3 my-3" aria-labelledby={id}>
      <h2 id={id}>{title}</h2>
      {!result ? (
        <p role="status">Loading {title.toLowerCase()}.</p>
      ) : result.ok ? (
        <>
          {children(result.value.rows)}
          {result.value.totalPages > 1 && (
            <div className="d-flex flex-wrap align-items-center gap-2">
              <Button
                disabled={request.page <= 1}
                onClick={() => setRequest({ page: request.page - 1 })}
                aria-label={`Previous ${title.toLowerCase()} page`}
              >
                Previous
              </Button>
              <span role="status">
                Page {request.page} of {result.value.totalPages}
              </span>
              <Button
                disabled={request.page >= result.value.totalPages}
                onClick={() => setRequest({ page: request.page + 1 })}
                aria-label={`Next ${title.toLowerCase()} page`}
              >
                Next
              </Button>
            </div>
          )}
        </>
      ) : (
        <p role="alert">
          {result.error.kind === 'authorization'
            ? 'Access forbidden.'
            : 'This view is unavailable.'}
        </p>
      )}
      <Button disabled={!result} onClick={() => setRequest({ page: request.page })}>
        Reload {title.toLowerCase()}
      </Button>
    </section>
  );
}

export function UserAdministration({
  user,
  service,
  cache,
  onOutcome
}: {
  user: DirectoryUser;
  service: UserAdministrationService;
  cache: AuthorityCache;
  onOutcome: (notice: AdministrationNotice) => void;
}) {
  const authority = useAuthority(cache);
  const scope = cache.captureScope();
  const [selected, select] = useState<Selection | null>(null);
  const roles = useCallback(
    (page: number, signal: AbortSignal) => service.roles(page, signal),
    [service]
  );
  const sessions = useCallback(
    (page: number, signal: AbortSignal) => service.sessions(user.userId, page, signal),
    [service, user.userId]
  );
  const other = authority.status === 'authenticated' && authority.principal.userId !== user.userId;
  const lifecycle: Selection[] = [
    { action: { kind: 'disable' }, label: 'Disable user' },
    { action: { kind: 'enable' }, label: 'Enable user' },
    { action: { kind: 'restore', state: 'active' }, label: 'Restore as active' },
    {
      action: { kind: 'restore', state: 'pending_activation' },
      label: 'Restore as pending activation'
    }
  ];
  const offer = (selection: Selection) =>
    actionAllowed(authority, user, selection.action) && (
      <Button
        onClick={() => {
          if (scope.isCurrent() && actionAllowed(cache.getSnapshot(), user, selection.action))
            select(selection);
        }}
      >
        {selection.label}
      </Button>
    );
  if (!other)
    return <p>To review your own sign-ins, open Active Sessions in Account &amp; Security.</p>;
  return (
    <>
      {lifecycle.some(({ action }) => actionAllowed(authority, user, action)) && (
        <div className="d-flex flex-wrap gap-2" aria-label="Lifecycle actions" role="group">
          {lifecycle.map((item) => (
            <span key={item.label}>{offer(item)}</span>
          ))}
        </div>
      )}
      {user.state === 'active' && adminAccess(authority, 'READ_ROLES', 'MANAGE_USER_ROLES') && (
        <AdminRecords title="Role catalog" load={roles}>
          {(rows) =>
            rows.length === 0 ? (
              <p>No roles in this catalog.</p>
            ) : (
              <ul className="list-unstyled text-break">
                {rows.map((role) => (
                  <li key={role.id} className="my-2 d-flex flex-wrap align-items-center gap-2">
                    <span>
                      {role.name} ({role.managed ? 'Managed' : 'Custom'})
                    </span>
                    {offer({ action: { kind: 'assign', role }, label: `Assign ${role.name}` })}
                    {offer({ action: { kind: 'remove', role }, label: `Remove ${role.name}` })}
                  </li>
                ))}
              </ul>
            )
          }
        </AdminRecords>
      )}
      {adminAccess(authority, 'READ_SESSIONS') && (
        <AdminRecords title="Admin sessions" load={sessions}>
          {(rows) =>
            rows.length === 0 ? (
              <p>No active sessions for this user.</p>
            ) : (
              <ul className="list-unstyled">
                {rows.map((session) => {
                  const date = session.createdAt.replace('T', ' ').replace('+00:00', ' UTC');
                  const label = `${sessionDescription(session)} — started ${date}`;
                  return (
                    <li
                      key={session.sessionId}
                      className="my-2 d-flex flex-wrap align-items-center gap-2"
                    >
                      <span>{label}</span>
                      {offer({
                        action: { kind: 'revoke', sessionId: session.sessionId },
                        label: `Revoke ${label}`,
                        target: label
                      })}
                    </li>
                  );
                })}
              </ul>
            )
          }
        </AdminRecords>
      )}
      {selected && scope.isCurrent() && actionAllowed(authority, user, selected.action) && (
        <Confirmation
          selection={selected}
          user={user}
          onClose={() => select(null)}
          onConfirm={async (reason) => {
            const result = await service.change(user, selected.action, reason);
            if (!scope.isCurrent() || (!result.ok && result.error.kind === 'cancelled')) return;
            select(null);
            onOutcome(
              result.ok
                ? { message: 'The server confirmed the change.', error: false }
                : {
                    message:
                      result.error.kind === 'conflict'
                        ? 'The change conflicts with current server state. Review the refreshed view before confirming again.'
                        : result.error.kind === 'authorization' ||
                            result.error.kind === 'authentication'
                          ? 'The server denied the change.'
                          : 'The change could not be confirmed. Review current server state before trying again.',
                    error: true
                  }
            );
            service.reconcile(user.userId, result);
          }}
        />
      )}
    </>
  );
}

function Confirmation({
  selection,
  user,
  onClose,
  onConfirm
}: {
  selection: Selection;
  user: DirectoryUser;
  onClose: () => void;
  onConfirm: (reason: string) => Promise<void>;
}) {
  const title = useId();
  const description = useId();
  const reasonId = useId();
  const [reason, setReason] = useState('');
  const [pending, setPending] = useState(false);
  const busy = useRef(false);
  const cancel = useRef<HTMLButtonElement>(null);
  const revoke = selection.action.kind === 'revoke';
  const elevated =
    selection.action.kind === 'assign' && selection.action.role.name === 'ROLE_SUPER_ADMIN';
  return (
    <Modal
      show
      animation={false}
      centered
      aria-labelledby={title}
      aria-describedby={description}
      onEscapeKeyDown={(event: Event) => event.stopPropagation()}
      onShow={() => cancel.current?.focus()}
      onHide={() => {
        if (!busy.current) onClose();
      }}
    >
      <Modal.Header>
        <Modal.Title id={title}>
          {revoke ? 'Revoke this admin session?' : `${selection.label}?`}
        </Modal.Title>
      </Modal.Header>
      <Modal.Body>
        <div id={description} className="text-break">
          <p>Target: {user.email}</p>
          {selection.target && (
            <p>Session: {selection.target}. This ends only that user’s selected sign-in.</p>
          )}
          {selection.action.kind === 'disable' && (
            <p>This disables the account and ends its existing sign-ins.</p>
          )}
          {selection.action.kind === 'enable' && (
            <p>This enables the account without restoring its previous sign-ins.</p>
          )}
          {elevated && <p>This grants Super Admin authority across the entire installation.</p>}
        </div>
        {revoke && (
          <div>
            <label className="form-label" htmlFor={reasonId}>
              Reason (required)
            </label>
            <input
              className="form-control"
              id={reasonId}
              type="text"
              required
              autoComplete="off"
              maxLength={500}
              aria-describedby={`${reasonId}-help`}
              value={reason}
              disabled={pending}
              onChange={(event) => setReason(event.target.value)}
            />
            <small id={`${reasonId}-help`}>
              Use a short operational reason, without credentials, private information, or links.
            </small>
          </div>
        )}
        {pending && <p role="status">Waiting for the server.</p>}
      </Modal.Body>
      <Modal.Footer>
        <BootstrapButton
          ref={cancel}
          type="button"
          disabled={pending}
          variant="secondary"
          onClick={onClose}
        >
          Cancel
        </BootstrapButton>
        <Button
          busy={pending}
          busyLabel="Waiting for the server"
          disabled={revoke && !safeAdminReason(reason)}
          onClick={() => {
            if (busy.current || (revoke && !safeAdminReason(reason))) return;
            busy.current = true;
            setPending(true);
            void onConfirm(reason);
          }}
        >
          Confirm {elevated ? 'Super Admin assignment' : revoke ? 'revocation' : 'change'}
        </Button>
      </Modal.Footer>
    </Modal>
  );
}
