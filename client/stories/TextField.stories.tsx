import { useRef, useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { TextField } from '../src/components/forms/TextField';
import { Button } from '../src/components/Button';
import { Notice } from '../src/components/Notice';

const meta = {
  title: 'Foundation/Text field',
  component: TextField,
  args: {
    label: 'Example label',
    description: 'Invented local text only.',
    required: true,
  },
} satisfies Meta<typeof TextField>;
export default meta;
type Story = StoryObj<typeof meta>;

export const Ready: Story = {};
export const Invalid: Story = { args: { error: 'Enter an example label.' } };
export const Disabled: Story = {
  args: { disabled: true, defaultValue: 'Unavailable example' },
};

// Disposable story composition, not a product form or a copied component implementation.
function LocalForm() {
  const [value, setValue] = useState('');
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const input = useRef<HTMLInputElement>(null);
  return (
    <form
      className="catalog-example-form"
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        if (!value.trim()) {
          setError('Enter an example label.');
          input.current?.focus();
          return;
        }
        setError('');
        setSaved(true);
      }}
    >
      <TextField
        ref={input}
        label="Example label"
        description="This demonstration never saves or sends data."
        required
        value={value}
        error={error}
        onChange={(event) => {
          setValue(event.target.value);
          setSaved(false);
        }}
      />
      <Button type="submit">Validate example</Button>
      {saved && (
        <Notice tone="success" title="Success">
          Example validated locally. Nothing was saved.
        </Notice>
      )}
    </form>
  );
}

export const ValidationToSuccess: Story = {
  render: () => <LocalForm />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(
      canvas.getByRole('button', { name: 'Validate example' }),
    );
    const field = canvas.getByRole('textbox', {
      name: 'Example label (required)',
    });
    await expect(field).toHaveFocus();
    await expect(field).toHaveAccessibleDescription(
      /Error: Enter an example label/,
    );
    await userEvent.type(field, 'Sample label');
    await userEvent.keyboard('{Enter}');
    await expect(canvas.getByRole('status')).toHaveTextContent(
      'Example validated locally',
    );
    await expect(field).not.toHaveAttribute('aria-invalid');
  },
};
