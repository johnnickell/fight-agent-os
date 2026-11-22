import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { BrandProvider, defineBrand, PageHeading, ShellLayout } from '@fight/react/controls';

const logo = { src: '/brand/sailboat.svg', alt: 'Harbor sailboat' };

describe('shell branding', () => {
  it('renders the host name as text, a local logo with alt text, and a branded title', () => {
    render(
      <BrandProvider brand={{ name: '<Harbor Desk>', logo }}>
        <ShellLayout>
          <PageHeading>Sign in</PageHeading>
        </ShellLayout>
      </BrandProvider>
    );
    expect(screen.getByRole('link', { name: 'Harbor sailboat <Harbor Desk>' })).toHaveAttribute(
      'href',
      '/'
    );
    expect(screen.getByRole('img', { name: 'Harbor sailboat' })).toHaveAttribute('src', logo.src);
    expect(document.title).toBe('Sign in — <Harbor Desk>');
  });

  it('uses a neutral fallback without host configuration', () => {
    render(
      <ShellLayout>
        <PageHeading>Unavailable</PageHeading>
      </ShellLayout>
    );
    expect(screen.getByRole('link', { name: 'Application' })).toBeVisible();
    expect(screen.queryByRole('img')).toBeNull();
    expect(document.title).toBe('Unavailable — Application');
  });

  it.each(['', ' ', ' Harbor', 'Harbor\nDesk', 'Harbor\u202eDesk', 'x'.repeat(101)])(
    'rejects invalid brand text',
    (name) => {
      expect(() => defineBrand({ name })).toThrow('Invalid product name');
      expect(() => defineBrand({ name: 'Harbor', logo: { ...logo, alt: name } })).toThrow(
        'Invalid local product logo'
      );
    }
  );

  it.each([
    'https://example.test/mark.svg',
    '//example.test/mark.svg',
    '/brand/../mark.svg',
    '/brand/a.svg?q=1',
    '/brand/a.svg#logo',
    '/brand/mark.txt',
    '/a'.repeat(260) + '.svg'
  ])('rejects unsupported logo locations', (src) => {
    expect(() => defineBrand({ name: 'Harbor', logo: { ...logo, src } })).toThrow(
      'Invalid local product logo'
    );
  });

  it('snapshots the configuration and refuses unknown options', () => {
    const supplied = { name: 'Harbor', logo: { ...logo } };
    const saved = defineBrand(supplied);
    supplied.logo.alt = 'Changed';
    expect(saved.logo?.alt).toBe(logo.alt);
    expect(() => defineBrand({ ...supplied, html: 'Not supported' } as typeof supplied)).toThrow(
      'Unsupported branding option'
    );
  });
});
