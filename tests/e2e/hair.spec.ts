import { test, expect } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL || 'http://localhost/fakhra/hair-graft-calculator';

test('happy path: 0→6 و ذخیرهٔ موبایل', async ({ page }) => {
  test.setTimeout(120_000);

  await page.goto(BASE); // ⬅️ به‌جای "/"
  await page.waitForSelector('#step-0', { state: 'visible' });  await page.waitForSelector('#step-0', { state: 'visible' });
  await page.locator('#agree-btn').click();

  // Step 1
  await page.waitForSelector('#step-1.active', { state: 'visible' });

  // جنسیت
  await page.locator('#form-step-1 .gender-option input[value="male"]').check({ force: true });

  // سن (روی لیبل کلیک می‌کنیم که رادیو hidden هست)
  await page.locator('#form-step-1 .age-option', { hasText: '36-43' }).click();

  // موبایل
  const mobile = '0912' + Math.floor(1000000 + Math.random()*8999999);
  await page.locator('#form-step-1 input[name="mobile"]').fill(mobile);

  // قطعیت (هرچی)
  await page.locator('#form-step-1 select[name="confidence"]').selectOption({ index: 1 });

  await page.locator('#form-step-1 button[type="submit"]').click();

  // Step 2
  await page.waitForSelector('#step-2.active', { state: 'visible' });
  await page.locator('#step-2 .pattern-option').first().click();
  await page.locator('#form-step-2 button[type="submit"]').click();

  // Step 3
  await page.waitForSelector('#step-3.active', { state: 'visible' });
  await page.locator('#form-step-3 button[type="submit"]').click();

  // Step 4
  await page.waitForSelector('#step-4.active', { state: 'visible' });
  await page.locator('#has-medical-group .toggle-option', { hasText: 'خیر' }).click();
  await page.locator('#has-meds-group .toggle-option', { hasText: 'خیر' }).click();
  await page.locator('#form-step-4 button[type="submit"]').click();

  // Step 5
  await page.waitForSelector('#step-5.active', { state: 'visible' });
  await page.locator('input[name="first_name"]').fill('E2E');
  await page.locator('input[name="last_name"]').fill('Tester');
  await page.locator('input[name="state"]').fill('تهران');
  await page.locator('input[name="city"]').fill('تهران');
  await page.locator('.toggle-option', { hasText: 'تماس' }).click();
  await page.locator('#form-step-5 button[type="submit"]').click();

  // Step 6
  await page.waitForSelector('#step-6', { state: 'visible' });
  await expect(page.locator('#ai-result-box .method-text')).toBeVisible();

  // موبایل باید در خلاصه باشد
  await expect(page.locator('#user-summary-list')).toContainText(mobile);
});
