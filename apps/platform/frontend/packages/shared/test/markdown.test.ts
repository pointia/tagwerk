import { describe, expect, it } from 'vitest'
import { inlineText, parseInline, parseMarkdown, safeHref } from '../app/utils/markdown'

describe('parseInline', () => {
  it('reads strong, emphasis and links', () => {
    expect(parseInline('**heiß** und *schnell*, [Quelle](https://example.org/a)')).toEqual([
      { type: 'strong', children: [{ type: 'text', text: 'heiß' }] },
      { type: 'text', text: ' und ' },
      { type: 'em', children: [{ type: 'text', text: 'schnell' }] },
      { type: 'text', text: ', ' },
      { type: 'link', href: 'https://example.org/a', children: [{ type: 'text', text: 'Quelle' }] },
    ])
  })

  it('keeps a lone star and snake_case as text', () => {
    expect(inlineText(parseInline('2 * 3 = 6, file_name_here'))).toBe('2 * 3 = 6, file_name_here')
    expect(parseInline('2 * 3 = 6')).toEqual([{ type: 'text', text: '2 * 3 = 6' }])
  })

  it('turns dangerous and relative links into their label', () => {
    expect(parseInline('[klick](javascript:alert(1))')).toEqual([{ type: 'text', text: 'klick' }])
    expect(parseInline('[klick](JavaScript:alert(1))')).toEqual([{ type: 'text', text: 'klick' }])
    expect(parseInline('[da](/admin)')).toEqual([{ type: 'text', text: 'da' }])
    expect(parseInline('[alt](http://example.org)')).toEqual([{ type: 'text', text: 'alt' }])
    expect(parseInline('[Mail](mailto:a@example.org)')[0]).toMatchObject({ type: 'link', href: 'mailto:a@example.org' })
  })

  it('leaves HTML as text', () => {
    expect(parseInline('<img src=x onerror=alert(1)>')).toEqual([{ type: 'text', text: '<img src=x onerror=alert(1)>' }])
  })

  it('honours escapes', () => {
    expect(parseInline('\\*kein\\* Stern')).toEqual([{ type: 'text', text: '*kein* Stern' }])
  })
})

describe('safeHref', () => {
  it('allows https and mailto only', () => {
    expect(safeHref('https://www.kochwiki.org/wiki/Spaghetti')).toBe('https://www.kochwiki.org/wiki/Spaghetti')
    expect(safeHref('data:text/html,<script>')).toBeNull()
    expect(safeHref('vbscript:x')).toBeNull()
  })
})

describe('parseMarkdown', () => {
  it('reads headings and paragraphs', () => {
    expect(parseMarkdown('# Teig\n\nMehl und\nWasser.')).toEqual([
      { type: 'heading', level: 3, children: [{ type: 'text', text: 'Teig' }] },
      { type: 'paragraph', children: [{ type: 'text', text: 'Mehl und Wasser.' }] },
    ])
  })

  it('reads numbered lists with their start and nested bullets', () => {
    const blocks = parseMarkdown('3. Kochen\n   - kurz\n   - heiß\n4. Servieren')

    expect(blocks).toHaveLength(1)
    expect(blocks[0]).toMatchObject({ type: 'list', ordered: true, start: 3 })

    const list = blocks[0] as Extract<(typeof blocks)[number], { type: 'list' }>

    expect(list.items).toHaveLength(2)
    expect(list.items[0]).toEqual([
      { type: 'paragraph', children: [{ type: 'text', text: 'Kochen' }] },
      {
        type: 'list',
        ordered: false,
        start: 1,
        items: [
          [{ type: 'paragraph', children: [{ type: 'text', text: 'kurz' }] }],
          [{ type: 'paragraph', children: [{ type: 'text', text: 'heiß' }] }],
        ],
      },
    ])
  })

  it('keeps a continuation line in its item', () => {
    const blocks = parseMarkdown('1. Mehl sieben\nund salzen\n2. Rühren')
    const list = blocks[0] as Extract<(typeof blocks)[number], { type: 'list' }>

    expect(list.items[0]).toEqual([{ type: 'paragraph', children: [{ type: 'text', text: 'Mehl sieben und salzen' }] }])
  })

  it('starts a new list when the kind changes', () => {
    expect(parseMarkdown('- a\n1. b').map(block => block.type === 'list' && block.ordered)).toEqual([false, true])
  })

  it('handles Windows line ends and empty input', () => {
    expect(parseMarkdown('')).toEqual([])
    expect(parseMarkdown('a\r\nb')).toEqual([{ type: 'paragraph', children: [{ type: 'text', text: 'a b' }] }])
  })
})
