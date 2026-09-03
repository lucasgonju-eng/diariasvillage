import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  outputDir: './tmp/playwright-results',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['line']],
  use: {
    baseURL: 'http://127.0.0.1:4174',
    browserName: 'chromium',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  webServer: {
    command: 'php -S 127.0.0.1:4174 tests/e2e/router.php',
    url: 'http://127.0.0.1:4174/__e2e_health',
    reuseExistingServer: false,
    timeout: 15_000,
  },
});
