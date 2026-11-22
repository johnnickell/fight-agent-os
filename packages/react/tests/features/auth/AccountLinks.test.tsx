import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { AccountLinksProvider, useAccountLinks } from '@fight/react/account';

function Example() {
  const resolve = useAccountLinks();
  const signIn = resolve('signIn');
  return <>{signIn && <a href={signIn.href}>Sign in</a>}</>;
}

describe('account destination composition', () => {
  it('keeps host routing outside the package and accepts a local destination', () => {
    render(
      <AccountLinksProvider
        resolve={(destination) =>
          destination === 'signIn'
            ? { href: '/access/sign-in', requirements: [{ kind: 'public' }] }
            : null
        }
      >
        <Example />
      </AccountLinksProvider>
    );
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute(
      'href',
      '/access/sign-in'
    );
  });

  it('omits a destination the host does not supply', () => {
    render(
      <AccountLinksProvider resolve={() => null}>
        <Example />
      </AccountLinksProvider>
    );
    expect(screen.queryByRole('link')).toBeNull();
  });

  it('rejects a destination outside the host', () => {
    expect(() =>
      render(
        <AccountLinksProvider
          resolve={() => ({ href: '//example.test/sign-in', requirements: [] })}
        >
          <Example />
        </AccountLinksProvider>
      )
    ).toThrow('Invalid account destination');
  });
});
