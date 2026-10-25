import { hasExactKeys } from '@/api/decodeResponse';

export const sessionChannelName = 'fight-agent-os.session.v1';
export const sessionLockName = 'fight-agent-os.session-mutation.v1';

type Header = Readonly<{ version: 1; sender: string; sequence: number }>;
export type SessionMessage = Header &
  (
    | Readonly<{ kind: 'request'; round: string }>
    | Readonly<{ kind: 'token'; round: string; token: string; remaining: number }>
    | Readonly<{
        kind: 'invalidate' | 'authority-changed' | 'logout' | 'terminal' | 'password-change';
      }>
    | Readonly<{ kind: 'password-reset'; userId: string }>
  );

const identifier = (value: unknown): value is string =>
  typeof value === 'string' && /^[a-f0-9]{32}$/.test(value);

/** Rejects arbitrary objects/extra data before any state change; credentials never reach diagnostics. */
export function decodeSessionMessage(value: unknown): SessionMessage | null {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return null;
  const message = value as Record<string, unknown>;
  const common = ['version', 'sender', 'sequence', 'kind'];
  if (
    message.version !== 1 ||
    !identifier(message.sender) ||
    !Number.isSafeInteger(message.sequence) ||
    typeof message.sequence !== 'number' ||
    message.sequence < 1
  )
    return null;
  if (message.kind === 'request' && hasExactKeys(message, [...common, 'round']))
    return identifier(message.round) ? (message as SessionMessage) : null;
  if (
    message.kind === 'token' &&
    hasExactKeys(message, [...common, 'round', 'token', 'remaining']) &&
    identifier(message.round) &&
    typeof message.token === 'string' &&
    message.token.length <= 2048 &&
    /^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/.test(message.token) &&
    typeof message.remaining === 'number' &&
    Number.isFinite(message.remaining) &&
    message.remaining > 0 &&
    message.remaining <= 840_000
  )
    return message as SessionMessage;
  if (
    message.kind === 'password-reset' &&
    hasExactKeys(message, [...common, 'userId']) &&
    typeof message.userId === 'string' &&
    /^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(message.userId)
  )
    return message as SessionMessage;
  if (
    (message.kind === 'invalidate' ||
      message.kind === 'authority-changed' ||
      message.kind === 'logout' ||
      message.kind === 'terminal' ||
      message.kind === 'password-change') &&
    hasExactKeys(message, common)
  )
    return message as SessionMessage;
  return null;
}

/** Sends only a transient target routing identity, never a grant/password or persisted identity. */
export function broadcastPasswordReset(userId: string): void {
  let channel: SessionChannel | undefined;
  try {
    ({ channel } = browserSessionPlatform());
    channel?.postMessage({
      version: 1,
      sender: crypto.randomUUID().replaceAll('-', ''),
      sequence: 1,
      kind: 'password-reset',
      userId
    } satisfies SessionMessage);
  } catch {
    // Missing/denied messaging cannot undo reset. Server versions remain authoritative.
  } finally {
    try {
      channel?.close();
    } catch {
      // Cleanup failure also cannot replace an observed reset success.
    }
  }
}

export type SessionChannel = Pick<BroadcastChannel, 'postMessage' | 'close'> & {
  onmessage: ((event: MessageEvent<unknown>) => void) | null;
};

/** Missing/denied secure-origin APIs select the bounded independent-tab fallback, never storage. */
export function browserSessionPlatform(): {
  locks?: LockManager;
  channel?: SessionChannel;
} {
  if (!globalThis.isSecureContext) return {};
  let channel: SessionChannel | undefined;
  try {
    if (typeof BroadcastChannel !== 'undefined') channel = new BroadcastChannel(sessionChannelName);
  } catch {
    // Same-origin messaging can be unavailable even when its constructor exists.
  }
  return {
    ...(navigator.locks ? { locks: navigator.locks } : {}),
    ...(channel ? { channel } : {})
  };
}
