/*
 * Conversion between wall-clock time and instant.
 *
 * A datetime-local field delivers and expects wall-clock time without a
 * zone. But the wall computes in the household's time zone, not the
 * device's. Without an explicit conversion saving would produce the same bug
 * as in the backend: the time would look right, the instant would be
 * shifted.
 */

/** Offset of the zone at the given instant, in minutes. */
function offsetMinutes(instant: Date, timeZone: string): number {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    hour12: false,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).formatToParts(instant)

  const value = (kind: string): number => Number(parts.find(part => part.type === kind)?.value ?? '0')

  // Hour 24 exists for midnight with hour12: false in some environments.
  const asUtc = Date.UTC(
    value('year'),
    value('month') - 1,
    value('day'),
    value('hour') % 24,
    value('minute'),
    value('second'),
  )

  return (asUtc - Math.floor(instant.getTime() / 1000) * 1000) / 60000
}

/**
 * Wall-clock time (`YYYY-MM-DDTHH:mm`) in the zone → instant as ISO with offset.
 *
 * Computed twice, because the offset itself depends on the instant: on the
 * clock-change days the first guess would otherwise be off by an hour.
 */
export function instantFromWallTime(wallTime: string, timeZone: string): string {
  const [date = '', clock = '00:00'] = wallTime.split('T')
  const [year = 0, month = 1, day = 1] = date.split('-').map(Number)
  const [hour = 0, minute = 0] = clock.split(':').map(Number)

  const rough = Date.UTC(year, month - 1, day, hour, minute)
  const firstGuess = rough - offsetMinutes(new Date(rough), timeZone) * 60000
  const moment = rough - offsetMinutes(new Date(firstGuess), timeZone) * 60000

  return new Date(moment).toISOString()
}

/** Instant → wall-clock time in the zone, suitable for a datetime-local field. */
export function wallTimeFromInstant(iso: string, timeZone: string): string {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    hour12: false,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).formatToParts(new Date(iso))

  const value = (kind: string): string => parts.find(part => part.type === kind)?.value ?? '00'
  const hour = value('hour') === '24' ? '00' : value('hour')

  return `${value('year')}-${value('month')}-${value('day')}T${hour}:${value('minute')}`
}

/** All day: midnight to midnight of the next day, end exclusive. */
export function allDaySpan(fromIso: string, untilIso: string, timeZone: string): [string, string] {
  const from = instantFromWallTime(`${fromIso}T00:00`, timeZone)
  const until = instantFromWallTime(`${untilIso}T00:00`, timeZone)

  return [from, from === until ? instantFromWallTime(`${untilIso}T24:00`, timeZone) : until]
}
