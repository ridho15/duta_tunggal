import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:8009',
    trace: 'on-first-retry',
  },
  projects: [
    // Auth setup - logs in once and saves session for all tests
    { name: 'setup', testMatch: /.*auth\.setup\.js/ },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Reuse saved auth state (avoids per-test login overhead)
        storageState: 'playwright/.auth/user.json',
        env: { ...process.env, APP_ENV: 'testing' },
      },
      dependencies: ['setup'],
    },
  ],
});
