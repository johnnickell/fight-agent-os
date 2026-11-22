import { createContext, useContext, useEffect } from 'react';

import type { ReactNode } from 'react';

export type Brand = Readonly<{
  name: string;
  logo?: Readonly<{ src: string; alt: string }>;
}>;

const fallback: Brand = Object.freeze({ name: 'Application' });
const BrandContext = createContext<Brand | null>(null);

function validText(value: unknown): value is string {
  return (
    typeof value === 'string' &&
    value.length > 0 &&
    value.length <= 100 &&
    value.trim() === value &&
    !/[\p{Cc}\p{Cf}]/u.test(value)
  );
}

export function defineBrand(brand: Brand): Brand {
  if (
    typeof brand !== 'object' ||
    brand === null ||
    !Object.hasOwn(brand, 'name') ||
    !validText(brand.name)
  )
    throw new Error('Invalid product name');
  if (Object.keys(brand).some((key) => key !== 'name' && key !== 'logo'))
    throw new Error('Unsupported branding option');
  if (!Object.hasOwn(brand, 'logo')) return Object.freeze({ name: brand.name });
  const logo = brand.logo;
  if (
    typeof logo !== 'object' ||
    logo === null ||
    Object.keys(logo).length !== 2 ||
    !Object.hasOwn(logo, 'src') ||
    !Object.hasOwn(logo, 'alt') ||
    !validText(logo.alt) ||
    typeof logo.src !== 'string' ||
    logo.src.length > 512 ||
    !/^\/(?:[A-Za-z0-9_-]+\/)*[A-Za-z0-9_-]+\.(?:svg|png|webp|avif)(?![\s\S])/.test(logo.src)
  )
    throw new Error('Invalid local product logo');
  return Object.freeze({ name: brand.name, logo: Object.freeze({ src: logo.src, alt: logo.alt }) });
}

export function BrandProvider({ brand, children }: { brand: Brand; children: ReactNode }) {
  return <BrandContext.Provider value={defineBrand(brand)}>{children}</BrandContext.Provider>;
}

export function useBrand(defaultBrand: Brand = fallback): Brand {
  return useContext(BrandContext) ?? defaultBrand;
}

export function usePageTitle(title: string): void {
  const { name } = useBrand();
  useEffect(() => {
    document.title = `${title} — ${name}`;
  }, [name, title]);
}

export function BrandLink({ href = '/', brand }: { href?: string; brand: Brand }) {
  const { name, logo } = defineBrand(brand);
  return (
    <a className="ui-brand" href={href} aria-label={logo ? `${logo.alt} ${name}` : undefined}>
      {logo && <img src={logo.src} alt={logo.alt} width="24" height="24" />}
      {name}
    </a>
  );
}
