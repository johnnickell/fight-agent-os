import { useId } from 'react';

import type { ReactNode } from 'react';

type ContentPanelProps = {
  title: string;
  children: ReactNode;
  actions?: ReactNode;
};

/**
 * Composes a labelled section whose header actions wrap without constraining content width
 */
export function ContentPanel({ title, children, actions }: ContentPanelProps) {
  const headingId = useId();
  return (
    <section className="catalog-panel" aria-labelledby={headingId}>
      <header className="catalog-panel-header">
        <h2 id={headingId}>{title}</h2>
        {actions && <div className="catalog-actions">{actions}</div>}
      </header>
      {children}
    </section>
  );
}
