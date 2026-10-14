import { useRef, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { Button } from '@/components/Button';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { decodePublicSchema } from '@/features/validation/PublicSchema';
import { usePublicForm } from '@/features/validation/usePublicForm';

import type { ApiResult } from '@/api/ApiResult';
import type { Meta, StoryObj } from '@storybook/react-vite';

const meta = {
  title: 'Foundation/Text field',
  component: TextField,
  args: {
    label: 'Example label',
    description: 'Invented local text only.',
    required: true
  }
} satisfies Meta<typeof TextField>;
export default meta;
type Story = StoryObj<typeof meta>;

export const Ready: Story = {};
export const Invalid: Story = { args: { error: 'Enter an example label.' } };
export const Disabled: Story = {
  args: { disabled: true, defaultValue: 'Unavailable example' }
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

const exampleSchema = decodePublicSchema({
  schema_version: 1,
  revision: 'a'.repeat(64),
  form_name: 'example_form',
  fields: {
    display_name: {
      client_field: 'displayName',
      rules: [
        { type: 'MinLength', args: '2', message: 'Use at least two characters.', depends_on: [] },
        { type: 'MaxLength', args: '30', message: 'Use at most thirty characters.', depends_on: [] }
      ]
    }
  }
});

function SharedValidationExample() {
  const [available, setAvailable] = useState(true);
  const [resolvePending, setResolvePending] = useState<(() => void) | null>(null);
  const form = usePublicForm({
    schema: available ? exampleSchema : null,
    initialValues: { displayName: '' },
    submit: () =>
      new Promise<ApiResult<string>>((resolve) => {
        setResolvePending(
          () => () =>
            resolve({
              ok: false,
              error: {
                kind: 'validation',
                correlationId: null,
                fields: {
                  'body.display_name': [
                    'Use at least two characters.',
                    'Use at most thirty characters.'
                  ]
                }
              }
            })
        );
      }),
    onSuccess: () => {}
  });
  return (
    <form className="catalog-example-form" noValidate onSubmit={form.handleSubmit}>
      <TextField
        label="Example label"
        description="Synthetic server reply for UI states; no data is sent."
        {...(available && exampleSchema
          ? form.field('displayName')
          : { value: '', disabled: true })}
      />
      {!available && (
        <Notice tone="warning" title="Schema unavailable">
          Retry metadata loading.
        </Notice>
      )}
      {form.formError && <p role="status">{form.formError}</p>}
      <Button type="submit" disabled={form.busy || !available}>
        Validate
      </Button>
      <Button onClick={() => setAvailable((previous) => !previous)}>
        {available ? 'Simulate schema failure' : 'Retry schema'}
      </Button>
      {resolvePending && (
        <Button
          onClick={() => {
            resolvePending();
            setResolvePending(null);
          }}
        >
          Resolve delayed rejection
        </Button>
      )}
    </form>
  );
}

export const SharedValidation: Story = { render: () => <SharedValidationExample /> };

export const SharedValidationRejection: Story = {
  render: () => <SharedValidationExample />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.type(canvas.getByRole('textbox', { name: 'Example label' }), 'Sample');
    await userEvent.click(canvas.getByRole('button', { name: 'Validate' }));
    await userEvent.click(await canvas.findByRole('button', { name: 'Resolve delayed rejection' }));
    await expect(await canvas.findAllByRole('listitem')).toHaveLength(2);
    await expect(canvas.getByRole('textbox', { name: 'Example label' })).toHaveFocus();
  }
};

export const SharedValidationCorrection: Story = {
  ...SharedValidationRejection,
  play: async (context) => {
    await SharedValidationRejection.play?.(context);
    const canvas = within(context.canvasElement);
    const input = canvas.getByRole('textbox', { name: 'Example label' });
    await userEvent.type(input, ' updated');
    await expect(input).not.toHaveAttribute('aria-invalid');
  }
};

export const SharedValidationUnavailable: Story = {
  render: () => <SharedValidationExample />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', { name: 'Simulate schema failure' }));
    await expect(canvas.getByText('Retry metadata loading.')).toBeInTheDocument();
    await expect(canvas.getByRole('button', { name: 'Validate' })).toBeDisabled();
  }
};

export const SharedValidationStale: Story = {
  render: () => <SharedValidationExample />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const input = canvas.getByRole('textbox', { name: 'Example label' });
    await userEvent.type(input, 'Sample');
    await userEvent.click(canvas.getByRole('button', { name: 'Validate' }));
    await userEvent.type(input, ' changed');
    await userEvent.click(await canvas.findByRole('button', { name: 'Resolve delayed rejection' }));
    await expect(input).not.toHaveAttribute('aria-invalid');
  }
};

export const ValidationToSuccess: Story = {
  render: () => <LocalForm />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', { name: 'Validate example' }));
    const field = canvas.getByRole('textbox', {
      name: 'Example label (required)'
    });
    await expect(field).toHaveFocus();
    await expect(field).toHaveAccessibleDescription(/Error: Enter an example label/);
    await userEvent.type(field, 'Sample label');
    await userEvent.keyboard('{Enter}');
    await expect(canvas.getByRole('status')).toHaveTextContent('Example validated locally');
    await expect(field).not.toHaveAttribute('aria-invalid');
  }
};
