import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { ShellApplication } from './ShellApplication';

const configuration = '{"schema_version":1,"api_base_path":"/api/v1"}';

describe('foundation shell', () => {
  it.each(['/app', '/app/'])(
    'renders home at %s with named landmarks',
    async (pathname) => {
      render(
        <ShellApplication configuration={configuration} pathname={pathname} />,
      );
      expect(
        await screen.findByRole('heading', {
          level: 1,
          name: 'Application foundation',
        }),
      ).toBeVisible();
      expect(screen.getByRole('banner')).toBeVisible();
      expect(
        screen.getByRole('navigation', { name: 'Foundation' }),
      ).toBeVisible();
      expect(screen.getByRole('main')).toBeVisible();
      expect(screen.getByRole('contentinfo')).toBeVisible();
      expect(
        screen.getByText(/No application data is loaded here/),
      ).toBeVisible();
      await waitFor(() =>
        expect(document.title).toBe('Application foundation — Fight Agent OS'),
      );
    },
  );

  it.each(['/app/unknown', '/app/nested/missing.file', '/app/%3Cscript%3E'])(
    'offers safe native recovery from %s',
    async (pathname) => {
      render(
        <ShellApplication configuration={configuration} pathname={pathname} />,
      );
      expect(
        screen.getByRole('heading', { name: 'Page not found' }),
      ).toBeVisible();
      expect(
        screen.getByRole('link', { name: 'Return to foundation' }),
      ).toHaveAttribute('href', '/app');
      expect(document.body.textContent).not.toContain(pathname);
      await waitFor(() =>
        expect(document.title).toBe('Page not found — Fight Agent OS'),
      );
    },
  );

  it('puts the skip link first in keyboard order and targets the main landmark', async () => {
    const user = userEvent.setup();
    render(
      <ShellApplication
        configuration={configuration}
        pathname="/app/missing"
      />,
    );
    await user.tab();
    const skip = screen.getByRole('link', { name: 'Skip to content' });
    expect(skip).toHaveFocus();
    expect(skip).toHaveAttribute('href', `#${screen.getByRole('main').id}`);
    expect(screen.getByRole('main')).toHaveAttribute('tabindex', '-1');
    await user.tab();
    expect(screen.getByRole('link', { name: 'Fight Agent OS' })).toHaveFocus();
    await user.tab();
    expect(
      screen.getByRole('link', { name: 'Return to foundation' }),
    ).toHaveFocus();
  });

  it.each([
    null,
    'private-value-not-for-display',
    '{"schema_version":1,"api_base_path":"/api/v1","token":"private-value-not-for-display"}',
  ])('fails boot safely before route rendering %#', async (source) => {
    render(<ShellApplication configuration={source} pathname="/app/missing" />);
    expect(
      screen.getByRole('heading', { name: 'Application unavailable' }),
    ).toBeVisible();
    expect(screen.getByRole('alert')).toHaveTextContent(
      'contact the installation operator',
    );
    expect(
      screen.getByRole('link', { name: 'Reload application' }),
    ).toHaveAttribute('href', '/app');
    expect(screen.queryByText('Page not found')).not.toBeInTheDocument();
    expect(document.body.textContent).not.toContain(
      'private-value-not-for-display',
    );
    await waitFor(() =>
      expect(document.title).toBe('Application unavailable — Fight Agent OS'),
    );
  });
});
