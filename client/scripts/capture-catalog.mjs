// Direct catalog evidence, not a visual-regression suite or a product acceptance verdict.
/* global document, window, getComputedStyle -- Playwright browser evaluation callbacks */
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { extname, resolve } from 'node:path';
import process from 'node:process';
import { URL } from 'node:url';
import { chromium } from 'playwright';

import {
  observeKeyboard,
  observePalette,
  observeRendering
} from './accessibility-observations.mjs';

const require = createRequire(import.meta.url);
const root = resolve('../.runs/build/reports/client/storybook');
const output = resolve('../.runs/build/reports/client/catalog-evidence');
await rm(output, { recursive: true, force: true });
await mkdir(output, { recursive: true });
const types = {
  '.html': 'text/html',
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.json': 'application/json',
  '.woff2': 'font/woff2',
  '.svg': 'image/svg+xml',
  '.png': 'image/png'
};
const server = createServer(async (request, response) => {
  const path = resolve(root, `.${new URL(request.url, 'http://localhost').pathname}`);
  if (!path.startsWith(`${root}/`)) {
    response.writeHead(404).end();
    return;
  }
  try {
    const body = await readFile(path);
    response
      .writeHead(200, {
        'Content-Type': types[extname(path)] ?? 'application/octet-stream'
      })
      .end(body);
  } catch {
    response.writeHead(404).end();
  }
});
await new Promise((done) => server.listen(0, '127.0.0.1', done));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch();
const receipt = {
  browser: browser.version(),
  platform: process.platform,
  architecture: process.arch,
  deviceScaleFactor: 1,
  locale: 'en-US',
  timezone: 'UTC',
  motion: 'reduce (captures)',
  concurrency: 2,
  expectedCaptures: 0,
  stories: [],
  externalRequests: [],
  pageErrors: [],
  systemModes: [],
  keyboardJourneys: []
};
// Closing Playwright's owned browser interrupts in-flight operations and reaps its children.
// Keep the normal finally path so an interrupted run retains evidence, never a partial pass.
const interrupt = (signal) => {
  receipt.interrupted = signal;
  process.exitCode = signal === 'SIGINT' ? 130 : 143;
  void browser.close().catch(() => {});
};
const onInterrupt = () => interrupt('SIGINT');
const onTerminate = () => interrupt('SIGTERM');
process.once('SIGINT', onInterrupt);
process.once('SIGTERM', onTerminate);

