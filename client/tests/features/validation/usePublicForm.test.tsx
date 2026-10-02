import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { decodeResponse } from '@/api/decodeResponse';
import { TextField } from '@/components/forms/TextField';
import { decodePublicSchema } from '@/features/validation/PublicSchema';
import { usePublicForm } from '@/features/validation/usePublicForm';

import { deferred } from '../../apiFixtures';
import { fixture } from './fixtures';

const schema = decodePublicSchema(fixture)!;
const initialValues = { displayName: '', confirmation: '' };
function Example({
  submit,
  success = () => {},
  activeSchema = schema
}: {
  submit: (values: Readonly<Record<string, string>>) => Promise<ApiResult<string>>;
  success?: (value: string) => void;
  activeSchema?: typeof schema | null;
}) {
  const form = usePublicForm({ schema: activeSchema, initialValues, submit, onSuccess: success });
  return (
    <form onSubmit={form.handleSubmit} noValidate>
      {activeSchema && <TextField label="Name" {...form.field('displayName')} />}
      {activeSchema && <TextField label="Confirm" {...form.field('confirmation')} />}
      {form.formError && <p role="status">{form.formError}</p>}
      <button type="submit" disabled={form.busy}>
        Submit
      </button>
      <button type="button" onClick={() => form.setFieldValue('displayName', 'Other')}>
        Change value
      </button>
      <button type="button" onClick={form.reset}>
        Reset
      </button>
    </form>
  );
}
const rejection = (messages: readonly string[]) =>
  apiFailure('validation', null, {
    'body.display_name': messages
  });

