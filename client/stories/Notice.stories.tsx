import { expect, within } from 'storybook/test';

import { Notice } from '@/components/Notice';

import type { Meta, StoryObj } from '@storybook/react-vite';

const meta = {
  title: 'Foundation/Notice',
  component: Notice,
  args: {
    tone: 'info',
    title: 'Information',
    children: 'These are invented local examples.'
  }
} satisfies Meta<typeof Notice>;
export default meta;
type Story = StoryObj<typeof meta>;

export const Information: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('status')).toHaveAttribute('aria-atomic', 'true');
    await expect(canvas.queryByRole('alert')).not.toBeInTheDocument();
  }
};
export const Warning: Story = {
  args: {
    tone: 'warning',
    title: 'Warning',
    children: 'Review the example before continuing.'
  }
};
export const Failure: Story = {
  args: {
    tone: 'danger',
    title: 'Error',
    children: 'The example could not be completed.',
    announce: 'assertive'
  },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('alert')).toHaveAttribute('aria-atomic', 'true');
    await expect(canvas.queryByRole('status')).not.toBeInTheDocument();
  }
};
export const Success: Story = {
  args: {
    tone: 'success',
    title: 'Success',
    children: 'The example is complete.'
  }
};
