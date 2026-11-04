/**
 * A person's or subject's colour as a soft fill.
 *
 * The tint lies on a base that only the glass look sets: there the surface
 * beneath is translucent, and a bare tint would mix with the household's
 * gradient — a blue chip on a peach backdrop turns grey. In the classic look
 * the base is transparent and the tint looks as it always did.
 *
 * Calendar entries take the `solid` base: an event among grid lines must
 * read as a thing on the glass, not as part of it.
 */
export function tint(color: string, base: 'soft' | 'solid' = 'soft'): string {
  return `linear-gradient(${color}, ${color}) var(--tint-${base}, transparent)`
}
