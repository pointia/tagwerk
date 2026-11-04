/**
 * Which language the app starts in — pure rules, so Vitest runs them
 * without Nuxt. `useLanguage` and `plugins/language.client.ts` apply them.
 */

/** What the server says: `/api/language`, the agenda, `/api/auth/me`. */
export interface Language {
  code: string
  /** BCP 47 for `Intl` */
  intl: string
  /** Address of a pack's texts; null for the bundled German and English. */
  messages: string | null
}

/** The last answer kept on the device, with a pack's texts. */
export interface StoredLanguage extends Language {
  texts: Record<string, unknown> | null
}

export type BundledCode = 'de' | 'en'

export const BUNDLED: Record<BundledCode, string> = { de: 'de-AT', en: 'en-US' }

export function isBundled(code: string): code is BundledCode {
  return code === 'de' || code === 'en'
}

export function isLanguage(value: unknown): value is Language {
  if (typeof value !== 'object' || value === null) {
    return false
  }

  const { code, intl, messages } = value as Record<string, unknown>

  return typeof code === 'string' && /^[a-z]{2}$/.test(code)
    && typeof intl === 'string' && intl !== ''
    && (messages === null || (typeof messages === 'string' && messages.startsWith('/api/language/')))
}

/** Something the device can show without asking anyone: bundled, or a pack whose texts it kept. */
export function isUsable(stored: StoredLanguage | null): stored is StoredLanguage {
  return stored !== null && (stored.messages === null ? isBundled(stored.code) : stored.texts !== null)
}

/**
 * The server's answer first; without one (no network) the last answer the
 * device kept; without that the first bundled browser language, else German.
 * Never a pack whose texts the device cannot get — that would be keys.
 */
export function startLanguage(
  answer: Language | null,
  stored: StoredLanguage | null,
  browser: readonly string[],
): Language {
  if (answer !== null) {
    return answer
  }

  if (isUsable(stored)) {
    return { code: stored.code, intl: stored.intl, messages: stored.messages }
  }

  for (const tag of browser) {
    const primary = tag.toLowerCase().split('-')[0] ?? ''

    if (isBundled(primary)) {
      return bundled(primary)
    }
  }

  return bundled('de')
}

/** A bundled language by its code; anything else is German. */
export function bundled(code: string): Language & { code: BundledCode } {
  const known: BundledCode = isBundled(code) ? code : 'de'

  return { code: known, intl: BUNDLED[known], messages: null }
}

/** The texts kept for this address, if they are the same version. */
export function keptTexts(stored: StoredLanguage | null, messages: string): Record<string, unknown> | null {
  return stored !== null && stored.messages === messages ? stored.texts : null
}
