import { createContext, useContext } from 'react';

import type { AccessRequirement } from '../authority/evaluateAccess.js';
import type { ComponentPropsWithoutRef, ReactNode } from 'react';

export const readUsers = [
  { kind: 'authenticated' },
  { kind: 'permissions', all: ['READ_USERS'] }
] as const;

export type AccountDestination =
  | 'home'
  | 'account'
  | 'signIn'
  | 'requestReset'
  | 'users'
  | 'invite'
  | 'inviteElevated'
  | 'invitation';

type Destination = Readonly<{ href: string; requirements: readonly AccessRequirement[] }>;
export type ResolveAccountLink = (
  destination: AccountDestination,
  userId?: string
) => Destination | null;

const publicAccess = [{ kind: 'public' }] as const;
const authenticated = [{ kind: 'authenticated' }] as const;
const invite = [{ kind: 'permissions', all: ['INVITE_USERS'] }] as const;
const inviteElevated = [
  { kind: 'permissions', all: ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN'] }
] as const;
const defaultLinks = {
  home: { href: '/', requirements: authenticated },
  account: { href: '/account', requirements: authenticated },
  signIn: { href: '/login', requirements: publicAccess },
  requestReset: { href: '/reset-password', requirements: publicAccess },
  users: { href: '/admin/users', requirements: readUsers },
  invite: { href: '/admin/invite', requirements: invite },
  inviteElevated: { href: '/admin/invite-super-admin', requirements: inviteElevated }
} satisfies Record<Exclude<AccountDestination, 'invitation'>, Destination>;

const resolveDefault: ResolveAccountLink = (kind, userId) =>
  kind === 'invitation'
    ? userId
      ? { href: `/admin/invitations/${userId}`, requirements: readUsers }
      : null
    : defaultLinks[kind];

const AccountLinks = createContext<ResolveAccountLink>(resolveDefault);

export function AccountLinksProvider({
  resolve,
  children
}: {
  resolve: ResolveAccountLink;
  children: ReactNode;
}) {
  return <AccountLinks.Provider value={resolve}>{children}</AccountLinks.Provider>;
}

export function useAccountLinks(): ResolveAccountLink {
  const resolve = useContext(AccountLinks);
  return (kind, userId) => {
    const link = resolve(kind, userId);
    if (!link) return null;
    if (!/^\/(?:[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*)?(?![\s\S])/.test(link.href))
      throw new Error('Invalid account destination');
    return link;
  };
}

export function AccountLink({
  to,
  ...props
}: Omit<ComponentPropsWithoutRef<'a'>, 'href'> & { to: AccountDestination }) {
  const link = useAccountLinks()(to);
  return link ? <a {...props} href={link.href} /> : null;
}
