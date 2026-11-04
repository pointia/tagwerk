/*
 * The toolbar of TwMarkdownEditor: what a button does to the text and the
 * selection. Pure, so Vitest checks it without a textarea.
 */

export type MarkdownFormat = 'heading' | 'bold' | 'italic' | 'list' | 'numbered' | 'link'

export interface MarkdownEdit {
  value: string
  start: number
  end: number
}

function wrap(edit: MarkdownEdit, before: string, after: string, placeholder: string): MarkdownEdit {
  const selected = edit.value.slice(edit.start, edit.end) || placeholder
  const value = edit.value.slice(0, edit.start) + before + selected + after + edit.value.slice(edit.end)
  const start = edit.start + before.length

  return { value, start, end: start + selected.length }
}

/** Prefixes every line the selection touches; a second press removes it. */
function lines(edit: MarkdownEdit, prefix: (index: number) => string, pattern: RegExp): MarkdownEdit {
  const from = edit.value.lastIndexOf('\n', edit.start - 1) + 1
  const lineEnd = edit.value.indexOf('\n', edit.end)
  const to = lineEnd === -1 ? edit.value.length : lineEnd
  const block = edit.value.slice(from, to).split('\n')
  const remove = block.every(line => pattern.test(line))
  const changed = block
    .map((line, index) => remove ? line.replace(pattern, '') : prefix(index) + line.replace(pattern, ''))
    .join('\n')

  const value = edit.value.slice(0, from) + changed + edit.value.slice(to)

  return { value, start: from, end: from + changed.length }
}

export function applyMarkdown(edit: MarkdownEdit, format: MarkdownFormat, placeholders: { text: string, link: string }): MarkdownEdit {
  switch (format) {
    case 'bold':
      return wrap(edit, '**', '**', placeholders.text)
    case 'italic':
      return wrap(edit, '*', '*', placeholders.text)
    case 'link': {
      const label = edit.value.slice(edit.start, edit.end) || placeholders.link
      const value = `${edit.value.slice(0, edit.start)}[${label}](https://)${edit.value.slice(edit.end)}`
      // The address is what is still missing — select it.
      const start = edit.start + label.length + 3

      return { value, start, end: start + 'https://'.length }
    }
    case 'heading':
      return lines(edit, () => '## ', /^#{1,6}\s+/)
    case 'list':
      return lines(edit, () => '- ', /^[-*+]\s+/)
    case 'numbered':
      return lines(edit, index => `${index + 1}. `, /^\d+[.)]\s+/)
  }
}
