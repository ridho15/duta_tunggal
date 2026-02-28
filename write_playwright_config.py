#!/usr/bin/env python3
"""Helper script to write playwright.config.js with auth setup."""
import os

target = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'playwright.config.js')

config = (
    "import { defineConfig, devices } from '@playwright/test';\n"
    "\n"
    "export default defineConfig({\n"
    "  testDir: './tests/playwright',\n"
    "  fullyParallel: true,\n"
    "  forbidOnly: !!process.env.CI,\n"
    "  retries: process.env.CI ? 2 : 0,\n"
    "  workers: process.env.CI ? 1 : undefined,\n"
    "  reporter: 'html',\n"
    "  use: {\n"
    "    baseURL: 'http://localhost:8009',\n"
    "    trace: 'on-first-retry',\n"
    "  },\n"
    "  projects: [\n"
    "    // Auth setup - logs in once and saves session for all tests\n"
    "    { name: 'setup', testMatch: /.*auth\\.setup\\.js/ },\n"
    "    {\n"
    "      name: 'chromium',\n"
    "      use: {\n"
    "        ...devices['Desktop Chrome'],\n"
    "        // Reuse saved auth state (avoids per-test login overhead)\n"
    "        storageState: 'playwright/.auth/user.json',\n"
    "        env: { ...process.env, APP_ENV: 'testing' },\n"
    "      },\n"
    "      dependencies: ['setup'],\n"
    "    },\n"
    "  ],\n"
    "});\n"
)

with open(target, 'w') as f:
    f.write(config)

print(f"Written {len(config)} bytes to {target}")
