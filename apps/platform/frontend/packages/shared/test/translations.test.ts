import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { describe, expect, it } from 'vitest'
import de from '../i18n/locales/de.json'
import en from '../i18n/locales/en.json'

/*
 * Every text in wall and phone exists in both languages, and none is left
 * German in the code.
 *
 * A missing key would not fail anywhere else: vue-i18n shows the key itself,
 * and on a tablet in the hallway nobody reports that.
 */

type Messages = { [key: string]: string | Messages }

const packages = join(__dirname, '../..')

function flatten(messages: Messages, prefix = ''): Record<string, string> {
  return Object.entries(messages).reduce<Record<string, string>>((flat, [key, value]) => (
    typeof value === 'string'
      ? { ...flat, [prefix + key]: value }
      : { ...flat, ...flatten(value, `${prefix}${key}.`) }
  ), {})
}

function sources(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)

    return statSync(path).isDirectory() ? sources(path) : /\.(vue|ts)$/.test(name) ? [path] : []
  })
}

const german = flatten(de as Messages)
const english = flatten(en as Messages)
const files = ['shared/app', 'app/app'].flatMap(dir => sources(join(packages, dir)))

describe('translations', () => {
  it('has the same keys in both languages', () => {
    expect(Object.keys(english).sort()).toEqual(Object.keys(german).sort())
  })

  it('writes plurals and placeholders alike in both languages', () => {
    for (const [key, text] of Object.entries(german)) {
      const other = english[key] ?? ''

      expect(other.split('|').length, key).toBe(text.split('|').length)
      expect([...other.matchAll(/\{(\w+)\}/g)].map(match => match[1]).sort(), key)
        .toEqual([...text.matchAll(/\{(\w+)\}/g)].map(match => match[1]).sort())
    }
  })

  it('uses no characters vue-i18n reads as syntax', () => {
    // `@` starts a linked message, a stray brace a placeholder.
    const broken = Object.entries({ ...german, ...english })
      .filter(([, text]) => /[@$]/.test(text) || text.replace(/\{\w+\}/g, '').match(/[{}]/))

    expect(broken).toEqual([])
  })

  it('knows every key the code asks for', () => {
    const missing = files.flatMap((file) => {
      const source = readFileSync(file, 'utf8')

      return [...source.matchAll(/\$?\bt\(\s*'([\w.]+)'/g)]
        .map(match => match[1]!)
        .filter(key => !(key in german))
        .map(key => `${relative(packages, file)}: ${key}`)
    })

    expect(missing).toEqual([])
  })

  it('leaves no German in the code', () => {
    const found = files.flatMap((file) => {
      const lines = readFileSync(file, 'utf8').split('\n')

      return lines
        .map((line, index) => ({ line, index }))
        .filter(({ line }) => !/^\s*(\/\/|\*|\/\*|<!--)/.test(line))
        .filter(({ line }) => /[äöüÄÖÜß„]/.test(line.replace(/\/\/.*$|<!--.*?-->/g, '')))
        .map(({ line, index }) => `${relative(packages, file)}:${index + 1}: ${line.trim()}`)
    })

    expect(found).toEqual([])
  })
})
