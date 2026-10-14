import { ContentState } from '@/components/ContentState';

import type { AuthorityState } from '@/features/authority/AuthorityCache';
import type { AccessDecision } from '@/features/authority/evaluateAccess';

const descriptions = {
  unknown: [
    'Authority unknown',
    'Authentication must be established before this content can open.'
  ],
  loading: ['Checking access', 'Waiting for current authority.'],
  anonymous: ['Sign in required', 'Establish a session before opening this content.'],
  authenticated: ['Access forbidden', 'Your current permissions do not allow this content.'],
  stale: ['Access needs checking', 'Previous authority is no longer usable.'],
  terminal: [
    'Session ended',
    'Explicit sign-in is required. Delayed results cannot restore this session.'
  ],
  'refresh-failed': [
    'Session recovery failed',
    'Start an explicit authentication recovery attempt.'
  ],
  error: ['Access check failed', 'Current authority could not be established.']
} as const;

/**
 * Presents safe authority outcomes without implying that client checks authorize server operations
 */
export function AuthorityStatus({
  state,
  decision,
  onRetry
}: {
  state: AuthorityState;
  decision: Exclude<AccessDecision, 'allowed'>;
  onRetry?: () => void;
}) {
  const [title, description] =
    decision === 'invalid'
      ? ['Route unavailable', 'This route does not have valid access requirements.']
      : descriptions[state.status];
  const message = `${description} Server authorization remains authoritative.`;
  if (decision !== 'invalid' && (state.status === 'error' || state.status === 'stale')) {
    return (
      <ContentState
        kind="error"
        title={title}
        message={message}
        {...(onRetry ? { onRetry } : {})}
      />
    );
  }
  return (
    <ContentState
      kind={state.status === 'loading' ? 'loading' : 'empty'}
      title={title}
      message={message}
    />
  );
}
