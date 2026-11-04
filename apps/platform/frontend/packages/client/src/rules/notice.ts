/** What the household should know — trial, read-only, a lost connection — as the server sends it. */
export interface HouseholdNotice {
  text: string
  level: 'info' | 'warning' | 'blocking'
  url: string | null
  action: string | null
  /** Names the state; an account closes it by this for good. */
  key?: string | null
  audience?: 'household' | 'managers' | 'operator'
  /** Whether an account may close it; blocking ones stay. */
  dismissible?: boolean
}

/**
 * Which strips show. An account sees what the server sends — it already
 * left out what the account closed. A wall shows each once a day, until
 * someone closes it: a hallway is no place for a permanent banner, but a
 * changed text counts as new.
 */
export function shownNotices(notices: HouseholdNotice[] | null | undefined, device: boolean, seen: string[], today: string): HouseholdNotice[] {
  if (!notices) {
    return []
  }

  return device ? notices.filter(notice => !seen.includes(noticeSeenKey(notice, today))) : notices
}

export function noticeSeenKey(notice: HouseholdNotice, today: string): string {
  return `${today}|${notice.text}`
}

/** The wall's memory after closing one: today's only, so it never grows. */
export function rememberSeen(seen: string[], notice: HouseholdNotice, today: string): string[] {
  const key = noticeSeenKey(notice, today)

  return [...seen.filter(entry => entry.startsWith(`${today}|`) && entry !== key), key]
}
