import { useSyncExternalStore } from 'react';

import type { ThemePreference, ThemePreferenceStore } from '@/features/theme/ThemePreference';

const choices: readonly ThemePreference[] = ['system', 'light', 'dark'];

export function ThemeSelector({ theme }: { theme: ThemePreferenceStore }) {
  const { preference, effective } = useSyncExternalStore(theme.subscribe, theme.getSnapshot);
  return (
    <fieldset className="theme-selector">
      <legend>Appearance</legend>
      <div className="theme-selector-options">
        {choices.map((choice) => (
          <label key={choice} className="theme-selector-option">
            <input
              type="radio"
              name="appearance"
              value={choice}
              checked={preference === choice}
              onChange={() => theme.select(choice)}
            />
            <span>{choice === 'system' ? 'System' : choice === 'light' ? 'Light' : 'Dark'}</span>
          </label>
        ))}
      </div>
      <small>Showing {effective} mode</small>
    </fieldset>
  );
}
