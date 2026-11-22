import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ActivationPage, LoginPage, ResetCompletionPage } from '@fight/react/account';
import { BrandProvider } from '@fight/react/controls';

import { activationDependencies } from '@fixtures/activationFixtures';
import { loginDependencies } from '@fixtures/loginFixtures';
import { resetCompletionDependencies } from '@fixtures/resetCompletionFixtures';

const brand = { name: 'Harbor Desk' };

describe('public account page branding', () => {
  it('uses the host brand in the login introduction', async () => {
    const dependencies = loginDependencies(vi.fn());
    render(
      <BrandProvider brand={brand}>
        <LoginPage {...dependencies} />
      </BrandProvider>
    );
    await screen.findByRole('button', { name: 'Sign in' });
    expect(screen.getByText('Harbor Desk', { exact: true })).toBeVisible();
    expect(screen.queryByText('FIGHT / AGENT OS', { exact: true })).not.toBeInTheDocument();
  });

  it('uses the host brand before the invitation password is chosen', async () => {
    const dependencies = activationDependencies(vi.fn());
    try {
      render(
        <BrandProvider brand={brand}>
          <ActivationPage {...dependencies} />
        </BrandProvider>
      );
      await screen.findByRole('button', { name: 'Activate account' });
      expect(screen.getByText('Harbor Desk', { exact: true })).toBeVisible();
      expect(screen.queryByText('FIGHT / AGENT OS', { exact: true })).not.toBeInTheDocument();
    } finally {
      dependencies.service.dispose();
    }
  });

  it('uses the host brand before the reset password is chosen', async () => {
    const dependencies = resetCompletionDependencies(vi.fn());
    render(
      <BrandProvider brand={brand}>
        <ResetCompletionPage {...dependencies} />
      </BrandProvider>
    );
    await screen.findByRole('button', { name: 'Reset password' });
    expect(screen.getByText('Harbor Desk', { exact: true })).toBeVisible();
    expect(screen.queryByText('FIGHT / AGENT OS', { exact: true })).not.toBeInTheDocument();
  });
});
