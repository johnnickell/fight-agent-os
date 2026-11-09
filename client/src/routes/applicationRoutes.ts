import type { PresentationIconName } from '@/components/PresentationIcon';
import type { RegisteredRoute } from '@/routes/GuardedRoute';

export type ApplicationRoute = RegisteredRoute &
  Readonly<{
    title: string;
    access: 'public' | 'protected';
    destination:
      | 'root'
      | 'login'
      | 'recovery'
      | 'reset-completion'
      | 'activation'
      | 'dashboard'
      | 'password-change'
      | 'sessions'
      | 'users'
      | 'invitation'
      | 'invite-user'
      | 'invite-super-admin'
      | 'landing'
      | 'swagger';
    group?: 'Workspace' | 'Security' | 'Administration';
    icon?: PresentationIconName;
    availability?: string;
    targetUserId?: string;
  }>;

const authenticated = [{ kind: 'authenticated' }] as const;
const permission = (...all: string[]) =>
  [{ kind: 'authenticated' }, { kind: 'permissions', all }] as const;
const publicAccess = [{ kind: 'public' }] as const;

/** Complete route metadata is shared by outlets, navigation and one-shot intent evaluation */
export const applicationRoutes: readonly ApplicationRoute[] = [
  ...['/', '/app', '/app/'].map((pathname): ApplicationRoute => ({
    pathname,
    title: 'Application',
    access: 'public',
    requirements: publicAccess,
    destination: 'root'
  })),
  {
    pathname: '/app/login',
    title: 'Sign in',
    access: 'public',
    requirements: publicAccess,
    destination: 'login'
  },
  ...['/app/reset-password', '/app/login/recovery'].map((pathname): ApplicationRoute => ({
    pathname,
    title: 'Reset password',
    access: 'public',
    requirements: publicAccess,
    destination: 'recovery'
  })),
  {
    pathname: '/app/reset-password/complete',
    title: 'Complete password reset',
    access: 'public',
    requirements: publicAccess,
    destination: 'reset-completion'
  },
  {
    pathname: '/app/activate',
    title: 'Activate invitation',
    access: 'public',
    requirements: publicAccess,
    destination: 'activation'
  },
  {
    pathname: '/app/dashboard',
    title: 'Dashboard',
    icon: 'dashboard',
    access: 'protected',
    requirements: permission('VIEW_DASHBOARD'),
    destination: 'dashboard',
    group: 'Workspace',
    intendedRoute: {}
  },
  {
    pathname: '/app/swagger',
    title: 'API Docs',
    icon: 'apiDocs',
    access: 'protected',
    requirements: permission('READ_SWAGGER'),
    destination: 'swagger',
    group: 'Administration',
    intendedRoute: {}
  },
  {
    pathname: '/app/account',
    title: 'Account & Security',
    icon: 'shield',
    access: 'protected',
    requirements: authenticated,
    destination: 'landing',
    group: 'Security',
    intendedRoute: {},
    availability:
      'Change your password or review your active sessions below. Individual revocation is not available yet.'
  },
  {
    pathname: '/app/account/password',
    title: 'Change Password',
    icon: 'key',
    access: 'protected',
    requirements: authenticated,
    destination: 'password-change',
    group: 'Security'
  },
  {
    pathname: '/app/account/sessions',
    title: 'Active Sessions',
    icon: 'sessions',
    access: 'protected',
    requirements: permission('READ_OWN_SESSIONS'),
    destination: 'sessions',
    group: 'Security',
    intendedRoute: {},
    availability:
      'Review your usable sign-ins and current session. Individual revocation is not available yet.'
  },
  {
    pathname: '/app/admin/users',
    title: 'Users',
    icon: 'users',
    access: 'protected',
    requirements: permission('READ_USERS'),
    destination: 'users',
    group: 'Administration',
    intendedRoute: {},
    availability: 'Read the installation-wide user directory and current role assignments.'
  },
  {
    pathname: '/app/admin/invite',
    title: 'Invite User',
    icon: 'invite',
    access: 'protected',
    requirements: permission('INVITE_USERS'),
    destination: 'invite-user',
    group: 'Administration',
    availability: 'Invite one ordinary user. The server assigns only the managed User role.'
  },
  {
    pathname: '/app/admin/invite-super-admin',
    title: 'Invite Super Admin',
    icon: 'elevatedInvite',
    access: 'protected',
    requirements: permission('INVITE_USERS', 'ASSIGN_SUPER_ADMIN'),
    destination: 'invite-super-admin',
    group: 'Administration',
    availability: 'Invite a pending Super Admin only after explicit installation-wide confirmation.'
  },
  {
    pathname: '/app/admin/invitations',
    title: 'Invitation Admin',
    icon: 'invitations',
    access: 'protected',
    requirements: permission('READ_USERS'),
    destination: 'landing',
    group: 'Administration',
    intendedRoute: {},
    availability:
      'Choose a pending user in Users to inspect current invitation status. Recovery additionally requires MANAGE_USER_INVITATIONS.'
  }
];

export function matchApplicationRoute(pathname: string): ApplicationRoute | undefined {
  const target =
    /^\/app\/admin\/invitations\/([a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})$/.exec(
      pathname
    )?.[1];
  if (target)
    return {
      pathname,
      title: 'Pending invitation',
      access: 'protected',
      requirements: permission('READ_USERS'),
      destination: 'invitation',
      targetUserId: target
    };
  return applicationRoutes.find((route) => route.pathname === pathname);
}
