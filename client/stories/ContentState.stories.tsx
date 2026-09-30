import { expect, fn, userEvent, within } from 'storybook/test';

import { ContentState } from '@/components/ContentState';

import type { Meta, StoryObj } from '@storybook/react-vite';

const meta = {
  title: 'Foundation/Content state',
  component: ContentState
} satisfies Meta<typeof ContentState>;
export default meta;
type Story = StoryObj<typeof meta>;

export const Loading: Story = {
  args: {
    kind: 'loading',
    title: 'Loading',
    message: 'Waiting for example content…'
  }
};
export const Empty: Story = {
  args: {
    kind: 'empty',
    title: 'No examples yet',
    message: 'Nothing to display. This is not a loading error.'
  }
};
export const Failure: Story = {
  args: {
    kind: 'error',
    title: 'Unable to load',
    message: 'Example content is unavailable. You can try again.',
    onRetry: fn()
  },
  play: async ({ canvasElement, args }) => {
    await userEvent.click(within(canvasElement).getByRole('button', { name: 'Try again' }));
    await expect(args.onRetry).toHaveBeenCalledOnce();
  }
};
export const Success: Story = {
  args: {
    kind: 'success',
    title: 'Complete',
    message: 'Example content is ready.'
  }
};
