import { usePageTitle } from './Brand.js';

export function PageHeading({ children }: { children: string }) {
  usePageTitle(children);
  return <h1>{children}</h1>;
}