try {
  const index = JSON.parse(await readFile(`${root}/index.json`, 'utf8'));
  const stories = Object.values(index.entries).filter((entry) => entry.type === 'story');
  const widths = (story) =>
    /ready-light|long-email|long-unicode|confirmation/.test(story.id)
      ? [320, 640, 1280]
      : [320, 1280];
  receipt.expectedCaptures = stories.reduce((total, story) => total + widths(story).length, 0);
  receipt.reflowPolicy =
    '1280px reference; 640px and 320px effective CSS layouts model 200% and 400% reflow. Not an observation of native browser zoom.';
  receipt.axeScope =
    'Whole body, including portaled dialogs; WCAG 2 A/AA, 2.1 AA and 2.2 AA tags; no rule/element suppressions. Best-practice recommendations are outside this WCAG rule selection.';
  const capture = async (story, width) => {
    const context = await browser.newContext({
      viewport: { width, height: 900 },
      deviceScaleFactor: 1,
      locale: 'en-US',
      timezoneId: 'UTC',
      reducedMotion: 'reduce',
      colorScheme: 'light'
    });
    try {
      await context.route('**/*', (route) => {
        if (new URL(route.request().url()).origin !== origin) {
          receipt.externalRequests.push(route.request().url());
          return route.abort();
        }
        return route.continue();
      });
      const page = await context.newPage();
      page.on('pageerror', (error) =>
        receipt.pageErrors.push({ story: story.id, message: error.message })
      );
      // This capture owns the axe scan. Keep the addon's automatic scan off only
      // for this URL so it cannot race our scan; browser story tests retain it.
      await page.goto(
        `${origin}/iframe.html?id=${story.id}&viewMode=story&globals=a11y.manual:!true`
      );
      await page.locator('#storybook-root .catalog-preview').first().waitFor();
      // Wait for the installed Storybook render/play lifecycle, not just the decorator mount.
      await page.waitForFunction(() =>
        window.__STORYBOOK_PREVIEW__?.storyRenders?.some((render) => render.phase === 'finished')
      );
      if (
        /^application-frame--(wide-light|wide-dark|narrow-dark|long-email|account-menu)/.test(
          story.id
        )
      ) {
        await page.getByRole('heading', { name: 'Dashboard', exact: true }).waitFor();
      }
      await page.evaluate(
        () =>
          new Promise((done) =>
            window.requestAnimationFrame(() => window.requestAnimationFrame(done))
          )
      );
      if (story.id.endsWith('--validation-to-success')) await page.getByRole('status').waitFor();
      if (story.id.endsWith('--keyboard-focus'))
        await page.waitForFunction(() => document.activeElement?.tagName === 'BUTTON');
      await page.evaluate(() => document.fonts.ready);
      if (width === 320 && !receipt.fonts) {
        const session = await context.newCDPSession(page);
        await session.send('DOM.enable');
        await session.send('CSS.enable');
        const { root: document } = await session.send('DOM.getDocument');
        const { nodeId } = await session.send('DOM.querySelector', {
          nodeId: document.nodeId,
          selector: '#storybook-root h1'
        });
        receipt.fonts = (await session.send('CSS.getPlatformFontsForNode', { nodeId })).fonts;
        await session.detach();
      }
      await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
      const accessibility = await page.evaluate(async () => {
        // Production confirmations portal outside the story root. Scan the whole document.
        const result = await window.axe.run(document.body, {
          runOnly: {
            type: 'tag',
            values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']
          }
        });
        return {
          violations: result.violations,
          incomplete: result.incomplete,
          passes: result.passes.map(({ id }) => id)
        };
      });
      const layout = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        viewportWidth: window.innerWidth,
        font: getComputedStyle(document.querySelector('.catalog-preview')).fontFamily,
        controls: [
          ...document.querySelectorAll('button, input, select, textarea, a[href], [role="radio"]')
        ].map((element) => {
          const bounds = element.getBoundingClientRect();
          return {
            label: element.textContent || element.labels?.[0]?.textContent,
            width: bounds.width,
            height: bounds.height
          };
        }),
        focus:
          document.activeElement?.tagName === 'BUTTON'
            ? {
                outline: getComputedStyle(document.activeElement).outline,
                offset: getComputedStyle(document.activeElement).outlineOffset,
                visible: document.activeElement.matches(':focus-visible')
              }
            : null,
        motion: [...document.querySelectorAll('.catalog-spinner')].map(
          (element) => getComputedStyle(element).animationName
        )
      }));
      const screenshot = `${story.id}-${width}.png`;
      await page.screenshot({
        path: `${output}/${screenshot}`,
        fullPage: true,
        animations: 'disabled'
      });
      return {
        id: story.id,
        width,
        screenshot,
        layout,
        accessibility,
        rendering: await observeRendering(page)
      };
    } finally {
      await context.close();
    }
  };
  for (const story of stories) {
    if (receipt.interrupted) throw new Error(`Catalog interrupted: ${receipt.interrupted}`);
    // Bound overlap to one story's two widths; never share page/storage or queue the entire catalog.
    // Wait for both cleanups even on failure, preserving successful partial evidence in stable order.
    const results = [];
    // At most two contexts at once, including the selected intermediate reflow samples.
    const sizes = widths(story);
    for (let index = 0; index < sizes.length; index += 2) {
      results.push(
        ...(await Promise.allSettled(
          sizes.slice(index, index + 2).map((width) => capture(story, width))
        ))
      );
    }
    for (const result of results) {
      if (result.status === 'fulfilled') receipt.stories.push(result.value);
    }
    const failure = results.find((result) => result.status === 'rejected');
    if (failure) throw failure.reason;
    if (
      results.some(
        (result) =>
          result.value.accessibility.violations.length ||
          result.value.layout.scrollWidth > result.value.layout.viewportWidth
      ) ||
      receipt.externalRequests.length ||
      receipt.pageErrors.length
    ) {
      throw new Error(`Catalog checks failed for ${story.id}; see retained receipt`);
    }
  }
  await observeKeyboard(browser, origin, receipt.keyboardJourneys);
  const measurements = await browser.newPage();
  try {
    await measurements.goto(
      `${origin}/iframe.html?id=foundation-composition--wide-light&viewMode=story&globals=a11y.manual:!true`
    );
    await measurements.locator('.catalog-preview').first().waitFor();
    receipt.contrast = await observePalette(measurements);
    await measurements.emulateMedia({ forcedColors: 'active' });
    receipt.forcedColors = {
      emulated: true,
      rendering: await observeRendering(measurements),
      limitation:
        'Chromium media emulation, not a Windows High Contrast or assistive-technology observation'
    };
  } finally {
    await measurements.close();
  }

  // Observe the catalog-only system preview across live OS changes; never write preference storage.
  const page = await browser.newPage();
  await page.route('**/*', (route) => {
    if (new URL(route.request().url()).origin !== origin) {
      receipt.externalRequests.push(route.request().url());
      return route.abort();
    }
    return route.continue();
  });
  page.on('pageerror', (error) =>
    receipt.pageErrors.push({ story: 'system-keyboard', message: error.message })
  );
  await page.goto(`${origin}/iframe.html?id=foundation-composition--system-preview&viewMode=story`);
  const system = page.locator('.catalog-preview .catalog-preview');
  for (const colorScheme of ['dark', 'light']) {
    await page.emulateMedia({ colorScheme, reducedMotion: 'no-preference' });
    await page.waitForFunction(
      (mode) =>
        document
          .querySelector('.catalog-preview .catalog-preview')
          ?.getAttribute('data-bs-theme') === mode,
      colorScheme
    );
    receipt.systemModes.push({
      requested: colorScheme,
      actual: await system.getAttribute('data-bs-theme'),
      animation: await page
        .locator('.catalog-spinner')
        .first()
        .evaluate((element) => getComputedStyle(element).animationName)
    });
  }
  await page.setViewportSize({ width: 320, height: 900 });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(`${origin}/iframe.html?id=foundation-composition--narrow-dark&viewMode=story`);
  await page.locator('#storybook-root .catalog-preview').waitFor();
  receipt.keyboardWalk = [];
  for (let step = 1; step <= 4; step++) {
    await page.keyboard.press('Tab');
    receipt.keyboardWalk.push(
      await page.evaluate(() => {
        const element = document.activeElement;
        const bounds = element.getBoundingClientRect();
        return {
          label: element.textContent || element.labels?.[0]?.textContent,
          tag: element.tagName,
          focusVisible: element.matches(':focus-visible'),
          outline: getComputedStyle(element).outline,
          top: bounds.top,
          bottom: bounds.bottom,
          viewportHeight: window.innerHeight
        };
      })
    );
    await page.screenshot({
      path: `${output}/keyboard-dark-${step}.png`,
      fullPage: true,
      animations: 'disabled'
    });
  }
  await page.close();

  // Smoke the built Storybook manager as well as its isolated production previews.
  const manager = await browser.newPage({
    viewport: { width: 1280, height: 900 }
  });
  await manager.route('**/*', (route) => {
    if (new URL(route.request().url()).origin !== origin) {
      receipt.externalRequests.push(route.request().url());
      return route.abort();
    }
    return route.continue();
  });
  manager.on('pageerror', (error) =>
    receipt.pageErrors.push({ story: 'manager', message: error.message })
  );
  await manager.goto(`${origin}/index.html?path=/story/foundation-composition--wide-dark`);
  await manager
    .frameLocator('#storybook-preview-iframe')
    .getByRole('heading', { name: 'Neutral component foundation' })
    .waitFor();
  await manager.screenshot({
    path: `${output}/manager.png`,
    fullPage: true,
    animations: 'disabled'
  });
  receipt.manager = 'Rendered the static manager and selected production composition';
  await manager.close();
} finally {
  await browser.close();
  await new Promise((done) => server.close(done));
  await writeFile(`${output}/receipt.json`, `${JSON.stringify(receipt, null, 2)}\n`);
  process.removeListener('SIGINT', onInterrupt);
  process.removeListener('SIGTERM', onTerminate);
}
const violations = receipt.stories.reduce(
  (total, story) => total + story.accessibility.violations.length,
  0
);
const overflow = receipt.stories.filter(
  (story) => story.layout.scrollWidth > story.layout.viewportWidth
);
console.log(
  JSON.stringify(
    {
      captures: receipt.stories.length,
      violations,
      overflow: overflow.map(({ id, width }) => ({ id, width })),
      externalRequests: receipt.externalRequests,
      pageErrors: receipt.pageErrors,
      output
    },
    null,
    2
  )
);
if (
  receipt.interrupted ||
  receipt.stories.length !== receipt.expectedCaptures ||
  violations ||
  overflow.length ||
  receipt.externalRequests.length ||
  receipt.pageErrors.length
)
  process.exitCode = process.exitCode || 1;
