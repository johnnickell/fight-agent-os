/** Discards all caller-controlled login URL/history data; navigation intent never comes from a return URL. */
export function clearLoginLocation(
  location: Pick<Location, 'pathname'>,
  history: Pick<History, 'replaceState'>
): boolean {
  if (location.pathname !== '/app/login') return true;
  try {
    history.replaceState(null, '', '/app/login');
    return true;
  } catch {
    return false;
  }
}
