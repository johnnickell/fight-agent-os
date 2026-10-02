import { useId } from 'react';
import FormControl from 'react-bootstrap/FormControl';
import FormLabel from 'react-bootstrap/FormLabel';
import FormText from 'react-bootstrap/FormText';

import type { ComponentPropsWithRef } from 'react';

type TextFieldProps = Omit<
  ComponentPropsWithRef<'input'>,
  'id' | 'type' | 'aria-describedby' | 'aria-invalid'
> & {
  label: string;
  description?: string;
  error?: string;
  errors?: readonly string[];
  type?: 'text' | 'email' | 'password' | 'search' | 'url' | 'tel';
};

/**
 * Connects native field semantics to visible help and caller-owned validation feedback
 *
 * Maps native size to htmlSize; omits absent library props and copies readonly values
 * to preserve the native input API under the library's narrower optional/value types
 */
export function TextField({
  label,
  description,
  error,
  errors,
  required,
  className = '',
  type = 'text',
  size,
  readOnly = false,
  disabled = false,
  value,
  onChange,
  ...props
}: TextFieldProps) {
  const messages = [...new Set([...(error ? [error] : []), ...(errors ?? [])])];
  const id = useId();
  const helpId = `${id}-help`;
  const errorId = `${id}-error`;
  const describedBy = [description ? helpId : '', messages.length ? errorId : '']
    .filter(Boolean)
    .join(' ');
  return (
    <div className="catalog-field">
      <FormLabel htmlFor={id}>
        {label}
        {required ? ' (required)' : ''}
      </FormLabel>
      <FormControl
        as="input"
        {...props}
        id={id}
        type={type}
        required={required}
        className={className}
        isInvalid={messages.length > 0}
        readOnly={readOnly}
        disabled={disabled}
        {...(size === undefined ? {} : { htmlSize: size })}
        {...(value === undefined ? {} : { value: typeof value === 'object' ? [...value] : value })}
        {...(onChange === undefined ? {} : { onChange })}
        aria-invalid={messages.length ? true : undefined}
        aria-describedby={describedBy || undefined}
      />
      {description && (
        <FormText as="p" id={helpId}>
          {description}
        </FormText>
      )}
      {messages.length === 1 && (
        <FormControl.Feedback as="p" id={errorId} type="invalid">
          Error: {messages[0]}
        </FormControl.Feedback>
      )}
      {messages.length > 1 && (
        <FormControl.Feedback as="div" id={errorId} type="invalid">
          <span>Errors:</span>
          <ul>
            {messages.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        </FormControl.Feedback>
      )}
    </div>
  );
}
