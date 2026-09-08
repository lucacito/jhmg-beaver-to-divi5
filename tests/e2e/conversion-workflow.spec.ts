import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { BASE, copyHelperScript, isDiviNoise, login, screenshotsDir, shellEscape, wp } from './helpers';

test.describe.serial('Full workflow on a real Beaver Builder layout', () => {
  let sourceId: string;

  test.beforeAll(() => {
    fs.mkdirSync(screenshotsDir, { recursive: true });
    copyHelperScript('import-bb-template.php');
    sourceId = wp(`TEMPLATE=layout-03-Home-lite wp eval-file /tmp/import-bb-template.php --allow-root`).trim();
    expect(sourceId).toMatch(/^\d+$/);
  });

  test('the Tools screen lists the page, checks it, converts it, and records the run', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE}/wp-admin/tools.php?page=bdc-converter`);
    await expect(page.locator('h1')).toContainText('Beaver Builder to Divi 5 Converter');

    // Pick the page and check it.
    await page.check(`input[name="bbdc_post_ids"][value="${sourceId}"], input[name="bbdc_post_ids[]"][value="${sourceId}"]`);
    await page.click('button:has-text("Check this page")');
    await page.waitForURL(/action=direct_report/);
    await expect(page.locator('.bdc-direct-report')).toContainText('modules converted');
    await expect(page.locator('.bdc-outline-node--section').first()).toBeVisible();
    await page.screenshot({ path: path.join(screenshotsDir, 'workflow-report.png'), fullPage: true });

    // Convert it.
    await page.click('button:has-text("Convert to Divi 5")');
    await page.waitForURL(/action=batch_result/);
    await expect(page.locator('.bdc-summary-stat--ok')).toContainText('1 converted');
    await page.screenshot({ path: path.join(screenshotsDir, 'workflow-results.png'), fullPage: true });

    const viewHref = await page.locator('a.button:has-text("View")').first().getAttribute('href');
    expect(viewHref).toBeTruthy();
    const match = /[?&]p=(\d+)|page_id=(\d+)|\/([^/]+)\/?$/.exec(viewHref!);
    expect(match).toBeTruthy();

    // The run is undoable from the landing page.
    await page.goto(`${BASE}/wp-admin/tools.php?page=bdc-converter`);
    await expect(page.locator('a.button:has-text("Undo")').first()).toBeVisible();

  });

  test('the converted page renders every section on the frontend without JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => { if (!isDiviNoise(err.message)) errors.push(err.message); });

    const rows = parseInt(wp(`wp eval ${shellEscape(`$d=get_post_meta(${sourceId},"_fl_builder_data",true);echo count(array_filter((array)$d,fn($n)=>($n->type??"")==="row"));`)} --allow-root`).trim(), 10);
    const newId = wp(`wp post list --post_type=page --post_status=any --meta_key=_bbdc_source_post_id --meta_value=${sourceId} --field=ID --allow-root`).trim().split('\n')[0];
    expect(newId).toMatch(/^\d+$/);

    // Direct conversion creates a draft; a fresh (logged-out) browser context cannot view a draft.
    wp(`wp post update ${newId} --post_status=publish --allow-root`);
    await page.goto(`${BASE}/?page_id=${newId}`);
    await page.waitForSelector('.et_pb_section', { timeout: 20000 });
    expect(await page.locator('.et_pb_section').count()).toBe(rows);
    expect(await page.locator('.et_pb_image img').count()).toBeGreaterThan(0);
    expect(await page.locator('.et_pb_button').count()).toBeGreaterThan(0);
    await page.screenshot({ path: path.join(screenshotsDir, 'home-frontend.png'), fullPage: true });
    expect(errors).toEqual([]);

    const report = JSON.parse(wp(`wp post meta get ${newId} _bbdc_conversion_report --allow-root`).trim());
    expect(report.converted.section).toBe(rows);
    expect(report.unsupported).toEqual([]);
    expect(report.skipped_settings).toEqual([]);
  });
});
