import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { expect, it } from 'vitest';

import { ThemePreferenceStore } from '@/features/theme/ThemePreference';
import { ThemeSelector } from '@/features/theme/ThemeSelector';

it('offers one named keyboard-operable radio group with explicit selection and resolved status', async () => {
  const values = new Map<string, string>();
  const root = document.createElement('html');
  const theme = new ThemePreferenceStore({
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
    media: () => ({ matches: true }),
    subscribeStorage: () => () => {}
  });
  const user = userEvent.setup();
  const view = render(<ThemeSelector theme={theme} />);
  expect(screen.getByRole('group', { name: 'Appearance' })).toBeVisible();
  expect(screen.getByRole('radio', { name: 'System' })).toBeChecked();
  expect(screen.getByText('Showing dark mode')).toBeVisible();
  await user.tab();
  expect(screen.getByRole('radio', { name: 'System' })).toHaveFocus();
  await user.keyboard('{ArrowRight}');
  expect(screen.getByRole('radio', { name: 'Light' })).toBeChecked();
  expect(screen.getByText('Showing light mode')).toBeVisible();
  expect(root.getAttribute('data-bs-theme')).toBe('light');
  view.unmount();
});
