import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 60_000,
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost/fakhra/hair-graft-calculator',
    headless: true,
    viewport: { width: 1280, height: 800 },
  },
  reporter: [['list']],
});
