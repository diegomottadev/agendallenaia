import { defineConfig } from '@playwright/test';

/**
 * E2E del flujo principal contra la app levantada con docker compose.
 *
 * Corre dentro del contenedor `e2e` (perfil `e2e` del compose), en la misma red
 * que `app`, así que la base es `http://app:8000`. Necesita el simulador de
 * WhatsApp encendido: Meta y Google los contestan los mocks locales.
 */
export default defineConfig({
  testDir: './tests',
  timeout: 90_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  outputDir: 'test-results',
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:8000',
    locale: 'es-AR',
    viewport: { width: 1280, height: 800 },
    video: 'on',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
