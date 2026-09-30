import { useSyncExternalStore } from 'react';

import { Button } from '@/components/Button';
import { ContentPanel } from '@/components/ContentPanel';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';

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

// Only a catalog OS preview: no preference storage, bootstrap or runtime selector.
const media = () => window.matchMedia('(prefers-color-scheme: dark)');
const subscribe = (notify: () => void) => {
  const query = media();
  query.addEventListener('change', notify);
  return () => query.removeEventListener('change', notify);
};
function SystemExample() {
  const dark = useSyncExternalStore(subscribe, () => media().matches);
  return (
    <div className="catalog-preview" data-bs-theme={dark ? 'dark' : 'light'}>
      <Examples />
    </div>
  );
}
export const SystemPreview: Story = { render: () => <SystemExample /> };
