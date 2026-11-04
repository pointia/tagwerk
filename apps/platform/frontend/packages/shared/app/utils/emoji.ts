/**
 * The file name of an emoji in `public/emoji`: its code points in hex,
 * joined by dashes, without the variation selector FE0F. Stored icons come
 * with and without it, and the catalog's own glyphs carry it — so pictures
 * are always compared by this name, never by the text.
 */
export function emojiHex(emoji: string): string | null {
  const codes = [...emoji.trim()]
    .map(character => character.codePointAt(0) ?? 0)
    .filter(code => code !== 0xFE0F)
    .map(code => code.toString(16))

  return codes.length === 0 ? null : codes.join('-')
}

/** The picture of an emoji, coloured (Fluent "Flat") or grey ("High Contrast"). */
export function emojiSrc(emoji: string, colored: boolean): string | null {
  const hex = emojiHex(emoji)

  return hex === null ? null : `/emoji/${colored ? 'flat' : 'mono'}/${hex}.svg`
}
