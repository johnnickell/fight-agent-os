import { useId } from 'react';
import type { ComponentPropsWithRef } from 'react';

type TextFieldProps = Omit<
  ComponentPropsWithRef<'input'>,
  'id' | 'type' | 'aria-describedby' | 'aria-invalid'
> & {
  label: string;
  description?: string;
  error?: string;
  type?: 'text' | 'email' | 'password' | 'search' | 'url' | 'tel';
};

/**
 * Connects native field semantics to visible help and caller-owned validation feedback
 */
export function TextField({
  label,
  description,
  error,
  required,
  className = '',
  type = 'text',
  ...props
}: TextFieldProps) {
  const id = useId();
  const helpId = `${id}-help`;
  const errorId = `${id}-error`;
  const describedBy = [description ? helpId : '', error ? errorId : '']
    .filter(Boolean)
    .join(' ');
  return (
    <div className="catalog-field">
      <label className="form-label" htmlFor={id}>
        {label}
        {required ? ' (required)' : ''}
      </label>
      <input
        {...props}
        id={id}
        type={type}
        required={required}
        className={`form-control ${error ? 'is-invalid' : ''} ${className}`}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy || undefined}
      />
      {description && (
        <p id={helpId} className="form-text">
          {description}
        </p>
      )}
      {error && (
        <p id={errorId} className="invalid-feedback">
          Error: {error}
        </p>
      )}
    </div>
  );
}
