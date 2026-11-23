import { expect, within } from 'storybook/test';

export async function expectHarborTheme(canvasElement: HTMLElement, dark: boolean) {
  const heading = await within(canvasElement).findByRole('heading', { level: 1 });
  const style = getComputedStyle(heading);
  await expect({
    action: style.getPropertyValue('--ui-action').trim(),
    headingFont: style.fontFamily.split(',')[0]?.trim()
  }).toEqual({ action: dark ? '#a2e4d0' : '#126657', headingFont: 'Georgia' });
}
