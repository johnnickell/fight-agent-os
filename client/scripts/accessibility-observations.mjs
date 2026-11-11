// Direct observations of built production-backed stories, not simulated DOM layout.
/* global document, window, getComputedStyle -- Playwright evaluation callbacks */
import assert from 'node:assert/strict';
import { URL } from 'node:url';

export async function observeRendering(page) {
  return page.evaluate(() => {
    const visible = (element) => element.getClientRects().length > 0;
    const bounds = (element) => {
      const { x, y, width, height } = element.getBoundingClientRect();
      return { x, y, width, height };
    };
    return {
      viewport: { width: window.innerWidth, height: window.innerHeight },
      headings: [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')]
        .filter(visible)
        .map((element) => ({ level: element.tagName, text: element.textContent })),
      landmarks: [...document.querySelectorAll('main,nav,header,footer,[role="dialog"]')]
        .filter(visible)
        .map((element) => ({
          role: element.getAttribute('role') ?? element.tagName,
          label: element.getAttribute('aria-label'),
          bounds: bounds(element)
        })),
      announcements: [...document.querySelectorAll('[role="status"],[role="alert"],[aria-live]')]
        .filter(visible)
        .map((element) => ({
          role: element.getAttribute('role'),
          live: element.getAttribute('aria-live'),
          atomic: element.getAttribute('aria-atomic'),
          // Never collect form values or hidden authority fixtures.
          text: element.textContent
        })),
      targets: [...document.querySelectorAll('button,input,select,textarea,a[href],[role="radio"]')]
        .filter(visible)
        .map((element) => ({
          tag: element.tagName,
          label:
            element.getAttribute('aria-label') ??
            element.labels?.[0]?.textContent ??
            element.textContent,
          bounds: bounds(element),
          labelBounds: element.labels?.[0] ? bounds(element.labels[0]) : null,
          disabled:
            element.matches(':disabled') || element.getAttribute('aria-disabled') === 'true',
          inlineLink: element.tagName === 'A' && getComputedStyle(element).display === 'inline'
        })),
      fields: [...document.querySelectorAll('input')].filter(visible).map((element) => ({
        type: element.type,
        autocomplete: element.autocomplete,
        invalid: element.getAttribute('aria-invalid'),
        describedBy: element.getAttribute('aria-describedby') !== null
      })),
      motion: [...document.querySelectorAll('.catalog-spinner,.spinner-border')].map((element) => ({
        animation: getComputedStyle(element).animationName,
        duration: getComputedStyle(element).animationDuration
      }))
    };
  });
}

export async function observePalette(page) {
  const ratios = [];
  const luminance = (hex) => {
    const values = hex
      .replace('#', '')
      .match(/../g)
      .map((part) => parseInt(part, 16) / 255);
    const linear = values.map((value) =>
      value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
    );
    return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
  };
  for (const theme of ['light', 'dark']) {
    const tokens = await page.evaluate((theme) => {
      const element = document.querySelector('.catalog-preview');
      element.setAttribute('data-bs-theme', theme);
      const style = getComputedStyle(element);
      return Object.fromEntries(
        [
          'background',
          'surface',
          'rail',
          'raised',
          'text',
          'muted',
          'quiet',
          'action',
          'on-action',
          'focus',
          'border',
          'danger',
          'info',
          'success',
          'warning',
          'error-background'
        ].map((name) => [name, style.getPropertyValue(`--ui-${name}`).trim()])
      );
    }, theme);
    const pairs = [];
    for (const background of ['background', 'surface', 'rail', 'raised']) {
      for (const foreground of [
        'text',
        'muted',
        'quiet',
        'action',
        'danger',
        'info',
        'success',
        'warning'
      ])
        pairs.push([foreground, background, 4.5]);
      for (const foreground of ['focus', 'border']) pairs.push([foreground, background, 3]);
    }
    pairs.push(['on-action', 'action', 4.5], ['danger', 'error-background', 4.5]);
    for (const [foreground, background, minimum] of pairs) {
      const light = luminance(tokens[foreground]);
      const dark = luminance(tokens[background]);
      const ratio = (Math.max(light, dark) + 0.05) / (Math.min(light, dark) + 0.05);
      ratios.push({
        theme,
        foreground,
        background,
        foregroundColor: tokens[foreground],
        backgroundColor: tokens[background],
        ratio,
        minimum
      });
      assert(
        ratio >= minimum,
        `Contrast below ${minimum}: ${theme} ${foreground}/${background}: ${ratio}`
      );
    }
  }
  return ratios;
}

// Native Tab/Shift+Tab/Enter/Space/Escape only; locator focus/click/fill are intentionally absent.
export async function observeKeyboard(browser, origin, results) {
  for (const width of [320, 1280]) {
    const context = await browser.newContext({
      viewport: { width, height: 900 },
      reducedMotion: 'reduce',
      locale: 'en-US',
      timezoneId: 'UTC'
    });
    try {
      await context.route('**/*', (route) =>
        new URL(route.request().url()).origin === origin ? route.continue() : route.abort()
      );
      const page = await context.newPage();
      const open = async (id) => {
        await page.goto(`${origin}/iframe.html?id=${id}&viewMode=story&globals=a11y.manual:!true`);
        await page.locator('#storybook-root .catalog-preview').first().waitFor();
        await page.evaluate(() => document.fonts.ready);
      };
      let current;
      const begin = (name) => {
        current = { name, width, steps: [], result: 'incomplete' };
        results.push(current);
      };
      const record = async (key) => {
        await page.keyboard.press(key);
        await page.evaluate(
          () =>
            new Promise((done) =>
              window.requestAnimationFrame(() => window.requestAnimationFrame(done))
            )
        );
        const focus = await page.evaluate(() => {
          const element = document.activeElement;
          const rect = element.getBoundingClientRect();
          const style = getComputedStyle(element);
          // Inline links can wrap around empty space inside their union bounding box.
          // Hit-test each actual line box rather than mistaking that space for occlusion.
          const unobscured = [...element.getClientRects()].every((box) => {
            const hit = document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2);
            return hit === element || element.contains(hit);
          });
          return {
            tag: element.tagName,
            label:
              element.getAttribute('aria-label') ??
              element.labels?.[0]?.textContent ??
              element.textContent?.slice(0, 100),
            visible: element.matches(':focus-visible'),
            outline: style.outline,
            outlineOffset: style.outlineOffset,
            bounds: { top: rect.top, bottom: rect.bottom, left: rect.left, right: rect.right },
            inViewport:
              rect.top >= -1 &&
              rect.bottom <= window.innerHeight + 1 &&
              rect.left >= -1 &&
              rect.right <= window.innerWidth + 1,
            unobscured
          };
        });
        current.steps.push({ key, ...focus });
      };
      const tabTo = async (target, reverse = false) => {
        await target.waitFor({ state: 'visible' });
        if (await target.evaluate((element) => element === document.activeElement)) return;
        const maximum =
          (await page.locator('a[href],button,input,select,textarea,[tabindex]').count()) + 2;
        for (let attempt = 0; attempt < maximum; attempt++) {
          await record(reverse ? 'Shift+Tab' : 'Tab');
          if (await target.evaluate((element) => element === document.activeElement)) {
            const focus = current.steps.at(-1);
            assert(
              focus.visible && focus.inViewport && focus.unobscured,
              `Unusable focus: ${current.name} ${focus.label}`
            );
            return;
          }
        }
        throw new Error(`Keyboard target unreachable: ${current.name}`);
      };
      for (const form of [
        {
          id: 'authentication-login--ready-light',
          submit: 'Sign in',
          rejected: 'Sign-in not completed',
          success: 'You’re signed in',
          announcement: 'Session established',
          fields: ['Email (required)', 'Password (required)']
        },
        {
          id: 'authentication-activation--ready-light',
          submit: 'Activate account',
          rejected: 'Activation not completed',
          success: 'Account activated',
          announcement: 'Account activated',
          continuation: 'Continue to the application foundation',
          fields: ['New password (required)', 'Confirm new password (required)']
        },
        {
          id: 'authentication-resetrequest--ready-light',
          submit: 'Request reset link',
          success: 'Check your inbox',
          announcement: 'Request received',
          fields: ['Email (required)']
        },
        {
          id: 'authentication-resetcompletion--ready-light',
          submit: 'Reset password',
          rejected: 'Reset not confirmed',
          success: 'Password reset complete',
          announcement: 'Sign in with your new password',
          continuation: 'Sign in',
          fields: ['New password (required)', 'Confirm new password (required)']
        },
        {
          id: 'authentication-passwordchange--ready-light',
          submit: 'Change password and sign out',
          rejected: 'Password change not confirmed',
          success: 'Password changed — sign in again',
          announcement: 'Password changed — sign in again',
          fields: [
            'Current password (required)',
            'New password (required)',
            'Confirm new password (required)'
          ]
        }
      ].flatMap((form) =>
        form.rejected
          ? [
              { ...form, successful: false },
              { ...form, id: form.id.replace('--ready-light', '--ready-success'), successful: true }
            ]
          : [{ ...form, successful: true }]
      )) {
        begin(`${form.id}: ${form.successful ? 'successful completion' : 'rejection'}`);
        await open(form.id);
        const submit = page.getByRole('button', { name: form.submit, exact: true });
        await tabTo(submit);
        await record('Enter');
        await page.waitForFunction(
          () => document.activeElement?.getAttribute('aria-invalid') === 'true'
        );
        assert(
          await page
            .getByLabel(form.fields[0], { exact: true })
            .evaluate((element) => element === document.activeElement)
        );
        for (const name of form.fields) {
          const field = page.getByLabel(name, { exact: true });
          if (!(await field.evaluate((element) => element === document.activeElement)))
            await tabTo(field);
          // Public dictionary samples only. This observes insertion, not a real password-manager integration.
          await page.keyboard.insertText(
            name.startsWith('Email') ? 'équipe@example.test' : ' P@ssw0rd Unicode é '
          );
        }
        if (form.id.includes('login')) {
          await tabTo(page.getByRole('checkbox'));
          await record('Space');
          assert(await page.getByRole('checkbox').isChecked());
        }
        await tabTo(submit);
        await record('Enter');
        if (form.successful) {
          const bodyFocus = form.id.includes('activation') || form.id.includes('passwordchange');
          const title = bodyFocus
            ? page.getByText(form.success, { exact: true })
            : page.getByRole('heading', { name: form.success, exact: true });
          const status = page.getByRole('status');
          await title.waitFor();
          assert.equal(await status.count(), 1, `Expected one success announcement: ${form.id}`);
          assert(await status.getByText(form.announcement, { exact: true }).isVisible());
          assert.equal(await status.getAttribute('aria-atomic'), 'true');
          assert.equal(await page.getByRole('alert').count(), 0);
          assert.equal(await page.locator('form,input').count(), 0, 'Submitted fields removed');
          // Standalone Activation and the password-change catalog callback announce success
          // without moving focus. Record that boundary honestly; do not synthesize focus.
          assert(
            await (bodyFocus ? page.locator('body') : title).evaluate(
              (element) => element === document.activeElement
            ),
            `Unexpected terminal focus: ${form.id}`
          );
          current.terminal = {
            outcome: form.success,
            announcement: await status.textContent(),
            focus: bodyFocus ? 'BODY (standalone catalog terminal)' : form.success,
            fieldsRemoved: true,
            alerts: 0
          };
          if (form.continuation) {
            const continuation = page.getByRole('link', { name: form.continuation, exact: true });
            await tabTo(continuation);
            current.terminal.continuation = form.continuation;
          }
          current.result =
            'passed: keyboard-only success, exact announcement, terminal focus and field cleanup';
        } else {
          const alert = page.getByRole('alert');
          await alert.getByText(form.rejected, { exact: true }).waitFor();
          assert.equal(await alert.count(), 1);
          current.terminal = { outcome: form.rejected, announcement: await alert.textContent() };
          await tabTo(page.getByLabel(form.fields[0], { exact: true }), true);
          current.result =
            'passed: validation focus, insertion, keyboard submission and exact rejection';
        }
      }
      begin('elevated confirmation cancellation and focus return');
      await open('administration-invite-super-admin--ready');
      await tabTo(page.getByLabel('Email (required)', { exact: true }));
      await page.keyboard.insertText(`${'équipe'.repeat(35)}@example.test`);
      const review = page.getByRole('button', { name: 'Review Super Admin invitation' });
      await tabTo(review);
      await record('Enter');
      const dialog = page.getByRole('dialog');
      await dialog.waitFor();
      const cancel = dialog.getByRole('button', { name: 'Cancel', exact: true });
      assert(await cancel.evaluate((element) => element === document.activeElement));
      await tabTo(dialog.getByRole('button', { name: 'Confirm Super Admin invitation' }), true);
      assert(
        await dialog
          .getByRole('button', { name: 'Confirm Super Admin invitation' })
          .evaluate((element) => element === document.activeElement)
      );
      await record('Tab');
      assert(await cancel.evaluate((element) => element === document.activeElement));
      await record('Escape');
      await dialog.waitFor({ state: 'hidden' });
      assert(await review.evaluate((element) => element === document.activeElement));
      current.result = 'passed: Cancel-first, bidirectional containment, Escape and trigger return';

      begin('elevated invitation keyboard-only successful issuance');
      // Ready already supplies a successful response but has no play helper or submitted values.
      await open('administration-invite-super-admin--ready');
      await tabTo(page.getByLabel('Email (required)', { exact: true }));
      await page.keyboard.insertText('Équipe@EXAMPLE.test');
      await tabTo(page.getByRole('button', { name: 'Review Super Admin invitation', exact: true }));
      await record('Enter');
      const confirmation = page.getByRole('dialog');
      await confirmation.waitFor();
      assert(await confirmation.getByText('équipe@example.test', { exact: true }).isVisible());
      assert(
        await confirmation
          .getByRole('button', { name: 'Cancel', exact: true })
          .evaluate((element) => element === document.activeElement)
      );
      await tabTo(
        confirmation.getByRole('button', { name: 'Confirm Super Admin invitation', exact: true })
      );
      await record('Space');
      await confirmation.waitFor({ state: 'hidden' });
      const accepted = page.getByRole('heading', {
        name: 'Super Admin invitation accepted',
        exact: true
      });
      await accepted.waitFor();
      assert(await accepted.evaluate((element) => element === document.activeElement));
      const update = page.getByRole('status');
      assert.equal(await update.count(), 1);
      assert(await update.getByText('Elevated invitation update', { exact: true }).isVisible());
      assert.match(
        await update.textContent(),
        /The server confirmed the pending invitation, managed Super Admin assignment and required audit steps\./
      );
      assert.equal(await update.getAttribute('aria-atomic'), 'true');
      assert.equal(await page.getByRole('alert').count(), 0);
      assert.equal(await page.locator('form,input').count(), 0);
      assert(
        await page.getByRole('link', { name: 'Read invitation status', exact: true }).isVisible()
      );
      current.terminal = {
        outcome: 'Super Admin invitation accepted',
        announcement: await update.textContent(),
        focus: 'Super Admin invitation accepted',
        fieldsRemoved: true,
        dialogRemoved: true
      };
      await tabTo(page.getByRole('button', { name: 'Start a separate invitation', exact: true }));
      await record('Enter');
      const clearedEmail = page.getByLabel('Email (required)', { exact: true });
      assert.equal(await clearedEmail.inputValue(), '');
      assert(await clearedEmail.evaluate((element) => element === document.activeElement));
      assert.equal(
        await page
          .getByRole('heading', { name: 'Super Admin invitation accepted', exact: true })
          .count(),
        0
      );
      current.result =
        'passed: keyboard-only confirmation activation, exact success, heading focus, announcement and separate-form cleanup';

      begin('ordinary invitation keyboard completion');
      await open('administration-invite-user--ready');
      await tabTo(page.getByLabel('Email (required)', { exact: true }));
      await page.keyboard.insertText('équipe@example.test');
      await tabTo(page.getByRole('button', { name: 'Invite user', exact: true }));
      await record('Enter');
      await page.getByRole('heading', { name: 'Invitation accepted' }).waitFor();
      current.result = 'passed: Unicode insertion and keyboard-only submission';

      begin('session confirmation cancellation');
      await open('security-ownedsessionrevocation--ready');
      await tabTo(page.getByRole('button', { name: 'Revoke session', exact: true }));
      await record('Enter');
      const revocation = page.getByRole('dialog', { name: 'Revoke this other session?' });
      await revocation.waitFor();
      await record('Tab');
      await record('Shift+Tab');
      await record('Escape');
      await revocation.waitFor({ state: 'hidden' });
      assert(
        await page
          .getByRole('button', { name: 'Revoke session', exact: true })
          .evaluate((element) => element === document.activeElement)
      );
      await record('Enter');
      await revocation.waitFor();
      await tabTo(revocation.getByRole('button', { name: 'Confirm revocation' }));
      await record('Enter');
      await page.getByText(/Session revoked\./).waitFor();
      current.result =
        'passed: keyboard entry, containment, cancellation, restoration and explicit revocation';

      begin('invitation recovery and email correction');
      await open('administration-pending-invitation--retryable-failure');
      await tabTo(page.getByRole('button', { name: 'Retry delivery', exact: true }));
      await record('Enter');
      await tabTo(page.getByRole('button', { name: 'Correct email', exact: true }));
      await record('Enter');
      const newEmail = page.getByLabel('New email (required)', { exact: true });
      await newEmail.waitFor();
      if (!(await newEmail.evaluate((element) => element === document.activeElement)))
        await tabTo(newEmail);
      await page.keyboard.insertText('corrected@example.test');
      await tabTo(page.getByRole('button', { name: 'Review email correction', exact: true }));
      await record('Enter');
      const correction = page.getByRole('dialog', { name: 'Confirm email correction' });
      await correction.waitFor();
      assert(
        await correction
          .getByRole('button', { name: 'Cancel', exact: true })
          .evaluate((element) => element === document.activeElement)
      );
      await tabTo(correction.getByRole('button', { name: 'Confirm correction', exact: true }));
      await record('Enter');
      await page.getByText('corrected@example.test', { exact: true }).first().waitFor();
      current.result =
        'passed: explicit retry, correction, Cancel-first confirmation and authoritative result';

      begin('Users pagination and table-card semantics');
      await open('administration-users--pagination-ready');
      await tabTo(page.getByRole('button', { name: 'Next page' }));
      await record('Enter');
      await page.getByText('last@example.test', { exact: true }).waitFor();
      assert(
        await page
          .getByRole('heading', { name: 'Users', exact: true })
          .evaluate((element) => element === document.activeElement)
      );
      assert(await page.getByRole('button', { name: 'Next page' }).isDisabled());
      await tabTo(page.getByRole('button', { name: 'First page' }));
      await record('Enter');
      await page.getByText('person0@example.test', { exact: true }).waitFor();
      current.result = 'passed: pagination, disabled terminal page and heading focus';

      begin('application navigation, account menu and appearance');
      await open('application-frame--wide-light');
      const menu = page.getByRole('button', { name: 'User Menu' });
      await tabTo(menu);
      await record('ArrowDown');
      await record('Escape');
      assert(await menu.evaluate((element) => element === document.activeElement));
      await record('Enter');
      await tabTo(page.getByRole('radio', { name: 'System', exact: true }));
      await record('ArrowRight');
      assert(await page.getByRole('radio', { name: 'Light', exact: true }).isChecked());
      await record('ArrowRight');
      assert(await page.getByRole('radio', { name: 'Dark', exact: true }).isChecked());
      await record('Escape');
      if (width === 320) {
        await tabTo(page.getByRole('button', { name: 'Open navigation' }));
        await record('Enter');
        await page.getByRole('dialog', { name: 'Application navigation' }).waitFor();
        await record('Shift+Tab');
        await record('Tab');
        await record('Escape');
        assert(
          await page
            .getByRole('button', { name: 'Open navigation' })
            .evaluate((element) => element === document.activeElement)
        );
      }
      await tabTo(menu);
      await record('Enter');
      await tabTo(page.getByRole('banner').getByRole('link', { name: 'Account & Security' }));
      await record('Enter');
      await page.getByRole('heading', { name: 'Account & Security', exact: true }).waitFor();
      assert(
        await page.getByRole('main').evaluate((element) => element === document.activeElement)
      );
      await tabTo(page.getByRole('button', { name: 'User Menu' }));
      await record('Enter');
      await tabTo(page.getByRole('button', { name: 'Sign Out', exact: true }));
      await record('Enter');
      await page.getByRole('button', { name: 'User Menu' }).waitFor({ state: 'hidden' });
      current.result =
        'passed: menu arrows/Escape, appearance, drawer return, route focus and logout retirement';
    } finally {
      await context.close();
    }
  }
}
