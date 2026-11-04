import { describe, expect, it } from 'vitest'
import { type HouseholdNotice, noticeSeenKey, rememberSeen, shownNotices } from '../src/rules/notice'

const notice: HouseholdNotice = { text: 'Noch 5 Tage', level: 'info', url: null, action: null }
const other: HouseholdNotice = { text: 'Google abgelaufen', level: 'warning', url: null, action: null, key: 'reconnect:1:0' }
const today = '2026-11-02'

describe('shownNotices', () => {
  it('shows nothing without notices', () => {
    expect(shownNotices(null, false, [], today)).toEqual([])
  })

  it('shows an account everything the server sends', () => {
    expect(shownNotices([notice, other], false, [noticeSeenKey(notice, today)], today)).toEqual([notice, other])
  })

  it('shows each on a wall once a day', () => {
    expect(shownNotices([notice, other], true, [noticeSeenKey(notice, today)], today)).toEqual([other])
    expect(shownNotices([notice], true, [noticeSeenKey(notice, '2026-11-01')], today)).toEqual([notice])
  })

  it('shows a changed text again the same day', () => {
    const changed = { ...notice, text: 'Noch 4 Tage' }

    expect(shownNotices([changed], true, [noticeSeenKey(notice, today)], today)).toEqual([changed])
  })
})

describe('rememberSeen', () => {
  it('keeps only today and no duplicates', () => {
    const seen = rememberSeen([noticeSeenKey(other, '2026-11-01'), noticeSeenKey(notice, today)], notice, today)

    expect(seen).toEqual([noticeSeenKey(notice, today)])
    expect(rememberSeen(seen, other, today)).toEqual([noticeSeenKey(notice, today), noticeSeenKey(other, today)])
  })
})
