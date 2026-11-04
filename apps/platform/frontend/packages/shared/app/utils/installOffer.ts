/**
 * When the "install as app" band shows — pure, so Vitest checks it without
 * a browser. The browser facts are passed in by `useInstallOffer`.
 */

/** "Later" keeps the band away this long; after that it asks again. */
export const SNOOZE_DAYS = 7

const DAY_MS = 24 * 60 * 60 * 1000

/** Whether a "later" stored at `snoozedAt` (ms, or nothing) has run out. */
export function snoozeOver(snoozedAt: number | null, now: number): boolean {
  return snoozedAt === null || !Number.isFinite(snoozedAt) || now - snoozedAt >= SNOOZE_DAYS * DAY_MS
}

/**
 * iPhone and iPad: no install prompt, only "Share → Add to Home Screen".
 * An iPad reports itself as a Mac; the touch points give it away.
 */
export function isIos(userAgent: string, touchPoints: number): boolean {
  return /iPhone|iPad|iPod/.test(userAgent) || (/Macintosh/.test(userAgent) && touchPoints > 1)
}

export type InstallWay = 'prompt' | 'ios' | null

/**
 * How this browser installs, or null when the band has nothing to offer:
 * already installed (or a kiosk browser), or a browser without either way.
 */
export function installWay(facts: { installed: boolean, prompt: boolean, ios: boolean }): InstallWay {
  if (facts.installed) {
    return null
  }

  if (facts.prompt) {
    return 'prompt'
  }

  return facts.ios ? 'ios' : null
}
