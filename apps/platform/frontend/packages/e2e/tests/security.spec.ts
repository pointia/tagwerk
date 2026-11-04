import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { createAccount, pair } from './helpers'

/*
 * The Content Security Policy must not break what it protects. Browsers log
 * a violation to the console in both modes, reporting and enforcing — so a
 * run in the development's default (report-only) still fails on a script,
 * image or connection the policy would block.
 */

function violations(page: Page): string[] {
  const found: string[] = []
  page.on('console', (message) => {
    if (/Content Security Policy/i.test(message.text())) {
      found.push(message.text())
    }
  })

  return found
}

test('the app carries a strict policy and breaks nothing under it', async ({ page }) => {
  const found = violations(page)

  const response = await page.goto('/')
  const policy = response?.headers()['content-security-policy'] ?? response?.headers()['content-security-policy-report-only'] ?? ''
  expect(policy).toContain('script-src \'self\';')

  // Pairs, opens the home page and the calendar with its events.
  await pair(page)

  expect(found).toEqual([])
})

test('the admin works under its policy', async ({ page }) => {
  const found = violations(page)

  const response = await page.goto('/admin/login')
  expect(response?.headers()['content-security-policy'] ?? response?.headers()['content-security-policy-report-only']).toContain('object-src \'none\'')

  const account = createAccount()
  await page.locator('input[type=email]').fill(account.email)
  await page.locator('input[type=password]').fill(account.password)
  await page.locator('.fi-simple-main button[type=submit]').click()
  await expect(page.locator('.fi-header-heading')).toBeVisible()

  // A form with Alpine, selects and the icon picker's catalog.
  await page.goto('/admin/tasks/create')
  await expect(page.locator('.fi-header-heading')).toBeVisible()

  expect(found).toEqual([])
})