describe('Formik shared error lifecycle', () => {
  it('focuses invalid input, shows current multiple messages, clears on edits and retains unrelated errors', async () => {
    const user = userEvent.setup();
    const submit = vi
      .fn()
      .mockResolvedValue(rejection(['Use two characters.', 'Provide a value.']));
    render(<Example submit={submit} />);
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    const name = screen.getByRole('textbox', { name: 'Name' });
    expect(name).toHaveFocus();
    expect(submit).not.toHaveBeenCalled();
    await user.type(name, 'Long');
    await user.type(screen.getByRole('textbox', { name: 'Confirm' }), 'Long');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    await waitFor(() => expect(name).toHaveAccessibleDescription(/Use two characters/));
    expect(submit).toHaveBeenCalledOnce();
    await user.click(screen.getByRole('button', { name: 'Change value' }));
    expect(name).not.toHaveAttribute('aria-invalid');
  });

  it('retains an unchanged server error on locally invalid resubmission', async () => {
    const user = userEvent.setup();
    const submit = vi.fn().mockResolvedValue(rejection(['Use two characters.']));
    render(<Example submit={submit} />);
    const name = screen.getByRole('textbox', { name: 'Name' });
    const confirm = screen.getByRole('textbox', { name: 'Confirm' });
    await user.type(name, 'Valid');
    await user.type(confirm, 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    await waitFor(() => expect(name).toHaveAccessibleDescription('Error: Use two characters.'));
    await user.clear(confirm);
    await user.type(confirm, 'Other');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    expect(submit).toHaveBeenCalledOnce();
    expect(name).toHaveAccessibleDescription('Error: Use two characters.');
    expect(confirm).toHaveAccessibleDescription('Error: Values must match.');
    expect(name).toHaveFocus();
  });

  it('renders an approved PHP validation envelope through the shared decoder', async () => {
    const user = userEvent.setup();
    const submit = vi.fn().mockResolvedValue(
      decodeResponse(
        400,
        {
          status: 'fail',
          data: { fields: { 'body.display_name': ['Use two characters.', 'Invalid value.'] } }
        },
        () => null,
        null
      )
    );
    render(<Example submit={submit} />);
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Valid');
    await user.type(screen.getByRole('textbox', { name: 'Confirm' }), 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    await waitFor(() =>
      expect(screen.getByRole('textbox', { name: 'Name' })).toHaveAccessibleDescription(
        'Error: Use two characters.'
      )
    );
    expect(screen.getByRole('status')).toHaveTextContent('Please check the form');
    expect(screen.queryByText('Invalid value.')).not.toBeInTheDocument();
  });

  it('ignores old A to B to A responses while still reporting real success', async () => {
    const user = userEvent.setup();
    const pending = deferred<ApiResult<string>>();
    const success = vi.fn();
    render(<Example submit={() => pending.promise} success={success} />);
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Valid');
    await user.type(screen.getByRole('textbox', { name: 'Confirm' }), 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    await user.click(screen.getByRole('button', { name: 'Change value' }));
    await user.clear(screen.getByRole('textbox', { name: 'Name' }));
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Valid');
    pending.resolve(rejection(['Use two characters.']));
    await waitFor(() => expect(screen.getByRole('button', { name: 'Submit' })).not.toBeDisabled());
    expect(screen.queryByText('Use two characters.')).not.toBeInTheDocument();
    expect(success).not.toHaveBeenCalled();
  });

  it('rejects superseded failures and reset responses without moving focus', async () => {
    const user = userEvent.setup();
    const earlier = deferred<ApiResult<string>>();
    const later = deferred<ApiResult<string>>();
    const submit = vi.fn().mockReturnValueOnce(earlier.promise).mockReturnValueOnce(later.promise);
    render(<Example submit={submit} />);
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Valid');
    await user.type(screen.getByRole('textbox', { name: 'Confirm' }), 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    // Programmatic submit while the first attempt is still outstanding.
    fireEvent.submit(screen.getByRole('textbox', { name: 'Name' }).closest('form')!);
    await waitFor(() => expect(submit).toHaveBeenCalledTimes(2));
    later.resolve(rejection(['Use two characters.']));
    await waitFor(() => expect(screen.getByText('Error: Use two characters.')).toBeInTheDocument());
    await user.click(screen.getByRole('button', { name: 'Reset' }));
    earlier.resolve(rejection(['Use two characters.']));
    await earlier.promise;
    expect(screen.queryByText('Error: Use two characters.')).not.toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Name' })).not.toHaveFocus();
  });

  it('keeps independent current server errors while discarding a stale comparison error', async () => {
    const user = userEvent.setup();
    const pending = deferred<ApiResult<string>>();
    render(<Example submit={() => pending.promise} />);
    const name = screen.getByRole('textbox', { name: 'Name' });
    const confirm = screen.getByRole('textbox', { name: 'Confirm' });
    await user.type(name, 'Valid');
    await user.type(confirm, 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    await user.clear(confirm);
    await user.type(confirm, 'Other');
    pending.resolve(
      apiFailure('validation', null, {
        'body.display_name': ['Use two characters.'],
        'body.confirmation': ['Values must match.']
      })
    );
    await waitFor(() => expect(name).toHaveAccessibleDescription('Error: Use two characters.'));
    expect(confirm).toHaveAccessibleDescription('Error: Values must match.');
    // The comparison error is now from the current local value, not an old server result.
    expect(name).not.toHaveFocus();
  });

  it('does not attach unknown fields or unpublished server messages as field errors', async () => {
    const user = userEvent.setup();
    const submit = vi.fn().mockResolvedValue(
      apiFailure('validation', null, {
        'body.display_name': ['server internal path /private'],
        'body.unknown_field': ['Use two characters.']
      })
    );
    render(<Example submit={submit} />);
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Valid');
    await user.type(screen.getByRole('textbox', { name: 'Confirm' }), 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    await waitFor(() =>
      expect(screen.getByRole('status')).toHaveTextContent('Please check the form')
    );
    expect(screen.queryByText(/internal path/)).not.toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Name' })).not.toHaveAttribute('aria-invalid');
  });

  it('fences outstanding failures after schema replacement and unmount', async () => {
    const user = userEvent.setup();
    const pending = deferred<ApiResult<string>>();
    const submit = vi.fn().mockReturnValue(pending.promise);
    const { rerender, unmount } = render(<Example submit={submit} />);
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Valid');
    await user.type(screen.getByRole('textbox', { name: 'Confirm' }), 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    rerender(<Example submit={submit} activeSchema={null} />);
    pending.resolve(rejection(['Use two characters.']));
    await pending.promise;
    expect(screen.queryByText(/Use two characters/)).not.toBeInTheDocument();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    unmount();
  });

  it('discards a pending rejection after unmount', async () => {
    const user = userEvent.setup();
    const pending = deferred<ApiResult<string>>();
    const submit = vi.fn().mockReturnValue(pending.promise);
    const { unmount } = render(<Example submit={submit} />);
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Valid');
    await user.type(screen.getByRole('textbox', { name: 'Confirm' }), 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    unmount();
    pending.resolve(rejection(['Use two characters.']));
    await pending.promise;
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('never treats an edit or reset as cancellation of an already successful server mutation', async () => {
    const user = userEvent.setup();
    const pending = deferred<ApiResult<string>>();
    const success = vi.fn();
    render(<Example submit={() => pending.promise} success={success} />);
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Valid');
    await user.type(screen.getByRole('textbox', { name: 'Confirm' }), 'Valid');
    await user.click(screen.getByRole('button', { name: 'Submit' }));
    await user.click(screen.getByRole('button', { name: 'Reset' }));
    pending.resolve({ ok: true, value: 'completed', correlationId: null });
    await waitFor(() => expect(success).toHaveBeenCalledWith('completed'));
    expect(screen.getByRole('textbox', { name: 'Name' })).toHaveValue('');
  });
});
