// Direct catalog evidence, not a visual-regression suite or a product acceptance verdict.
/* global document, window, getComputedStyle -- Playwright browser evaluation callbacks */
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { extname, resolve } from 'node:path';
import process from 'node:process';
import { URL } from 'node:url';
import { chromium } from 'playwright';

const require = createRequire(import.meta.url);
const root = resolve('../.runs/client/storybook');
const output = resolve('../.runs/client/catalog-evidence');
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
  stories: [],
  externalRequests: [],
  pageErrors: [],
  systemModes: []
};
try {
  const index = JSON.parse(await readFile(`${root}/index.json`, 'utf8'));
  for (const story of Object.values(index.entries).filter((entry) => entry.type === 'story')) {
    for (const width of [320, 1280]) {
      const context = await browser.newContext({
        viewport: { width, height: 900 },
        deviceScaleFactor: 1,
        locale: 'en-US',
        timezoneId: 'UTC',
        reducedMotion: 'reduce',
        colorScheme: 'light'
      });
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
      if (story.id.endsWith('--validation-to-success')) await page.getByRole('status').waitFor();
      if (story.id.endsWith('--keyboard-focus'))
        await page.waitForFunction(() => document.activeElement?.tagName === 'BUTTON');
      await page.evaluate(() => document.fonts.ready);
      if (!receipt.fonts) {
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
        const result = await window.axe.run('#storybook-root');
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
          ...document.querySelectorAll('#storybook-root button, #storybook-root input')
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
      receipt.stories.push({
        id: story.id,
        width,
        screenshot,
        layout,
        accessibility
      });
      await context.close();
    }
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
if (violations || overflow.length || receipt.externalRequests.length || receipt.pageErrors.length)
  process.exitCode = 1;
