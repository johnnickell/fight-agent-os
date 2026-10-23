import type { RegisteredRoute } from '@/routes/GuardedRoute';

export type ApplicationRoute = RegisteredRoute &
  Readonly<{
    title: string;
    access: 'public' | 'protected';
    destination: 'root' | 'login' | 'recovery' | 'activation' | 'dashboard' | 'landing' | 'swagger';
    group?: 'Workspace' | 'Security' | 'Administration';
    availability?: string;
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
  {
    pathname: '/app/login/recovery',
    title: 'Sign-in help',
    access: 'public',
    requirements: publicAccess,
    destination: 'recovery'
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
    access: 'protected',
    requirements: permission('VIEW_DASHBOARD'),
    destination: 'dashboard',
    group: 'Workspace',
    intendedRoute: {}
  },
  {
    pathname: '/app/swagger',
    title: 'API documentation',
    access: 'protected',
    requirements: permission('READ_SWAGGER'),
    destination: 'swagger',
    group: 'Administration',
    intendedRoute: {}
  },
  {
    pathname: '/app/account',
    title: 'Account / Security',
    access: 'protected',
    requirements: authenticated,
    destination: 'landing',
    group: 'Security',
    intendedRoute: {},
    availability: 'Account and security operations are not available yet.'
  },
  {
    pathname: '/app/account/password',
    title: 'Change password',
    access: 'protected',
    requirements: authenticated,
    destination: 'landing',
    group: 'Security',
    availability: 'Password change is not available yet. Never send anyone your password.'
  },
  {
    pathname: '/app/account/sessions',
    title: 'Active sessions',
    access: 'protected',
    requirements: permission('READ_OWN_SESSIONS'),
    destination: 'landing',
    group: 'Security',
    intendedRoute: {},
    availability:
      'Session listing and revocation are not available yet. Sign out ends only your current session.'
  },
  {
    pathname: '/app/admin/users',
    title: 'Users',
    access: 'protected',
    requirements: permission('READ_USERS'),
    destination: 'landing',
    group: 'Administration',
    intendedRoute: {},
    availability: 'The user directory is not available yet.'
  },
  {
    pathname: '/app/admin/invite',
    title: 'Invite user',
    access: 'protected',
    requirements: permission('INVITE_USERS'),
    destination: 'landing',
    group: 'Administration',
    availability: 'Invitation issuance is not available here yet.'
  },
  {
    pathname: '/app/admin/invite-super-admin',
    title: 'Invite Super Admin',
    access: 'protected',
    requirements: permission('INVITE_USERS', 'ASSIGN_SUPER_ADMIN'),
    destination: 'landing',
    group: 'Administration',
    availability: 'Elevated invitation issuance is not available here yet.'
  },
  {
    pathname: '/app/admin/invitations',
    title: 'Invitation administration',
    access: 'protected',
    requirements: permission('MANAGE_USER_INVITATIONS'),
    destination: 'landing',
    group: 'Administration',
    intendedRoute: {},
    availability: 'Invitation status and recovery operations are not available here yet.'
  }
];

export function matchApplicationRoute(pathname: string): ApplicationRoute | undefined {
  return applicationRoutes.find((route) => route.pathname === pathname);
}
