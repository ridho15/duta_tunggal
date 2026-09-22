import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for the REMOTE staging environment.
 * Runs against: https://dutatunggal.gpt-biomekanika.id
 * Auth state is saved once by auth.setup.js and reused by all tests.
 */
export default defineConfig({
  testDir: './tests/playwright',
  fullyParallel: false,
  retries: 1,
  workers: 1,
  timeout: 60_000,
  reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
  use: {
    baseURL: 'https://dutatunggal.gpt-biomekanika.id',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
    locale: 'id-ID',
  },
  projects: [
    { name: 'setup', testMatch: /.*auth\.setup\.js/ },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
    },
  ],
});
