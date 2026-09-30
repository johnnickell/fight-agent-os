import { createRef } from 'react';

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { TextField } from '@/components/forms/TextField';

import type { ChangeEventHandler } from 'react';

describe('TextField', () => {
  it('connects required labels, help, error text and the caller focus ref', async () => {
    const ref = createRef<HTMLInputElement>();
    render(
      <TextField
        ref={ref}
        label="Example"
        required
        description="Use local text."
        error="Enter a label."
      />
    );
    const field = screen.getByRole('textbox', { name: 'Example (required)' });
    expect(field).toBeRequired();
    expect(field).toHaveAttribute('aria-invalid', 'true');
    expect(field).toHaveAccessibleDescription('Use local text. Error: Enter a label.');
    await userEvent.setup().click(screen.getByText('Example (required)'));
    expect(field).toHaveFocus();
    expect(ref.current).toBe(field);
  });

  it('removes stale validation feedback when the caller accepts the value', async () => {
    const user = userEvent.setup();
    const change = vi.fn();
    const { rerender } = render(
      <TextField label="Example" error="Enter a label." onChange={change} />
    );
    const field = screen.getByRole('textbox');
    expect(field).toHaveAccessibleDescription('Error: Enter a label.');
    await user.tab();
    await user.type(field, 'Sample');
    expect(field).toHaveValue('Sample');
    expect(change).toHaveBeenCalledTimes(6);
    rerender(<TextField label="Example" description="Accepted locally." onChange={change} />);
    expect(field).not.toHaveAttribute('aria-invalid');
    expect(field).toHaveAccessibleDescription('Accepted locally.');
    expect(screen.queryByText(/Error:/)).not.toBeInTheDocument();
  });

  it('keeps duplicate labels independently associated and optional fields undescribed', () => {
    render(
      <>
        <TextField label="Example" />
        <TextField label="Example" />
      </>
    );
    const [first, second] = screen.getAllByRole('textbox', { name: 'Example' });
    expect(first?.id).not.toBe(second?.id);
    expect(first).not.toHaveAttribute('aria-describedby');
    expect(first).not.toBeRequired();
  });

  it('preserves native sizing, input event targets and controlled caller updates', async () => {
    const user = userEvent.setup();
    const change = vi.fn<ChangeEventHandler<HTMLInputElement>>();
    const fieldProps = { label: 'Example', size: 24, name: 'example', onChange: change };
    const { rerender } = render(<TextField {...fieldProps} value="Before" />);
    const field = screen.getByRole('textbox');
    expect(field.tagName).toBe('INPUT');
    expect(field).toHaveAttribute('size', '24');
    expect(field).toHaveAttribute('name', 'example');
    await user.type(field, 'x');
    expect(change).toHaveBeenCalledOnce();
    expect(change.mock.calls[0]?.[0].target).toBe(field);
    expect(field).toHaveValue('Before');
    rerender(<TextField {...fieldProps} value="After" />);
    expect(field).toHaveValue('After');
  });

  it.each([42, ['Sample'] as const])('preserves native read-only value %j', async (value) => {
    const change = vi.fn();
    render(<TextField label="Example" value={value} readOnly onChange={change} />);
    const field = screen.getByRole('textbox');
    expect(field).toHaveValue(String(value));
    await userEvent.setup().type(field, 'changed');
    expect(field).toHaveValue(String(value));
    expect(change).not.toHaveBeenCalled();
  });

  it('uses native email and disabled semantics without accepting input', async () => {
    render(
      <TextField
        label="Email example"
        type="email"
        disabled
        defaultValue="sample@example.invalid"
      />
    );
    const field = screen.getByRole('textbox');
    await userEvent.setup().type(field, 'changed');
    expect(field).toBeDisabled();
    expect(field).toHaveAttribute('type', 'email');
    expect(field).toHaveValue('sample@example.invalid');
  });
});
