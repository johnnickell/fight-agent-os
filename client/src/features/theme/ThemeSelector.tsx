import { useSyncExternalStore } from 'react';

import { PresentationIcon } from '@/components/PresentationIcon';

import type { ThemePreference, ThemePreferenceStore } from '@/features/theme/ThemePreference';

const choices: readonly ThemePreference[] = ['system', 'light', 'dark'];

export function ThemeSelector({
  theme,
  compact = false
}: {
  theme: ThemePreferenceStore;
  compact?: boolean;
}) {
  const { preference, effective } = useSyncExternalStore(theme.subscribe, theme.getSnapshot);
  return (
    <fieldset className={`theme-selector${compact ? ' theme-selector-compact' : ''}`}>
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
              onKeyDown={(event) => {
                // Native radio arrows select appearance, not a surrounding dropdown destination.
                if (event.key.startsWith('Arrow')) event.stopPropagation();
              }}
            />
            {compact && (
              <PresentationIcon
                name={choice === 'system' ? 'monitor' : choice === 'light' ? 'sun' : 'moon'}
              />
            )}
            <span>{choice === 'system' ? 'System' : choice === 'light' ? 'Light' : 'Dark'}</span>
          </label>
        ))}
      </div>
      <small>Showing {effective} mode</small>
    </fieldset>
  );
}
