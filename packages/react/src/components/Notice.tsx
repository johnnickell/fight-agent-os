import Alert from 'react-bootstrap/Alert';

import type { ReactNode } from 'react';

type NoticeProps = {
  tone: 'info' | 'success' | 'warning' | 'danger';
  title: string;
  children: ReactNode;
  announce?: 'polite' | 'assertive' | 'off';
};

/**
 * Presents explicit status text with an opt-in urgency suitable for the caller's interaction
 *
 * Disables Alert's transition so the explicit role (including off) reaches its native div
 */
export function Notice({ tone, title, children, announce = 'polite' }: NoticeProps) {
  return (
    <Alert
      variant=""
      transition={false}
      className={`catalog-notice catalog-notice-${tone}`}
      role={announce === 'off' ? undefined : announce === 'assertive' ? 'alert' : 'status'}
      aria-atomic={announce === 'off' ? undefined : true}
    >
      <p className="fw-semibold mb-1">{title}</p>
      <div>{children}</div>
    </Alert>
  );
}
