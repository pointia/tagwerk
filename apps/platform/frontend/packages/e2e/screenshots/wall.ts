import { expect, test } from '@playwright/test'
import { pair } from '../tests/helpers'
import { borrowLocation, morning, OUT, returnLocation, settle, theme } from './shots'

test.beforeAll(borrowLocation)
test.afterAll(returnLocation)

for (const mode of ['Hell', 'Dunkel'] as const) {
  const suffix = mode === 'Hell' ? 'light' : 'dark'

  test(`wall, ${suffix}`, async ({ page }) => {
    await morning(page)
    await pair(page)
    await theme(page, mode)

    await page.getByRole('button', { name: 'Kalender', exact: true }).click()
    await expect(page.getByTestId('event').first()).toBeVisible()
    await settle(page)
    await page.screenshot({ path: `${OUT}wall-calendar-${suffix}.png` })

    await page.getByRole('button', { name: 'Aufgaben', exact: true }).click()
    await settle(page)
    await page.screenshot({ path: `${OUT}wall-tasks-${suffix}.png` })

    await page.getByRole('button', { name: 'Start', exact: true }).click()
    await expect(page.getByTestId('dashboard-grid')).toBeVisible()
    await settle(page)
    await page.screenshot({ path: `${OUT}wall-dashboard-${suffix}.png` })
  })
}
