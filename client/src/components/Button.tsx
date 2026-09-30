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
    <button
      {...props}
      type={type}
      className={`btn catalog-button ${className}`}
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
      {busy && <span className="catalog-spinner" aria-hidden="true" />}
      {busy ? busyLabel : children}
    </button>
  );
}
