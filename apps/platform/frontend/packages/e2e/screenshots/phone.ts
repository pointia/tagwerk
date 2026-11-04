import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { createAccount, login, openSignIn } from '../tests/helpers'
import { borrowLocation, menu, morning, OUT, returnLocation, settle, theme } from './shots'

test.beforeAll(borrowLocation)
test.afterAll(returnLocation)

test('phone', async ({ page }) => {
  await morning(page)
  await openSignIn(page)
  await login(page, createAccount())

  for (const mode of ['Hell', 'Dunkel'] as const) {
    const suffix = mode === 'Hell' ? 'light' : 'dark'
    await theme(page, mode, async (p: Page) => {
      await menu(p)
      await p.getByTestId('settings').click()
    })

    for (const [area, name] of [['Aufgaben', 'tasks'], ['Kalender', 'calendar']] as const) {
      await (await menu(page)).getByTestId('tab').filter({ hasText: area }).click()
      await settle(page)
      await page.screenshot({ path: `${OUT}phone-${name}-${suffix}.png` })
    }
  }
})
