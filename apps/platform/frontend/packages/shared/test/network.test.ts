import { describe, expect, it } from 'vitest'
import { requiresPin, serverMessage } from '../app/utils/network'

describe('serverMessage', () => {
  it('takes the first field message of a validation', () => {
    const error = { statusCode: 422, data: { message: 'The given data was invalid.', errors: { redemption: ['Diese Anfrage ist bereits entschieden.'] } } }

    expect(serverMessage(error)).toBe('Diese Anfrage ist bereits entschieden.')
  })

  it('falls back to the message of a refusal', () => {
    expect(serverMessage({ statusCode: 403, data: { message: 'This action is unauthorized.' } })).toBe('This action is unauthorized.')
  })

  it('says nothing about a crash', () => {
    expect(serverMessage({ statusCode: 500, data: { message: 'Server Error' } })).toBeNull()
  })

  it('is null when nothing came along', () => {
    expect(serverMessage(new Error('network'))).toBeNull()
    expect(serverMessage(null)).toBeNull()
  })
})

describe('requiresPin', () => {
  it('only a 423 asks for the PIN', () => {
    expect(requiresPin({ response: { status: 423 } })).toBe(true)
    expect(requiresPin({ response: { status: 403 } })).toBe(false)
    expect(requiresPin({ response: { status: 422 } })).toBe(false)
  })
})
