import { describe, expect, it } from 'vitest'
import { codeFromUrl } from '../app/utils/pairing'

describe('codeFromUrl', () => {
  it('reads the code from the URL', () => {
    expect(codeFromUrl('?pair=K7M3QX92')).toBe('K7M3QX92')
  })

  it('uppercases because scanners lowercase the URL', () => {
    expect(codeFromUrl('?pair=k7m3qx92')).toBe('K7M3QX92')
  })

  it('ignores other parameters', () => {
    expect(codeFromUrl('?modus=kalender')).toBeNull()
  })

  it('treats an empty code as none', () => {
    expect(codeFromUrl('?pair=')).toBeNull()
    expect(codeFromUrl('?pair=%20%20')).toBeNull()
  })
})
