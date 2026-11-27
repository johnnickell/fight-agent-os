import { useEffect, useState } from 'react';

import {
  CatalogService,
  CustomRoleService,
  PermissionsPage,
  RolesPage
} from '@fight/react/account';
import { expect, userEvent, within } from 'storybook/test';

import { directoryDependencies, directoryResponse } from '../tests/userDirectoryFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario = 'roles' | 'permissions' | 'loading' | 'empty' | 'denied' | 'conflict';

function Catalog({ scenario = 'roles' }: { scenario?: Scenario }) {
  const [model] = useState(() => {
    const roles = ['ROLE_USER', 'ROLE_RELEASE_MANAGER'].map((name, index) => ({
      role_id: crypto.randomUUID(),
      name,
      managed: index === 0,
      revision: `${crypto.randomUUID()}:1`
    }));
    const permissions = [
      { name: 'VIEW_DASHBOARD', managed: true, tier: 'ADMIN_SAFE' },
      { name: 'MANAGE_ROLE_PERMISSIONS', managed: true, tier: 'SUPER_ADMIN_ONLY' },
      { name: 'READ_CUSTOM_EXPORTS', managed: false, tier: 'ADMIN_SAFE' },
      { name: 'FUTURE_CAPABILITY', managed: true, tier: 'FUTURE_POLICY' }
    ].map((row) => ({ ...row, permission_id: crypto.randomUUID() }));
    const { cache, api } = directoryDependencies(
      async (url, init) => {
        if (scenario === 'loading') return new Promise<Response>(() => {});
        if (init.method === 'DELETE')
          return new Response(
            JSON.stringify({ status: 'fail', data: { message: 'State conflict.' } }),
            {
              status: 409,
              headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
            }
          );
        const table = new URL(url, 'https://storybook.test');
        const page = Number(table.searchParams.get('page') ?? 1);
        const size = Number(table.searchParams.get('per_page') ?? 25);
        const rows =
          scenario === 'empty' ? [] : table.pathname.endsWith('/roles') ? roles : permissions;
        return directoryResponse({
          page,
          per_page: size,
          total_pages: Math.max(1, Math.ceil(rows.length / size)),
          total_records: rows.length,
          records: rows.slice((page - 1) * size, page * size)
        });
      },
      scenario === 'denied'
        ? ['READ_USERS']
        : [
            'READ_ROLES',
            'READ_PERMISSIONS',
            'CREATE_ROLES',
            'UPDATE_ROLES',
            'DELETE_ROLES',
            'MANAGE_ROLE_PERMISSIONS'
          ]
    );
    return {
      cache,
      catalog: new CatalogService(api),
      mutations: new CustomRoleService(api, cache)
    };
  });
  useEffect(() => {
    void model.cache.load();
    return () => model.cache.dispose();
  }, [model]);
  return (
    <main className="container py-3">
      {scenario === 'permissions' ? <PermissionsPage {...model} /> : <RolesPage {...model} />}
    </main>
  );
}

const meta = {
  title: 'Administration/Authority catalogs',
  component: Catalog,
  parameters: { applicationFrame: true }
} satisfies Meta<typeof Catalog>;
export default meta;
type Story = StoryObj<typeof meta>;

export const Roles: Story = {};
export const PermissionTiers: Story = {
  args: { scenario: 'permissions' },
  parameters: { mode: 'dark' }
};
export const Loading: Story = { args: { scenario: 'loading' } };
export const Empty: Story = { args: { scenario: 'empty' } };
export const Denied: Story = { args: { scenario: 'denied' } };
export const PermissionConfirmation: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(
      await canvas.findByRole('button', { name: 'Grant permission for ROLE_RELEASE_MANAGER' })
    );
    const dialog = within(await within(canvasElement.ownerDocument.body).findByRole('dialog'));
    await expect(
      await dialog.findByRole('option', { name: /Admin safe — eligible/ })
    ).toBeEnabled();
  }
};
export const NarrowPermissionConfirmation: Story = {
  globals: { viewport: { value: 'narrow', isRotated: false } },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const trigger = await canvas.findByRole('button', {
      name: 'Grant permission for ROLE_RELEASE_MANAGER'
    });
    const region = canvas.getByRole('region', { name: 'Role catalog' });
    await expect(region.clientWidth).toBeLessThanOrEqual(
      canvasElement.ownerDocument.defaultView?.innerWidth ?? 0
    );
    await userEvent.click(trigger);
    const body = within(canvasElement.ownerDocument.body);
    const dialog = within(await body.findByRole('dialog'));
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toHaveFocus();
    await expect(await dialog.findByRole('option', { name: /Super Admin only/ })).toBeDisabled();
    await userEvent.keyboard('{Escape}');
    await expect(body.queryByRole('dialog')).not.toBeInTheDocument();
    await expect(trigger).toHaveFocus();
  }
};
export const Conflict: Story = {
  args: { scenario: 'conflict' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(
      await canvas.findByRole('button', { name: 'Remove role for ROLE_RELEASE_MANAGER' })
    );
    const body = within(canvasElement.ownerDocument.body);
    const dialog = within(await body.findByRole('dialog'));
    await userEvent.click(dialog.getByRole('button', { name: 'Remove role' }));
    await expect(await canvas.findByText(/operation conflicts/)).toBeVisible();
    await expect(
      await canvas.findByRole('rowheader', { name: 'ROLE_RELEASE_MANAGER' })
    ).toBeVisible();
  }
};
