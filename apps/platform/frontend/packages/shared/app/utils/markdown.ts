/*
 * A small Markdown subset as a tree, drawn by TwMarkdown with Vue nodes.
 *
 * Never HTML: recipe text comes from the family, from Koch-Wiki and from
 * pasted links, and `v-html` would run whatever it carries. What is not in
 * the subset stays visible as text — `<b>` included. Links are kept only for
 * `https:` and `mailto:`; anything else (`javascript:`, relative paths)
 * becomes its label.
 *
 * Subset: headings, paragraphs, `**strong**`, `*em*`, links, bulleted and
 * numbered lists, nested by indentation.
 */

export type MarkdownInline =
  | { type: 'text', text: string }
  | { type: 'strong', children: MarkdownInline[] }
  | { type: 'em', children: MarkdownInline[] }
  | { type: 'link', href: string, children: MarkdownInline[] }

export type MarkdownBlock =
  | { type: 'heading', level: number, children: MarkdownInline[] }
  | { type: 'paragraph', children: MarkdownInline[] }
  | { type: 'list', ordered: boolean, start: number, items: MarkdownBlock[][] }

const HEADING = /^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/
const ITEM = /^(\s*)([-*+]|\d{1,4}[.)])\s+(.*)$/

function indentOf(line: string): number {
  return line.length - line.trimStart().length
}

/** `https:` and `mailto:` only, as written; everything else is no link. */
export function safeHref(href: string): string | null {
  try {
    const url = new URL(href)

    return url.protocol === 'https:' || url.protocol === 'mailto:' ? href : null
  }
  catch {
    return null
  }
}

function closing(source: string, open: number, left: string, right: string): number {
  let depth = 0

  for (let index = open; index < source.length; index++) {
    if (source[index] === '\\') {
      index++
    }
    else if (source[index] === left) {
      depth++
    }
    else if (source[index] === right && --depth === 0) {
      return index
    }
  }

  return -1
}

export function parseInline(source: string): MarkdownInline[] {
  const nodes: MarkdownInline[] = []
  let text = ''
  let index = 0

  const flush = (): void => {
    if (text !== '') {
      nodes.push({ type: 'text', text })
      text = ''
    }
  }

  while (index < source.length) {
    const char = source[index]!

    if (char === '\\' && /[\\`*_[\]()#+\-.!<>]/.test(source[index + 1] ?? '')) {
      text += source[index + 1]
      index += 2
      continue
    }

    if (char === '[') {
      const close = closing(source, index, '[', ']')
      const end = close > 0 && source[close + 1] === '(' ? closing(source, close + 1, '(', ')') : -1

      if (end > 0) {
        const href = safeHref(source.slice(close + 2, end).trim().split(/\s+/)[0] ?? '')
        const label = parseInline(source.slice(index + 1, close))

        flush()
        nodes.push(...(href === null ? label : [{ type: 'link' as const, href, children: label }]))
        index = end + 1
        continue
      }
    }

    if (char === '*' || char === '_') {
      const marker = source[index + 1] === char ? char + char : char
      const start = index + marker.length
      const insideWord = char === '_' && /[\p{L}\p{N}]/u.test(source[index - 1] ?? '')

      if (!insideWord && start < source.length && !/\s/.test(source[start]!)) {
        let end = source.indexOf(marker, start + 1)

        while (end > 0 && /\s/.test(source[end - 1]!)) {
          end = source.indexOf(marker, end + 1)
        }

        if (end > 0) {
          flush()
          nodes.push({ type: marker.length === 2 ? 'strong' : 'em', children: parseInline(source.slice(start, end)) })
          index = end + marker.length
          continue
        }
      }
    }

    text += char === '\n' ? ' ' : char
    index++
  }

  flush()

  return nodes
}

function parseLines(lines: string[]): MarkdownBlock[] {
  const blocks: MarkdownBlock[] = []
  let index = 0

  while (index < lines.length) {
    const line = lines[index]!

    if (line.trim() === '') {
      index++
      continue
    }

    const heading = HEADING.exec(line)

    if (heading) {
      // `#` is the third level: the dialog's title is above it.
      blocks.push({ type: 'heading', level: Math.min(6, heading[1]!.length + 2), children: parseInline(heading[2]!) })
      index++
      continue
    }

    const first = ITEM.exec(line)

    if (first) {
      const indent = first[1]!.length
      const ordered = /\d/.test(first[2]!)
      const items: MarkdownBlock[][] = []

      while (index < lines.length) {
        const item = ITEM.exec(lines[index]!)

        if (!item || item[1]!.length !== indent || /\d/.test(item[2]!) !== ordered) {
          break
        }

        const content = [item[3]!]
        const contentIndent = item[1]!.length + item[2]!.length + 1
        index++

        while (index < lines.length) {
          const next = lines[index]!

          if (next.trim() === '') {
            const after = lines[index + 1]

            if (after !== undefined && after.trim() !== '' && indentOf(after) > indent) {
              content.push('')
              index++
              continue
            }

            break
          }

          const nested = ITEM.exec(next)

          if (indentOf(next) > indent) {
            content.push(next.slice(Math.min(indentOf(next), contentIndent)))
          }
          else if (nested || HEADING.test(next)) {
            break
          }
          else {
            // A lazy continuation line belongs to the item's text.
            content.push(next.trim())
          }

          index++
        }

        items.push(parseLines(content))
      }

      blocks.push({ type: 'list', ordered, start: ordered ? Number.parseInt(first[2]!, 10) : 1, items })
      continue
    }

    const paragraph: string[] = []

    while (index < lines.length) {
      const next = lines[index]!

      if (next.trim() === '' || HEADING.test(next) || ITEM.test(next)) {
        break
      }

      paragraph.push(next.trim())
      index++
    }

    blocks.push({ type: 'paragraph', children: parseInline(paragraph.join('\n')) })
  }

  return blocks
}

export function parseMarkdown(source: string): MarkdownBlock[] {
  return parseLines(source.replace(/\r\n?/g, '\n').split('\n'))
}

/** The text of a tree, without markup — for labels and search. */
export function inlineText(nodes: MarkdownInline[]): string {
  return nodes.map(node => node.type === 'text' ? node.text : inlineText(node.children)).join('')
}
