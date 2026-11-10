import { ThemeSelector } from '@/features/theme/ThemeSelector';

import type { ThemePreferenceStore } from '@/features/theme/ThemePreference';
import type { ReactNode } from 'react';

export function ShellLayout({
  children,
  theme,
  publicJourney = false
}: {
  children: ReactNode;
  theme?: ThemePreferenceStore | undefined;
  publicJourney?: boolean;
}) {
  return (
    <>
      <a className="skip-link" href="#main-content">
        Skip to content
      </a>
      <header className={`shell-header border-bottom${publicJourney ? ' activation-header' : ''}`}>
        <div className="container shell-width shell-header-row">
          <nav aria-label="Application">
            <a href="/">Fight Agent OS</a>
          </nav>
          {theme && <ThemeSelector theme={theme} compact />}
        </div>
      </header>
      <main id="main-content" tabIndex={-1} className="container shell-width py-5">
        {children}
      </main>
      <footer className="border-top py-3">
        <div className="container shell-width">Fight Agent OS</div>
      </footer>
    </>
  );
}
