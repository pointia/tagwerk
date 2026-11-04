/**
 * Whether a response belongs to the days that were asked for.
 *
 * The server sends its range as ISO timestamps in the household's zone, so
 * the first ten characters are that zone's calendar day — no conversion,
 * which would shift it back to the device's day.
 */
export function coversRange(range: { from: string, to: string }, from: string, until: string): boolean {
  return range.from.slice(0, 10) === from && range.to.slice(0, 10) === until
}
