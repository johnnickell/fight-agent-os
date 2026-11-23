import { BrandLink, useBrand } from '../components/Brand.js';
import { ThemeSelector } from '../features/theme/ThemeSelector.js';

import type { ThemePreferenceStore } from '../features/theme/ThemePreference.js';
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
  const brand = useBrand();
  return (
    <div className="ui-shell">
      <a className="skip-link" href="#main-content">
        Skip to content
      </a>
      <header className={`shell-header border-bottom${publicJourney ? ' activation-header' : ''}`}>
        <div className="container shell-width shell-header-row">
          <nav aria-label="Application">
            <BrandLink brand={brand} />
          </nav>
          {theme && <ThemeSelector theme={theme} compact />}
        </div>
      </header>
      <main id="main-content" tabIndex={-1} className="container shell-width py-5">
        {children}
      </main>
      <footer className="border-top py-3">
        <div className="container shell-width">{brand.name}</div>
      </footer>
    </div>
  );
}
