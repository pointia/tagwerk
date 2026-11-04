import { describe, expect, it } from 'vitest'
import { applyMarkdown } from '../app/utils/markdownEdit'

const placeholders = { text: 'Text', link: 'Link' }

describe('applyMarkdown', () => {
  it('wraps the selection and keeps it selected', () => {
    expect(applyMarkdown({ value: 'gut umrühren', start: 0, end: 3 }, 'bold', placeholders))
      .toEqual({ value: '**gut** umrühren', start: 2, end: 5 })
  })

  it('inserts a placeholder without a selection', () => {
    expect(applyMarkdown({ value: 'a ', start: 2, end: 2 }, 'italic', placeholders))
      .toEqual({ value: 'a *Text*', start: 3, end: 7 })
  })

  it('selects the address of a new link', () => {
    const edit = applyMarkdown({ value: 'Quelle', start: 0, end: 6 }, 'link', placeholders)

    expect(edit.value).toBe('[Quelle](https://)')
    expect(edit.value.slice(edit.start, edit.end)).toBe('https://')
  })

  it('numbers every touched line, and a second press removes it', () => {
    const numbered = applyMarkdown({ value: 'Kochen\nSalzen\nServieren', start: 2, end: 10 }, 'numbered', placeholders)

    expect(numbered.value).toBe('1. Kochen\n2. Salzen\nServieren')
    expect(applyMarkdown(numbered, 'numbered', placeholders).value).toBe('Kochen\nSalzen\nServieren')
  })

  it('toggles a heading, and a bullet does not touch numbering', () => {
    expect(applyMarkdown({ value: '# Teig', start: 0, end: 0 }, 'heading', placeholders).value).toBe('Teig')
    expect(applyMarkdown({ value: 'Teig', start: 0, end: 0 }, 'heading', placeholders).value).toBe('## Teig')
    expect(applyMarkdown({ value: '1. Teig', start: 0, end: 0 }, 'list', placeholders).value).toBe('- 1. Teig')
  })
})
