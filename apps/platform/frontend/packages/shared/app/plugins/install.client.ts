import type { InstallPromptEvent } from '../composables/useInstallOffer'

/*
 * Catches the browser's install event at start: it fires once, often before
 * the band's component exists. Held back (`preventDefault`), so the browser
 * shows no bar of its own and the band's button can prompt later.
 */
export default defineNuxtPlugin(() => {
  const { held, installed } = useInstallOffer()

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    held.value = event as InstallPromptEvent
  })

  window.addEventListener('appinstalled', () => {
    installed.value = true
    held.value = null
  })
})
