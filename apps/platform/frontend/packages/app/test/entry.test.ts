import { describe, expect, it } from 'vitest'
import { areaFromUrl, entryFromUrl, entryWithoutAnswer, householdFromUrl } from '../app/utils/entry'

describe('entryFromUrl', () => {
  it('takes a scanned pairing code for a tablet', () => {
    expect(entryFromUrl('?code=ABCD1234')).toBe('device')
  })

  it('takes a link from a mail for a person', () => {
    expect(entryFromUrl('?login=abc&email=a%40b.test')).toBe('user')
    expect(entryFromUrl('?reset=abc&email=a%40b.test')).toBe('user')
    expect(entryFromUrl('?invite=abc&email=a%40b.test')).toBe('user')
  })

  it('takes the way back from a sign-in provider for a person', () => {
    expect(entryFromUrl('?social=unknown')).toBe('user')
    expect(entryFromUrl('?two-factor=abc')).toBe('user')
  })

  it('leaves a plain address to the server', () => {
    expect(entryFromUrl('')).toBeNull()
  })
})

describe('entryWithoutAnswer', () => {
  it('starts with the pairing screen when nobody is signed in', () => {
    expect(entryWithoutAnswer(401, 'user')).toBe('device')
  })

  it('keeps the last answer without a network', () => {
    expect(entryWithoutAnswer(undefined, 'user')).toBe('user')
    expect(entryWithoutAnswer(undefined, 'device')).toBe('device')
  })

  it('falls back to the wall when nothing is remembered', () => {
    expect(entryWithoutAnswer(undefined, null)).toBe('device')
    expect(entryWithoutAnswer(503, 'nonsense')).toBe('device')
  })
})

describe('householdFromUrl', () => {
  const households = [{ id: 3, current: true }, { id: 7, current: false }]

  it('switches to another own household', () => {
    expect(householdFromUrl('?household=7', households)).toBe(7)
  })

  it('stays where it is otherwise', () => {
    expect(householdFromUrl('?household=3', households)).toBeNull()
    expect(householdFromUrl('?household=99', households)).toBeNull()
    expect(householdFromUrl('', households)).toBeNull()
  })
})

describe('areaFromUrl', () => {
  // A notification names the area that handles it, a plugin's with its vendor.
  it('reads the area a link names', () => {
    expect(areaFromUrl('?area=points&household=3')).toBe('points')
    expect(areaFromUrl('?area=acme/board')).toBe('acme/board')
  })

  it('ignores a missing or malformed area', () => {
    expect(areaFromUrl('?household=3')).toBeNull()
    expect(areaFromUrl('?area=')).toBeNull()
    expect(areaFromUrl('?area=../admin')).toBeNull()
    expect(areaFromUrl('?area=Points')).toBeNull()
  })
})
