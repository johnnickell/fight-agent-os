/* global document, window, localStorage, sessionStorage, indexedDB, caches, navigator, location, history, fetch, AbortSignal, getComputedStyle, innerWidth, innerHeight, isSecureContext -- Browser callbacks and Node fetch */
import { Buffer } from 'node:buffer';
import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, readdir, readFile, rm } from 'node:fs/promises';
import path from 'node:path';
import { performance } from 'node:perf_hooks';
import process from 'node:process';
import { clearTimeout, setTimeout } from 'node:timers';
import { URL, URLSearchParams } from 'node:url';
import { chromium } from 'playwright';

// Deliberately no test reporter, trace, screenshot, video, HAR, DOM/console dump,
// assertion operands or third-party exception output on credential-bearing pages.
const [scenario, scratch] = process.argv.slice(2);
const origin = process.env.BROWSER_SMOKE_ORIGIN;
const host = new URL(origin).hostname;
const mailbox =
  process.env.BROWSER_SMOKE_NATIVE === '1'
    ? 'http://127.0.0.1:8025/api/v1/'
    : 'http://mailcatcher:8025/api/v1/';
const started = performance.now();
let step = 'fixture';
let assertions = 0;
let context;
let grant = '';
let secret = '';
let leak = false;
let external = false;
const markers = [];
const tokenReads = [];
let markerReadFailed = false;
const observe = async (browserContext) => {
  browserContext.on('request', (request) => {
    const url = new URL(request.url());
    counts.set(url.pathname, (counts.get(url.pathname) ?? 0) + 1);
    if (
      request.url().includes('#') ||
      markers.some(
        (marker) =>
          typeof marker === 'string' &&
          marker.length > 16 &&
          JSON.stringify([request.url(), request.headers().referer]).includes(marker)
      )
    )
      leak = true;
  });
  await browserContext.route('**/*', async (route) => {
    if (new URL(route.request().url()).origin !== origin) {
      external = true;
      await route.abort();
    } else await route.continue();
  });
  browserContext.on('response', (response) => {
    const url = new URL(response.url());
    if (
      ['/api/v1/auth/activate', '/api/v1/auth/login', '/api/v1/auth/refresh'].includes(
        url.pathname
      ) &&
      response.status() === 200
    ) {
      tokenReads.push(
        response
          .json()
          .then((body) => {
            if (typeof body.data?.access_token === 'string') markers.push(body.data.access_token);
          })
          .catch(() => {
            markerReadFailed = true;
          })
      );
    }
  });
};
const counts = new Map();
const check = (condition) => {
  assertions++;
  if (!condition) throw new Error('safe assertion');
};
const command = (name, args) => execFileSync(name, args, { stdio: 'pipe', timeout: 15000 });
const mail = async (suffix = '', options) => {
  const response = await fetch(mailbox + suffix, { ...options, signal: AbortSignal.timeout(5000) });
  check(response.ok);
  return response.status === 204 || options?.method === 'DELETE' ? null : response.json();
};
const until = async (predicate) => {
  const deadline = performance.now() + 10000;
  while (performance.now() < deadline) {
    if (await predicate()) return;
    // Explicit bounded state polling; no arbitrary readiness delay or replay.
    await new Promise((resolve) => setTimeout(resolve, 25));
  }
  throw new Error('safe deadline');
};
const storage = async (page) => {
  check(
    await page.evaluate(async () => {
      const local = Object.entries(localStorage);
      return (
        local.every(
          ([key, value]) =>
            key === 'fight-agent-os.theme.v1' && ['system', 'light', 'dark'].includes(value)
        ) &&
        sessionStorage.length === 0 &&
        (await indexedDB.databases()).length === 0 &&
        (await caches.keys()).length === 0 &&
        (await navigator.serviceWorker.getRegistrations()).length === 0 &&
        location.hash === '' &&
        history.state === null
      );
    })
  );
};
const ready = async (page, text = 'Dashboard') => {
  await page.getByRole('heading', { name: text, exact: true }).waitFor({ timeout: 10000 });
};
const activate = async () => {
  const messages = await mail('messages');
  check(messages.total === 1 && messages.messages.length === 1);
  const message = await mail('message/' + messages.messages[0].ID);
  const links = (message.Text.match(/https:\/\/[^\s<>"']+/g) ?? []).filter((link) =>
    link.startsWith(origin + '/activate#')
  );
  check(links.length === 1);
  grant = links[0].split('#')[1];
  secret = 'Smoke!9z ' + randomBytes(16).toString('hex');
  markers.push(grant, new URLSearchParams(grant).get('credential'), secret);
  const page = await context.newPage();
  if (scenario !== 'fragment') {
    step = 'api-fixture-activation';
    await page.goto(origin + '/api/v1/auth/csrf');
    const result = await page.evaluate(
      async ({ fragment, password }) => {
        const fields = new URLSearchParams(fragment);
        const csrf = await (await fetch('/api/v1/auth/csrf', { cache: 'no-store' })).json();
        const response = await fetch('/api/v1/auth/activate', {
          method: 'POST',
          cache: 'no-store',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Proof': csrf.data.proof
          },
          body: JSON.stringify({
            user_id: fields.get('user_id'),
            credential: fields.get('credential'),
            password,
            password_confirmation: password
          })
        });
        const body = await response.json();
        return { status: response.status, token: body.data?.access_token };
      },
      { fragment: grant, password: secret }
    );
    check(result.status === 200);
    markers.push(result.token);
    await Promise.all(tokenReads);
    await page.goto(origin);
    await ready(page);
    await mail('messages', { method: 'DELETE' });
    return page;
  }
  step = 'activation-navigation';
  await page.goto(links[0]);
  step = 'activation-form';
  await page.getByLabel('New password (required)', { exact: true }).fill(secret);
  await page.getByLabel('Confirm new password (required)', { exact: true }).fill(secret);
  check(page.url() === origin + '/activate');
  await storage(page);
  step = 'activation-submit';
  await page.getByRole('button', { name: 'Activate account' }).click();
  await page.getByText('Account activated', { exact: true }).waitFor();
  check((await page.locator('input[type=password]').count()) === 0);
  const cookies = await context.cookies();
  check(
    cookies.some(
      (cookie) =>
        cookie.name.includes('refresh') &&
        cookie.httpOnly &&
        cookie.secure &&
        cookie.sameSite === 'Strict'
    )
  );
  markers.push(...cookies.map((cookie) => cookie.value));
  const cookiePage = await context.newPage();
  await cookiePage.goto(origin + '/api/v1/auth/csrf');
  check(!(await cookiePage.evaluate(() => document.cookie.includes('refresh'))));
  await cookiePage.close();
  step = 'activation-continuation';
  await page.getByRole('link', { name: 'Continue to the application foundation' }).click();
  await ready(page);
  await mail('messages', { method: 'DELETE' });
  return page;
};
try {
  await import('./dependencies.mjs');
  await mkdir(path.join(scratch, '.pki/nssdb'), { recursive: true, mode: 0o700 });
  command('certutil', ['-N', '-d', 'sql:' + scratch + '/.pki/nssdb', '--empty-password']);
  command('certutil', [
    '-A',
    '-d',
    'sql:' + scratch + '/.pki/nssdb',
    '-n',
    'Prepared envelope public CA',
    '-t',
    'C,,',
    '-i',
    process.env.BROWSER_SMOKE_CA
  ]);
  context = await chromium.launchPersistentContext(scratch + '/profile', {
    headless: true,
    locale: 'en-US',
    timezoneId: 'UTC',
    viewport: { width: 1280, height: 800 },
    colorScheme: 'dark',
    acceptDownloads: false,
    args: [
      '--no-proxy-server',
      `--host-resolver-rules=MAP ${host} ${process.env.BROWSER_SMOKE_ADDRESS}`
    ],
    env: { HOME: scratch, PATH: '/usr/local/bin:/usr/bin:/bin', TZ: 'UTC' }
  });
  check(context.browser().version() === '149.0.7827.0');
  context.setDefaultTimeout(10000);
  await observe(context);
  const readiness = await context.newPage();
  await readiness.route('**/build/main-*.js', (route) => route.abort());
  await until(async () => {
    try {
      const response = await readiness.goto(origin, { timeout: 1000 });
      step = `readiness-http-${response.status()}`;
      return (
        response.status() === 200 &&
        response.headers()['x-browser-smoke-fixture'] === process.env.BROWSER_SMOKE_FIXTURE &&
        (await readiness.evaluate(async () => {
          const result = await fetch('/api/v1/auth/csrf', { signal: AbortSignal.timeout(1000) });
          return result.status === 200 && typeof (await result.json()).data?.proof === 'string';
        }))
      );
    } catch (error) {
      const code = String(error.message).match(/net::(ERR_[A-Z_]+)/)?.[1];
      if (code) step = 'readiness-' + code;
      return false;
    }
  });
  check(await readiness.evaluate(() => isSecureContext));
  await readiness.close();
  step = 'activation';
  const page = await activate();
  step = scenario;
  if (scenario === 'fragment') {
    check(!leak);
    await page.goBack();
    check(!page.url().includes('#'));
    await storage(page);
  } else if (scenario === 'reload') {
    const before = counts.get('/api/v1/auth/refresh') ?? 0;
    const principal = counts.get('/api/v1/me') ?? 0;
    let release;
    let entered;
    const held = new Promise((resolve) => {
      entered = resolve;
    });
    const barrier = new Promise((resolve) => {
      release = resolve;
    });
    await context.route('**/api/v1/auth/refresh', async (route) => {
      check(!route.request().headers().authorization);
      entered();
      await barrier;
      await route.continue();
    });
    await page.reload();
    let timeout;
    try {
      await Promise.race([
        held,
        new Promise((_, reject) => {
          timeout = setTimeout(() => reject(new Error('safe deadline')), 10000);
        })
      ]);
    } finally {
      clearTimeout(timeout);
    }
    check((counts.get('/api/v1/me') ?? 0) === principal);
    check((await page.getByRole('heading', { name: 'Dashboard', exact: true }).count()) === 0);
    release();
    await ready(page);
    await context.unroute('**/api/v1/auth/refresh');
    check((counts.get('/api/v1/auth/refresh') ?? 0) === before + 1);
    check((counts.get('/api/v1/me') ?? 0) > principal);
    await storage(page);
  } else if (scenario === 'tabs') {
    await page.close();
    // A real held Web Lock is the barrier: both cold tabs queue before release.
    const controller = await context.newPage();
    await controller.goto(origin + '/favicon.svg');
    await controller.evaluate(() => {
      window.releaseSmokeLock = null;
      window.smokeLockEntered = false;
      void navigator.locks.request('fight-agent-os.session-mutation.v1', async () => {
        window.smokeLockEntered = true;
        await new Promise((resolve) => {
          window.releaseSmokeLock = resolve;
        });
      });
    });
    await controller.waitForFunction(() => window.smokeLockEntered);
    const before = counts.get('/api/v1/auth/refresh') ?? 0;
    const first = await context.newPage();
    const second = await context.newPage();
    await Promise.all([first.goto(origin), second.goto(origin)]);
    step = 'tabs-queued';
    await until(
      async () => (await controller.evaluate(() => navigator.locks.query())).pending.length === 2
    );
    await controller.evaluate(() => window.releaseSmokeLock());
    step = 'tabs-restored';
    await Promise.all([ready(first), ready(second)]);
    check((counts.get('/api/v1/auth/refresh') ?? 0) === before + 1);
    step = 'tabs-single-leader';
    await first.getByRole('button', { name: 'User Menu', exact: true }).click();
    step = 'tabs-logout-visible';
    await first.getByRole('button', { name: 'Sign Out', exact: true }).click();
    step = 'tabs-logout-convergence';
    await Promise.all(
      [first, second].map((tab) =>
        tab.getByText('Signed out of this page', { exact: true }).waitFor()
      )
    );
    step = 'tabs-logout-bounded';
    await until(() => (counts.get('/api/v1/auth/logout') ?? 0) === 1);
    check((counts.get('/api/v1/auth/logout') ?? 0) === 1);
    await storage(first);
    await storage(second);
  } else if (scenario === 'revocation') {
    // A second cookie jar models another session, NOT cross-device push.
    const second = await context.browser().newContext({ locale: 'en-US', timezoneId: 'UTC' });
    try {
      await observe(second);
      const target = await second.newPage();
      await target.goto(origin + '/login');
      await target
        .getByLabel('Email (required)', { exact: true })
        .fill('browser-smoke@example.test');
      await target.getByLabel('Password (required)', { exact: true }).fill(secret);
      step = 'revocation-login';
      const loggedIn = target.waitForResponse(
        (response) => response.url() === origin + '/api/v1/auth/login' && response.status() === 200
      );
      await target.getByRole('button', { name: 'Sign in', exact: true }).click();
      const access = (await (await loggedIn).json()).data.access_token;
      markers.push(access);
      await ready(target);
      step = 'revocation-list';
      await page.goto(origin + '/account/sessions');
      await ready(page, 'Active Sessions');
      const listPeer = await context.newPage();
      await listPeer.goto(origin + '/account/sessions');
      await ready(listPeer, 'Active Sessions');
      await listPeer.getByRole('button', { name: /Revoke/ }).waitFor();
      check((await listPeer.getByRole('button', { name: /Revoke/ }).count()) === 1);
      await page.getByRole('button', { name: /Revoke/ }).click();
      const cancel = page.getByRole('button', { name: 'Cancel', exact: true });
      await cancel.waitFor();
      check(await cancel.evaluate((element) => element === document.activeElement));
      await page.keyboard.press('Tab');
      await page.keyboard.press('Shift+Tab');
      check(
        await cancel.evaluate((element) => {
          const box = element.getBoundingClientRect();
          const style = getComputedStyle(element);
          return (
            element === document.activeElement &&
            box.top >= 0 &&
            box.left >= 0 &&
            box.right <= innerWidth &&
            box.bottom <= innerHeight &&
            style.outlineStyle !== 'none'
          );
        })
      );
      await page.getByRole('button', { name: 'Confirm revocation' }).click();
      await page
        .getByText('Session revoked. The session list is being checked again.', { exact: true })
        .waitFor();
      step = 'revocation-denial';
      const denial = await target.evaluate(
        async (token) =>
          (
            await fetch('/api/v1/me', {
              headers: { Authorization: `Bearer ${token}` },
              cache: 'no-store'
            })
          ).status,
        access
      );
      step = `revocation-denial-http-${denial}`;
      check(denial === 401);
      await until(
        async () =>
          (await page.getByRole('button', { name: /Revoke/ }).count()) === 0 &&
          (await listPeer.getByRole('button', { name: /Revoke/ }).count()) === 0
      );
      const refreshBefore = counts.get('/api/v1/auth/refresh') ?? 0;
      step = 'revocation-restoration-denied';
      await target.reload();
      await ready(target, 'Good work starts here.');
      check((counts.get('/api/v1/auth/refresh') ?? 0) === refreshBefore + 1);
      await storage(target);
    } finally {
      await second.close();
    }
  } else if (scenario === 'theme') {
    await context.route('**/build/main-*.js', (route) => route.abort());
    const prepaint = await context.newPage();
    await prepaint.goto(origin + '/login');
    check((await prepaint.locator('html').getAttribute('data-bs-theme')) === 'dark');
    check((await prepaint.locator('#app').textContent()) === 'Loading application');
    await context.unroute('**/build/main-*.js');
    await page.emulateMedia({ colorScheme: 'light' });
    await page.waitForFunction(() => document.documentElement.dataset.bsTheme === 'light');
    check((await page.locator('html').getAttribute('data-bs-theme')) === 'light');
    await storage(page);
  } else throw new Error('safe scenario');
  check(!leak && !external);
  if (!page.isClosed()) await storage(page);
  step = 'profile-redaction';
  const version = context.browser().version();
  markers.push(...(await context.cookies()).map((cookie) => cookie.value));
  await Promise.all(tokenReads);
  check(!markerReadFailed);
  await context.close();
  context = null;
  let profileClean = true;
  const inspect = async (directory) => {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const name = path.join(directory, entry.name);
      if (entry.isDirectory()) await inspect(name);
      else if (entry.isFile()) {
        const bytes = await readFile(name);
        if (
          markers.some(
            (marker) =>
              typeof marker === 'string' &&
              marker.length > 16 &&
              bytes.includes(Buffer.from(marker))
          )
        )
          profileClean = false;
      }
    }
  };
  await inspect(scratch + '/profile');
  check(profileClean);
  process.stdout.write(
    `Browser smoke PASS: ${scenario}; ${assertions} assertions; ${((performance.now() - started) / 1000).toFixed(2)}s; Chromium ${version}\n`
  );
} catch {
  const states = [];
  for (const page of context?.pages() ?? []) {
    for (const name of [
      'This link cannot be used',
      'Validation is unavailable',
      'Make it yours.',
      'Good work starts here.',
      'Signed out of this page',
      'Your session has expired or ended',
      'Signed out',
      'Dashboard'
    ]) {
      if (await page.getByText(name, { exact: true }).count()) states.push(name);
    }
    states.push(`password-fields=${await page.locator('input[type=password]').count()}`);
  }
  process.stderr.write(
    `Browser smoke FAIL: ${scenario}; step ${step}; safe states ${states.join(', ')}; no credential artifacts collected\n`
  );
  process.exitCode = 1;
} finally {
  await context?.close();
  try {
    await mail('messages', { method: 'DELETE' });
  } catch {
    process.exitCode = 1;
  }
  await rm(scratch + '/profile', { recursive: true, force: true });
  await rm(scratch + '/.pki', { recursive: true, force: true });
}
