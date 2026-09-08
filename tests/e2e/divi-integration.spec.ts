import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { BASE, copyHelperScript, createBeaverPage, convertToNewPage, isDiviNoise, login, screenshotsDir } from './helpers';

const fixtures = [
  { name: 'beaver/heading', title: 'Heading Fixture', selector: 'h1:has-text("Contact us")' },
  { name: 'beaver/rich-text', title: 'Text Fixture', selector: 'text=Second paragraph.' },
  { name: 'beaver/photo', title: 'Photo Fixture', selector: 'img[alt="Our team at work"]' },
  { name: 'beaver/button', title: 'Button Fixture', selector: 'a:has-text("Get in touch online")' },
  { name: 'beaver/two-columns', title: 'Two Columns Fixture', selector: 'h3:has-text("Right")' },
  { name: 'beaver/button-group', title: 'Button Group Fixture', selector: 'a:has-text("Two")' },
  { name: 'beaver/callout-icon-button', title: 'Callout Fixture', selector: 'a:has-text("Call now")' },
];

test.describe.serial('Divi 5 renders converted Beaver Builder modules', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screenshotsDir, { recursive: true });
    copyHelperScript('set-beaver-data.php');
    copyHelperScript('convert-to-new-page.php');
  });

  for (const fixture of fixtures) {
    test(`${fixture.name} is recognised by Divi and renders on the frontend`, async ({ page }) => {
      const errors: string[] = [];
      page.on('pageerror', (err) => { if (!isDiviNoise(err.message)) errors.push(err.message); });

      const sourceId = createBeaverPage(fixture.name, fixture.title);
      const pageId = convertToNewPage(sourceId);

      await login(page);
      await page.goto(`${BASE}/wp-admin/post.php?post=${pageId}&action=edit`);
      const diviButton = page.locator('button:has-text("Divi Builder"), a:has-text("Divi Builder"), [class*="divi-builder-button"]');
      await diviButton.first().waitFor({ state: 'attached', timeout: 20000 });

      await page.goto(`${BASE}/?page_id=${pageId}`);
      await page.waitForSelector('.et_pb_section', { timeout: 20000 });
      await page.waitForSelector(fixture.selector, { timeout: 20000 });
      expect(await page.locator(fixture.selector).first().isVisible()).toBe(true);

      const diviStylePresent = await page.evaluate(() =>
        Array.from(document.querySelectorAll('style[id], link[id]')).some((el) => el.id.startsWith('et-') || el.id.startsWith('divi-'))
      );
      expect(diviStylePresent, 'Divi styles enqueued').toBe(true);

      await page.screenshot({ path: path.join(screenshotsDir, `${path.basename(fixture.name)}-frontend.png`), fullPage: true });
      expect(errors, 'frontend JS errors').toEqual([]);
    });
  }
});
