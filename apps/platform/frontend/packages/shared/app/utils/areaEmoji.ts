/**
 * The colourful menu on wall and phone: one emoji per area icon. Emoji rather than image
 * files — every tablet draws them in colour, and nothing has to be shipped.
 * Keyed by the icon name the server sends, so a new area only needs a line.
 */
const EMOJI: Record<string, string> = {
  dashboard: '🏠',
  today: '☀️',
  calendar: '📅',
  tasks: '✅',
  lists: '📝',
  points: '⭐',
  meals: '🍽️',
  recipes: '🥘',
  school: '🎒',
  photo: '🖼️',
}

/** Null means: no emoji known, keep the line icon. */
export function areaEmoji(icon: string): string | null {
  return EMOJI[icon] ?? null
}
