import { useState, useSyncExternalStore } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { Button } from '@/components/Button';
import { ContentPanel } from '@/components/ContentPanel';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { ThemePreferenceStore } from '@/features/theme/ThemePreference';
import { ThemeSelector } from '@/features/theme/ThemeSelector';

import type { Meta, StoryObj } from '@storybook/react-vite';

function Examples() {
  return (
    <div className="catalog-example-stack">
      <ContentPanel
        title="Example collection with a deliberately long heading"
        actions={
          <>
            <Button>New example</Button>
            <Button disabled>Unavailable</Button>
          </>
        }
      >
        <TextField
          label="Example label"
          description="Long local text wraps: sample_collection_with_an_unbroken_label_for_narrow_layout_evidence"
          error="Enter a label before continuing."
          required
        />
      </ContentPanel>
      <ContentPanel title="Loading example">
        <ContentState kind="loading" title="Loading" message="Waiting for example content…" />
      </ContentPanel>
      <ContentState kind="empty" title="No examples yet" message="Nothing to display." />
      <ContentState
        kind="error"
        title="Unable to load"
        message="Example content is unavailable."
        onRetry={() => {}}
      />
      <Notice tone="success" title="Success">
        Example completed. No records were changed.
      </Notice>
      <Button busy busyLabel="Saving example…">
        Save example
      </Button>
    </div>
  );
}

const meta = {
  title: 'Foundation/Composition',
  component: Examples
} satisfies Meta<typeof Examples>;
export default meta;
type Story = StoryObj<typeof meta>;

export const NarrowLight: Story = {
  globals: { viewport: { value: 'narrow', isRotated: false } }
};
export const WideLight: Story = {
  globals: { viewport: { value: 'wide', isRotated: false } }
};
export const NarrowDark: Story = {
  parameters: { mode: 'dark' },
  globals: { viewport: { value: 'narrow', isRotated: false } }
};
export const WideDark: Story = {
  parameters: { mode: 'dark' },
  globals: { viewport: { value: 'wide', isRotated: false } }
};

// Catalog-owned environment; the production preference owner and selector are exercised
// without modifying a visitor's real preference or treating examples as authorization.
function ThemeExample({ initial }: { initial: 'system' | 'light' | 'dark' }) {
  const [theme] = useState(() => {
    const values = new Map<string, string>();
    if (initial !== 'system') values.set('fight-agent-os.theme.v1', initial);
    const root = document.createElement('div');
    return new ThemePreferenceStore({
      root,
      storage: {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => {
          values.set(key, value);
        },
        removeItem: (key) => {
          values.delete(key);
        }
      },
      media: () => window.matchMedia('(prefers-color-scheme: dark)'),
      subscribeStorage: () => () => {}
    });
  });
  const snapshot = useSyncExternalStore(theme.subscribe, theme.getSnapshot);
  return (
    <div className="catalog-preview" data-bs-theme={snapshot.effective}>
      <ThemeSelector theme={theme} />
      <Examples />
    </div>
  );
}
export const SystemPreview: Story = { render: () => <ThemeExample initial="system" /> };
export const ThemeLight: Story = {
  render: () => <ThemeExample initial="light" />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const group = canvas.getByRole('group', { name: 'Appearance' });
    await expect(group).toBeVisible();
    await expect(canvas.getByRole('radio', { name: 'Light' })).toBeChecked();
    const dark = canvas.getByRole('radio', { name: 'Dark' });
    await userEvent.click(dark);
    await expect(dark).toBeChecked();
    await expect(canvas.getByText('Showing dark mode')).toBeVisible();
    await userEvent.click(canvas.getByRole('radio', { name: 'Light' }));
    await expect(canvas.getByText('Showing light mode')).toBeVisible();
  }
};
export const ThemeDark: Story = { render: () => <ThemeExample initial="dark" /> };
