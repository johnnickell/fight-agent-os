import { useState, useSyncExternalStore } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ThemePreferenceStore } from '@/features/theme/ThemePreference';
import { ShellLayout } from '@/layouts/ShellLayout';
import { LoadingPage } from '@/pages/LoadingPage';
import { NotFoundPage } from '@/pages/NotFoundPage';
import { SystemErrorPage } from '@/pages/SystemErrorPage';

import type { Meta, StoryObj } from '@storybook/react-vite';

function PublicShellExample({
  withoutTheme = false,
  dark = false,
  page = 'example'
}: {
  withoutTheme?: boolean;
  dark?: boolean;
  page?: 'example' | 'not-found' | 'loading' | 'system-error';
}) {
  const [theme] = useState(
    () =>
      new ThemePreferenceStore({
        root: document.createElement('div'),
        storage: {
          getItem: () => (dark ? 'dark' : null),
          setItem: () => {},
          removeItem: () => {}
        },
        media: () => window.matchMedia('(prefers-color-scheme: dark)'),
        subscribeStorage: () => () => {}
      })
  );
  const appearance = useSyncExternalStore(theme.subscribe, theme.getSnapshot);
  return (
    <div className="catalog-preview catalog-frame-preview" data-bs-theme={appearance.effective}>
      <ShellLayout {...(!withoutTheme && { theme })}>
        {page === 'not-found' ? (
          <NotFoundPage />
        ) : page === 'loading' ? (
          <LoadingPage />
        ) : page === 'system-error' ? (
          <SystemErrorPage />
        ) : (
          <>
            <h1>Public shell</h1>
            <p>
              Shared header presentation. Page content and recovery belong to their existing owners.
            </p>
          </>
        )}
      </ShellLayout>
    </div>
  );
}

const meta = {
  title: 'Application/Public Shell',
  component: PublicShellExample,
  parameters: { layout: 'fullscreen', applicationFrame: true }
} satisfies Meta<typeof PublicShellExample>;
export default meta;
type Story = StoryObj<typeof meta>;

export const SystemAppearance: Story = {};
export const NotFound: Story = {
  args: { page: 'not-found' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('heading', { level: 1, name: 'Page not found' })).toBeVisible();
    await expect(canvas.getByRole('link', { name: 'Return to application' })).toHaveAttribute(
      'href',
      '/'
    );
    await expect(canvas.getByRole('main')).toBeVisible();
  }
};
export const NotFoundDark: Story = {
  args: { page: 'not-found', dark: true },
  parameters: { mode: 'dark' }
};
export const SystemErrorDark: Story = {
  args: { page: 'system-error', dark: true },
  parameters: { mode: 'dark' }
};
export const Loading: Story = { args: { page: 'loading' } };
export const SystemError: Story = { args: { page: 'system-error' } };
export const WithoutTheme: Story = {
  args: { withoutTheme: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('link', { name: 'Fight Agent OS' })).toHaveAttribute('href', '/');
    await expect(canvas.queryByRole('group', { name: 'Appearance' })).not.toBeInTheDocument();
    await expect(canvas.getByRole('main')).toBeVisible();
    await expect(canvas.getByRole('link', { name: 'Skip to content' })).toHaveAttribute(
      'href',
      '#main-content'
    );
  }
};
export const NarrowDark: Story = {
  args: { dark: true },
  parameters: { mode: 'dark' },
  globals: { viewport: { value: 'narrow', isRotated: false } }
};
export const AppearanceKeyboard: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const appearance = canvas.getByRole('group', { name: 'Appearance' });
    const system = canvas.getByRole('radio', { name: 'System' });
    await expect(system).toBeChecked();
    system.focus();
    await userEvent.keyboard('{ArrowRight}');
    const light = canvas.getByRole('radio', { name: 'Light' });
    await expect(light).toBeChecked();
    await expect(light).toHaveFocus();
    await expect(appearance).toHaveAccessibleDescription('Showing light mode');
    await userEvent.keyboard('{ArrowRight}');
    await expect(canvas.getByRole('radio', { name: 'Dark' })).toBeChecked();
    await expect(appearance).toHaveAccessibleDescription('Showing dark mode');
    await userEvent.keyboard('{ArrowRight}');
    await expect(system).toBeChecked();
    await expect(system).toHaveFocus();
    const view = canvasElement.ownerDocument.defaultView;
    await expect(appearance).toHaveAccessibleDescription(
      `Showing ${view?.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'} mode`
    );
  }
};
