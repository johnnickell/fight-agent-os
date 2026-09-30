import { Button } from '@/components/Button';
import { Notice } from '@/components/Notice';

type ContentStateProps = {
  title: string;
  message: string;
} & (
  | { kind: 'loading' | 'empty' | 'success'; onRetry?: never }
  | { kind: 'error'; onRetry?: () => void }
);

/**
 * Renders caller-owned load outcomes without fetching, retrying automatically or retaining data
 */
export function ContentState({ kind, title, message, onRetry }: ContentStateProps) {
  return (
    <div className="catalog-state">
      <Notice
        tone={kind === 'error' ? 'danger' : kind === 'success' ? 'success' : 'info'}
        title={title}
        announce={kind === 'empty' ? 'off' : 'polite'}
      >
        {kind === 'loading' && <span className="catalog-spinner" aria-hidden="true" />}
        {message}
      </Notice>
      {kind === 'error' && onRetry && <Button onClick={onRetry}>Try again</Button>}
    </div>
  );
}
