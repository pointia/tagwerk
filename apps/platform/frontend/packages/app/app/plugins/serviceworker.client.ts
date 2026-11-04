export default defineNuxtPlugin(() => {
  if (!('serviceWorker' in navigator)) {
    return
  }

  navigator.serviceWorker.register('/sw.js').catch((error: unknown) => {
    // No reason not to show the wall because of it: without a service worker
    // only the cold start without a network is missing, not regular operation.
    console.warn('Service worker not registered:', error)
  })
})
