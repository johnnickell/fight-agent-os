import {
  BrandProvider,
  NotFoundPage,
  PageHeading,
  ShellLayout,
  SystemErrorPage
} from '@fight/react/controls';
import { expect, within } from 'storybook/test';

import { harborBrand } from './HarborBrand';
import { expectHarborTheme } from './expectHarborTheme';

import type { Meta, StoryObj } from '@storybook/react-vite';

function HarborShell({ page }: { page: 'public' | 'error' | 'missing' }) {
  return (
    <BrandProvider brand={harborBrand}>
      <ShellLayout publicJourney>
        {page === 'public' ? (
          <PageHeading>Welcome</PageHeading>
        ) : page === 'error' ? (
          <SystemErrorPage />
        ) : (
          <NotFoundPage />
        )}
      </ShellLayout>
    </BrandProvider>
  );
}

const meta = {
  title: 'White label/Harbor Desk/Public',
  component: HarborShell,
  parameters: { applicationFrame: true, harbor: true },
  play: async ({ canvasElement, parameters }) => {
    const canvas = within(canvasElement);
    const logo = canvas.getByRole('img', { name: 'Harbor sailboat' });
    await expect(logo).toHaveAttribute('src', '/harbor.svg');
    await expect(canvas.getByRole('link', { name: 'Harbor sailboat Harbor Desk' })).toBeVisible();
    await expect(canvas.getByRole('main')).toBeVisible();
    await expect(canvasElement.ownerDocument.title).toMatch(/ — Harbor Desk$/);
    await expectHarborTheme(canvasElement, parameters.mode === 'dark');
  }
} satisfies Meta<typeof HarborShell>;
export default meta;

type Story = StoryObj<typeof meta>;
export const PublicLight: Story = {
  args: { page: 'public' },
  globals: { viewport: { value: 'wide' } }
};
export const ErrorDarkNarrow: Story = {
  args: { page: 'error' },
  globals: { viewport: { value: 'narrow' } },
  parameters: { mode: 'dark' }
};
export const PublicDark: Story = {
  args: { page: 'public' },
  parameters: { mode: 'dark' }
};
export const ErrorLight: Story = { args: { page: 'error' } };
export const NotFoundLight: Story = { args: { page: 'missing' } };
