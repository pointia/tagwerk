import { describe, expect, it, vi } from 'vitest'
import { EscapeStack } from '../app/utils/escape'

describe('EscapeStack', () => {
  it('closes only the popup on top', () => {
    const stack = new EscapeStack()
    const dialog = vi.fn()
    const pin = vi.fn()

    stack.push(dialog)
    stack.push(pin)

    expect(stack.escape()).toBe(true)
    expect(pin).toHaveBeenCalledOnce()
    expect(dialog).not.toHaveBeenCalled()
  })

  it('reaches the one below once the top is gone', () => {
    const stack = new EscapeStack()
    const dialog = vi.fn()
    const pin = vi.fn()

    stack.push(dialog)
    stack.push(pin)
    stack.remove(pin)
    stack.escape()

    expect(dialog).toHaveBeenCalledOnce()
  })

  it('does nothing without an open popup', () => {
    expect(new EscapeStack().escape()).toBe(false)
  })

  it('removes a closed popup even when it is not on top', () => {
    const stack = new EscapeStack()
    const first = vi.fn()
    const second = vi.fn()

    stack.push(first)
    stack.push(second)
    stack.remove(first)
    stack.remove(second)

    expect(stack.escape()).toBe(false)
    expect(first).not.toHaveBeenCalled()
  })

  // Escape only asks; the popup leaves the stack when it really closes.
  it('keeps the popup until it closes itself', () => {
    const stack = new EscapeStack()
    const first = vi.fn()
    const second = vi.fn()

    stack.push(first)
    stack.push(second)
    stack.remove(first)
    stack.escape()

    expect(first).not.toHaveBeenCalled()
    expect(second).toHaveBeenCalledOnce()
  })
})
