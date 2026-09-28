import { useEffect } from 'react';

export function PageHeading({ children }: { children: string }) {
  useEffect(() => {
    document.title = `${children} — Fight Agent OS`;
  }, [children]);
  return <h1>{children}</h1>;
}
