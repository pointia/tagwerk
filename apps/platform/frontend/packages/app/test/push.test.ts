import { describe, expect, it } from 'vitest'
import { keyAsBytes } from '../app/utils/push'

describe('keyAsBytes', () => {
  it('converts a key without padding', () => {
    // "hello" as Base64-URL — the length 7 is not a multiple of four.
    expect([...keyAsBytes('aGVsbG8')]).toEqual([104, 101, 108, 108, 111])
  })

  it('also accepts already padded input', () => {
    expect([...keyAsBytes('aGVsbG8=')]).toEqual([104, 101, 108, 108, 111])
  })

  /*
   * The actual reason for this test: a real VAPID key is random, and about
   * every second one contains `-` or `_`. Without the reverse substitution
   * atob() returns wrong bytes instead of an error — the bug would only show
   * on the first delivery attempt.
   */
  it('restores the Base64-URL special characters', () => {
    expect([...keyAsBytes('--8')]).toEqual([251, 239])
    expect([...keyAsBytes('__8')]).toEqual([255, 255])
  })

  it('stores the bytes in a real ArrayBuffer', () => {
    // Not cosmetic: applicationServerKey accepts no SharedArrayBuffer.
    expect(keyAsBytes('aGVsbG8').buffer).toBeInstanceOf(ArrayBuffer)
  })

  it('maps the length of a complete VAPID key', () => {
    // 65 bytes of uncompressed P-256 point, as the server delivers it.
    // 65 bytes are 21 full groups of three plus two bytes — so 87 characters.
    const key = 'BA'.padEnd(87, 'A')

    expect(keyAsBytes(key)).toHaveLength(65)
  })
})
