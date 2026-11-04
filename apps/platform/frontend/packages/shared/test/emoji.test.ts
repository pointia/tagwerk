import { describe, expect, it } from 'vitest'
import { emojiHex, emojiSrc } from '../app/utils/emoji'

describe('emojiHex', () => {
  it('names the file by its code points', () => {
    expect(emojiHex('🪥')).toBe('1faa5')
  })

  /*
   * Stored icons come with and without the variation selector (`🛏` and
   * `🛏️`); both are the same picture.
   */
  it('leaves out the variation selector', () => {
    expect(emojiHex('🛏️')).toBe('1f6cf')
    expect(emojiHex('🛏')).toBe('1f6cf')
  })

  it('joins sequences with a dash', () => {
    expect(emojiHex('🧑‍🍳')).toBe('1f9d1-200d-1f373')
  })

  it('has nothing for an empty icon', () => {
    expect(emojiHex('')).toBeNull()
    expect(emojiHex('  ')).toBeNull()
  })
})

describe('emojiSrc', () => {
  it('points at the coloured or the grey set', () => {
    expect(emojiSrc('🪥', true)).toBe('/emoji/flat/1faa5.svg')
    expect(emojiSrc('🪥', false)).toBe('/emoji/mono/1faa5.svg')
  })
})
