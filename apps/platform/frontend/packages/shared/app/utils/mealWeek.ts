/*
 * The meal plan goes by calendar weeks, Monday to Sunday — the way a family
 * plans its shopping. Plain calendar dates, computed in UTC: the device's zone
 * must not shift a day (see `isoWeek`).
 */

function shifted(iso: string, days: number): string {
  const date = new Date(`${iso}T00:00:00Z`)

  date.setUTCDate(date.getUTCDate() + days)

  return date.toISOString().slice(0, 10)
}

/** The Monday of the week holding `iso`, `offset` weeks on. */
export function weekStart(iso: string, offset = 0): string {
  const weekday = (new Date(`${iso}T00:00:00Z`).getUTCDay() + 6) % 7

  return shifted(iso, offset * 7 - weekday)
}

/** The seven days from a Monday. */
export function weekDays(monday: string): string[] {
  return Array.from({ length: 7 }, (_, offset) => shifted(monday, offset))
}
