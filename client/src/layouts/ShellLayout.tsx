import type { ReactNode } from 'react';

export function ShellLayout({ children }: { children: ReactNode }) {
  return (
    <>
      <a className="skip-link" href="#main-content">
        Skip to content
      </a>
      <header className="border-bottom py-3">
        <div className="container shell-width">
          <nav aria-label="Foundation">
            <a href="/app">Fight Agent OS</a>
          </nav>
        </div>
      </header>
      <main
        id="main-content"
        tabIndex={-1}
        className="container shell-width py-5"
      >
        {children}
      </main>
      <footer className="border-top py-3">
        <div className="container shell-width">Application foundation</div>
      </footer>
    </>
  );
}
