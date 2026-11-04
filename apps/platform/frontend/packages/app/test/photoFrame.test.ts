import { describe, expect, it } from 'vitest'
import { dayRange, duoCount, groupPhotos, nextTile, photoIntervalMs, photoStyle, restingStyle, tileStyle, upcomingToday, wrapped, zoomed } from '../app/utils/photoFrame'

describe('photo styles', () => {
  it('falls back to a single picture for an unknown style', () => {
    expect(photoStyle('mosaic')).toBe('mosaic')
    expect(photoStyle('carousel')).toBe('single')
  })

  it('rests in its own style or the awake one', () => {
    expect(restingStyle('duo', 'same')).toBe('duo')
    expect(restingStyle('duo', 'ambient')).toBe('ambient')
  })

  it('reads the interval in seconds', () => {
    expect(photoIntervalMs('300')).toBe(300_000)
    expect(photoIntervalMs('')).toBe(30_000)
  })
})

describe('duo', () => {
  const urls = ['a', 'b', 'c']

  it('pairs two portraits', () => {
    expect(duoCount(urls, 0, { a: 0.75, b: 0.66 })).toBe(2)
  })

  it('lets a landscape or an unknown size stand alone', () => {
    expect(duoCount(urls, 0, { a: 0.75, b: 1.5 })).toBe(1)
    expect(duoCount(urls, 0, { a: 0.75 })).toBe(1)
    expect(duoCount(urls, 2, { c: 0.75 })).toBe(1)
  })
})

describe('mosaic', () => {
  it('never changes the same tile twice in a row', () => {
    for (const draw of [0, 0.3, 0.6, 0.99]) {
      expect(nextTile(5, 2, () => draw)).not.toBe(2)
    }
  })

  it('has only one choice with one tile', () => {
    expect(nextTile(1, 0)).toBe(0)
  })
})

describe('dashboard tile', () => {
  it('grows from one picture to a duo to a mosaic', () => {
    expect(tileStyle(3, 3)).toBe('single')
    expect(tileStyle(4, 2)).toBe('duo')
    expect(tileStyle(6, 4)).toBe('mosaic')
  })
})

describe('library', () => {
  const photos = [
    { id: 1, taken_on: '2026-10-20', added_on: '2026-10-21' },
    { id: 2, taken_on: '2026-10-03', added_on: '2026-10-21' },
    { id: 3, taken_on: null, added_on: '2026-09-30' },
    { id: 4, taken_on: '2025-07-14', added_on: '2026-09-01' },
  ]

  it('groups by month, the upload day standing in for a missing date', () => {
    expect(groupPhotos(photos, 'months').map(group => [group.key, group.photos.map(photo => photo.id)])).toEqual([
      ['2026-10', [1, 2]],
      ['2026-09', [3]],
      ['2025-07', [4]],
    ])
  })

  it('groups by year and by day', () => {
    expect(groupPhotos(photos, 'years').map(group => group.key)).toEqual(['2026', '2025'])
    expect(groupPhotos(photos, 'days')).toHaveLength(4)
  })

  it('zooms one step and stops at the ends', () => {
    expect(zoomed('months', 1)).toBe('days')
    expect(zoomed('days', 1)).toBe('days')
    expect(zoomed('years', -1)).toBe('years')
  })

  it('wraps when paging past either end', () => {
    expect(wrapped(0, -1, 3)).toBe(2)
    expect(wrapped(2, 1, 3)).toBe(0)
    expect(wrapped(0, 1, 0)).toBe(0)
  })
})

describe('ambient', () => {
  const zone = 'Europe/Vienna'
  const now = new Date('2026-10-26T09:30:00Z') // 10:30 in Vienna

  it('lists today in the household zone, all-day first, past ones gone', () => {
    const events = [
      { id: 1, starts_at: '2026-10-26T06:00:00Z', ends_at: '2026-10-26T07:00:00Z', all_day: false },
      { id: 2, starts_at: '2026-10-26T14:30:00Z', ends_at: '2026-10-26T15:30:00Z', all_day: false },
      { id: 3, starts_at: '2026-10-25T23:00:00Z', ends_at: '2026-10-26T23:00:00Z', all_day: true },
      // 00:30 on the 27th in Vienna, though still the 26th in UTC.
      { id: 4, starts_at: '2026-10-26T23:30:00Z', ends_at: '2026-10-27T00:30:00Z', all_day: false },
    ]

    expect(upcomingToday(events, now, zone).map(event => event.id)).toEqual([3, 2])
  })
})

describe('memory titles', () => {
  it('names one day or a run of days', () => {
    expect(dayRange('2025-07-14', '2025-07-14', 'de-AT')).toBe('14. Juli 2025')
    expect(dayRange('2025-07-14', '2025-07-16', 'de-AT')).toBe('14.–16. Juli 2025')
  })
})
