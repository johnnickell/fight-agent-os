import { hasExactKeys } from '@/api/decodeResponse';

export const sessionChannelName = 'fight-agent-os.session.v1';
export const sessionLockName = 'fight-agent-os.session-mutation.v1';

type Header = Readonly<{ version: 1; sender: string; sequence: number }>;
export type SessionMessage = Header &
  (
    | Readonly<{ kind: 'request'; round: string }>
    | Readonly<{ kind: 'token'; round: string; token: string; remaining: number }>
    | Readonly<{ kind: 'invalidate' | 'authority-changed' | 'logout' | 'terminal' }>
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
    (message.kind === 'invalidate' ||
      message.kind === 'authority-changed' ||
      message.kind === 'logout' ||
      message.kind === 'terminal') &&
    hasExactKeys(message, common)
  )
    return message as SessionMessage;
  return null;
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
