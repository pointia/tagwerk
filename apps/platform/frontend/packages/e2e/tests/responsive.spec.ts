import { expect, test } from '@playwright/test'
import { pairingCode } from './helpers'

/*
 * One interface: the width decides the layout, who signed in decides what is
 * offered. wall.spec.ts runs a tablet wide and mobile.spec.ts an account
 * narrow; these two cross over, so neither rule can lean on the other.
 *
 * The projects `narrow-device` and `wide-account` pick their half by name.
 */

test.describe('a tablet on a narrow screen', () => {
  test.skip(({ isMobile }) => !isMobile, 'narrow-device only')

  test('gets the phone layout but keeps what a tablet has', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('button', { name: /Wandtablet einrichten/ }).click()
    await page.getByPlaceholder('ABCD1234').fill(pairingCode())
    await page.getByRole('button', { name: 'Verbinden' }).click()

    // Stacked tiles and no arranging: twelve columns do not fit.
    await expect(page.getByTestId('dashboard-tile').first()).toBeVisible()
    await expect(page.getByTestId('dashboard-edit')).toHaveCount(0)
    await expect(page.getByTestId('dashboard-arrange-hint')).toBeVisible()

    // The menu slides in instead of the bar.
    await page.getByTestId('menu-open').click()
    await page.getByTestId('menu').getByRole('button', { name: 'Kalender', exact: true }).click()
    await page.getByTestId('calendar-filter').click()
    await expect(page.getByTestId('calendar').getByTestId('calendar-view')).toHaveCount(3)
    await page.getByTestId('calendar-filter-close').click()

    // Still a tablet: the resting mode and unpairing, no notifications.
    await page.getByTestId('menu-open').click()
    await page.getByTestId('settings').click()

    const sheet = page.getByTestId('settings-sheet')
    await expect(sheet.locator('[data-testid="settings-page"][data-value="rest"]')).toBeVisible()
    await expect(sheet.getByTestId('unpair')).toBeVisible()
    await expect(sheet.getByTestId('push')).toHaveCount(0)
  })

  /*
   * An absolutely placed child of a swipe column (a screen reader text) took
   * its place from outside the strip, so the strip did not clip it: the page
   * grew three columns wide, and a swipe beside the strip moved it all.
   */
  test('does not scroll sideways in the task columns', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('button', { name: /Wandtablet einrichten/ }).click()
    await page.getByPlaceholder('ABCD1234').fill(pairingCode())
    await page.getByRole('button', { name: 'Verbinden' }).click()

    await page.getByTestId('menu-open').click()
    await page.getByTestId('menu').getByRole('button', { name: 'Aufgaben', exact: true }).click()
    await expect(page.getByTestId('day-column').first()).toBeVisible()

    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => innerWidth))
  })
})

test.describe('an account on a wide screen', () => {
  test.skip(({ isMobile }) => isMobile, 'wide-account only')

  test('gets the wall layout but keeps what an account has', async ({ page }) => {
    await page.goto('/')

    // The bar instead of the menu, the grid with its pencil.
    await expect(page.getByRole('navigation', { name: 'Bereiche' })).toBeVisible()
    await expect(page.getByTestId('menu-open')).toHaveCount(0)
    await expect(page.getByTestId('dashboard-edit')).toBeVisible()

    // Every view, the list included; an account starts on one day.
    await page.getByRole('navigation', { name: 'Bereiche' }).getByRole('button', { name: 'Kalender', exact: true }).click()
    await expect(page.getByTestId('calendar-view')).toHaveCount(5)
    await expect(page.locator('[data-testid="calendar-view"][data-value="day"]')).toHaveAttribute('aria-pressed', 'true')

    // Still an account: notifications and signing out, no resting mode.
    await page.getByTestId('theme').click()

    const sheet = page.getByTestId('settings-sheet')
    await expect(sheet.getByTestId('push')).toBeVisible()
    await expect(sheet.getByTestId('sign-out')).toBeVisible()
    await expect(sheet.locator('[data-testid="settings-page"][data-value="rest"]')).toHaveCount(0)
  })
})
