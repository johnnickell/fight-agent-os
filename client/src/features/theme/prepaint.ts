import { captureActivation, discardActivationFragment, takeActivation } from '@fight/react/account';
import { clearLoginLocation } from '@fight/react/account';
import { captureReset, discardResetFragment, takeReset } from '@fight/react/account';
import { browserThemeEnvironment, prepaintTheme } from '@fight/react/controls';

if (!captureReset(window, window.location, window.history))
  window.location.replace('/reset-password/complete');
window.addEventListener('pagehide', () => takeReset(window).clear(), { once: true });
if (!clearLoginLocation(window.location, window.history)) window.location.replace('/login');
if (
  !captureActivation(window, window.location, window.history) &&
  (window.location.hash !== '' || window.location.search !== '')
)
  window.location.replace('/activate');
window.addEventListener('pagehide', () => takeActivation(window).clear(), { once: true });
window.addEventListener('hashchange', () => {
  if (discardResetFragment(window, window.location, window.history))
    window.dispatchEvent(new Event('fight:reset-retired'));
  if (window.location.pathname === '/reset-password/complete' && window.location.hash !== '')
    window.location.replace('/reset-password/complete');
  if (!clearLoginLocation(window.location, window.history)) window.location.replace('/login');
  if (discardActivationFragment(window, window.location, window.history))
    window.dispatchEvent(new Event('fight:activation-retired'));
  if (window.location.pathname === '/activate' && window.location.hash !== '')
    window.location.replace('/activate');
});
prepaintTheme(browserThemeEnvironment());
