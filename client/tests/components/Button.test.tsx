import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Button } from '@/components/Button';

import type { ComponentProps } from 'react';

describe('Button', () => {
  it('defaults to a non-submitting button and activates by keyboard', async () => {
    const user = userEvent.setup();
    const click = vi.fn();
    const submit = vi.fn<NonNullable<ComponentProps<'form'>['onSubmit']>>((event) =>
      event.preventDefault()
    );
    render(
      <form onSubmit={submit}>
        <Button onClick={click}>Continue</Button>
      </form>
    );
    await user.tab();
    expect(screen.getByRole('button', { name: 'Continue' })).toHaveFocus();
    await user.keyboard('{Enter} ');
    expect(click).toHaveBeenCalledTimes(2);
    expect(submit).not.toHaveBeenCalled();
  });

  it('retains focus when becoming busy, suppresses submission, and resumes on completion', async () => {
    const user = userEvent.setup();
    const submit = vi.fn<NonNullable<ComponentProps<'form'>['onSubmit']>>((event) =>
      event.preventDefault()
    );
    const click = vi.fn();
    const form = (busy: boolean) => (
      <form onSubmit={submit}>
        <Button type="submit" busy={busy} busyLabel="Saving…" onClick={click}>
          Save
        </Button>
      </form>
    );
    const { rerender } = render(form(false));
    await user.tab();
    rerender(form(true));
    const button = screen.getByRole('button', { name: 'Saving…' });
    expect(button).toHaveFocus();
    expect(button).toHaveAttribute('aria-disabled', 'true');
    expect(button).toHaveAttribute('aria-busy', 'true');
    expect(button).not.toBeDisabled();
    await user.keyboard('{Enter} ');
    await user.click(button);
    expect(click).not.toHaveBeenCalled();
    expect(submit).not.toHaveBeenCalled();
    rerender(form(false));
    expect(button).toHaveFocus();
    expect(button).toHaveAccessibleName('Save');
    await user.keyboard('{Enter}');
    expect(click).toHaveBeenCalledOnce();
    expect(submit).toHaveBeenCalledOnce();
  });

  it('skips unavailable buttons in tab order and suppresses clicks', async () => {
    const user = userEvent.setup();
    const click = vi.fn();
    render(
      <>
        <Button disabled onClick={click}>
          Unavailable
        </Button>
        <Button>Next</Button>
      </>
    );
    await user.click(screen.getByRole('button', { name: 'Unavailable' }));
    await user.tab();
    expect(screen.getByRole('button', { name: 'Next' })).toHaveFocus();
    expect(click).not.toHaveBeenCalled();
  });

  it.each([true, 'true'] as const)(
    'suppresses explicitly aria-disabled=%s activation',
    async (disabled) => {
      const user = userEvent.setup();
      const click = vi.fn();
      render(
        <Button aria-disabled={disabled} onClick={click}>
          Unavailable
        </Button>
      );
      await user.tab();
      await user.keyboard('{Enter} ');
      expect(click).not.toHaveBeenCalled();
    }
  );

  it('supports a button without a callback and a default busy label', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<Button>Continue</Button>);
    await user.click(screen.getByRole('button'));
    rerender(<Button busy>Continue</Button>);
    expect(screen.getByRole('button')).toHaveAccessibleName('Working…');
  });
});
