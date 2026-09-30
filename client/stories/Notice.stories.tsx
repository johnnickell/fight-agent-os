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

export const Information: Story = {};
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
  }
};
export const Success: Story = {
  args: {
    tone: 'success',
    title: 'Success',
    children: 'The example is complete.'
  }
};
