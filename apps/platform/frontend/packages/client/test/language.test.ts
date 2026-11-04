import { describe, expect, it } from 'vitest'
import { isLanguage, keptTexts, startLanguage, type StoredLanguage } from '../src/rules/language'

const french = { code: 'fr', intl: 'fr-FR', messages: '/api/language/fr/3f9a1c2b4d5e.json' }
const keptFrench: StoredLanguage = { ...french, texts: { tasks: { nothing: 'Rien à faire.' } } }

describe('startLanguage', () => {
  it('takes the server answer over anything kept', () => {
    expect(startLanguage(french, { code: 'en', intl: 'en-US', messages: null, texts: null }, ['de-AT'])).toEqual(french)
  })

  it('starts offline in the language kept from the last answer', () => {
    expect(startLanguage(null, keptFrench, ['de-AT'])).toEqual(french)
  })

  it('never starts in a pack whose texts are gone', () => {
    expect(startLanguage(null, { ...french, texts: null }, ['fr-FR', 'en-GB'])).toEqual({ code: 'en', intl: 'en-US', messages: null })
  })

  it('falls back to a bundled browser language, then German', () => {
    expect(startLanguage(null, null, ['en-GB'])).toEqual({ code: 'en', intl: 'en-US', messages: null })
    expect(startLanguage(null, null, ['ja', 'fr'])).toEqual({ code: 'de', intl: 'de-AT', messages: null })
  })
})

describe('isLanguage', () => {
  it('accepts what the server sends and nothing else', () => {
    expect(isLanguage(french)).toBe(true)
    expect(isLanguage({ code: 'de', intl: 'de-AT', messages: null })).toBe(true)
    expect(isLanguage({ code: 'fr', intl: 'fr-FR', messages: 'https://elsewhere.example/fr.json' })).toBe(false)
    expect(isLanguage({ code: 'french', intl: 'fr-FR', messages: null })).toBe(false)
    expect(isLanguage('fr')).toBe(false)
  })
})

describe('keptTexts', () => {
  it('reuses kept texts only for the same version', () => {
    expect(keptTexts(keptFrench, french.messages)).toEqual(keptFrench.texts)
    expect(keptTexts(keptFrench, '/api/language/fr/000000000000.json')).toBeNull()
  })
})
