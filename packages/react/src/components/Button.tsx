import BootstrapButton from 'react-bootstrap/Button';
import Spinner from 'react-bootstrap/Spinner';

import type { ComponentPropsWithoutRef } from 'react';

type ButtonProps = ComponentPropsWithoutRef<'button'> & {
  busy?: boolean;
  busyLabel?: string;
};

/**
 * Retains focus while busy and suppresses activation, including native form submission
 */
export function Button({
  busy = false,
  busyLabel = 'Working…',
  disabled = false,
  children,
  onClick,
  type = 'button',
  className = '',
  ...props
}: ButtonProps) {
  return (
    <BootstrapButton
      {...props}
      as="button"
      variant=""
      type={type}
      className={`catalog-button ${className}`}
      disabled={disabled}
      aria-disabled={busy || disabled || props['aria-disabled']}
      aria-busy={busy}
      onClick={(event) => {
        if (
          busy ||
          disabled ||
          props['aria-disabled'] === true ||
          props['aria-disabled'] === 'true'
        ) {
          event.preventDefault();
          return;
        }
        onClick?.(event);
      }}
    >
      {busy && <Spinner as="span" className="catalog-spinner" aria-hidden="true" />}
      {busy ? busyLabel : children}
    </BootstrapButton>
  );
}
