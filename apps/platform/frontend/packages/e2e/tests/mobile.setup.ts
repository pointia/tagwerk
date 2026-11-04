import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname } from 'node:path'
import { test as setup } from '@playwright/test'
import { login, openSignIn, PHONE_STATE, ACCOUNT_FILE, createAccount } from './helpers'

setup('meldet ein Wegwerfkonto am Handy an', async ({ page }) => {
  const account = createAccount()

  mkdirSync(dirname(ACCOUNT_FILE), { recursive: true })
  writeFileSync(ACCOUNT_FILE, JSON.stringify(account))

  await openSignIn(page)
  await login(page, account)

  await page.context().storageState({ path: PHONE_STATE })
})
