import { expect, fn, userEvent, within } from 'storybook/test';

import { Button } from '@/components/Button';

import type { Meta, StoryObj } from '@storybook/react-vite';

const meta = {
  title: 'Foundation/Button',
  component: Button,
  args: { children: 'Continue', onClick: fn() }
} satisfies Meta<typeof Button>;
export default meta;
type Story = StoryObj<typeof meta>;

export const Ready: Story = {
  play: async ({ canvasElement, args }) => {
    const button = within(canvasElement).getByRole('button', {
      name: 'Continue'
    });
    await userEvent.click(button);
    await expect(args.onClick).toHaveBeenCalledOnce();
  }
};
export const Disabled: Story = {
  args: { disabled: true },
  play: async ({ canvasElement, args }) => {
    await expect(within(canvasElement).getByRole('button')).toBeDisabled();
    await expect(args.onClick).not.toHaveBeenCalled();
  }
};
export const Busy: Story = {
  args: { busy: true, busyLabel: 'Saving example…' },
  play: async ({ canvasElement, args }) => {
    const button = within(canvasElement).getByRole('button', {
      name: 'Saving example…'
    });
    await userEvent.click(button);
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(args.onClick).not.toHaveBeenCalled();
  }
};
export const KeyboardFocus: Story = {
  parameters: { mode: 'light' },
  play: async ({ canvasElement }) => {
    // Start from the preview document, not Storybook's manager controls.
    (canvasElement.ownerDocument.activeElement as HTMLElement)?.blur();
    await userEvent.tab();
    await expect(within(canvasElement).getByRole('button')).toHaveFocus();
  }
};
