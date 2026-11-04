/** Words of three letters or more, lower case. */
function termsOf(text: string): string[] {
  return text.toLocaleLowerCase().split(/\s+/).filter(term => term.length >= 3)
}

/**
 * The icons a task form offers.
 *
 * With a search: only icons whose words contain every term. Without one:
 * icons matching the title first ("Gemüse gegessen" → 🥦 🥕), then the rest.
 */
export function iconChoices(icons: string[], words: Record<string, string>, search: string, title: string): string[] {
  const wordsOf = (icon: string): string[] => (words[icon] ?? '').split(' ')
  const wanted = termsOf(search)

  if (wanted.length > 0) {
    return icons.filter(icon => wanted.every(term => wordsOf(icon).some(word => word.includes(term))))
  }

  const fromTitle = termsOf(title)
  const matches = (icon: string): boolean => fromTitle.some(term => wordsOf(icon)
    .some(word => word.startsWith(term) || (word.length >= 4 && term.startsWith(word))))

  return [...icons.filter(matches), ...icons.filter(icon => !matches(icon))]
}
