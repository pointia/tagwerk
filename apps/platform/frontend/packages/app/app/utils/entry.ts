/** Which shell the app shows: the wall's for a device, the phone's for an account. */
export type Entry = 'device' | 'user'

/*
 * A link already says who comes: a scanned pairing code is a tablet, a link
 * from a mail (sign-in, reset, invitation) or the way back from a sign-in
 * provider (its error, its second-factor ticket) is a person. Null leaves the
 * question to the server.
 */
export function entryFromUrl(search: string): Entry | null {
  const query = new URLSearchParams(search)

  if (query.has('code')) {
    return 'device'
  }

  return ['login', 'reset', 'invite', 'social', 'two-factor'].some(key => query.has(key)) ? 'user' : null
}

/*
 * When the server cannot be asked. 401 means nobody: the wall's shell, whose
 * pairing screen is the start page with both ways in. Without a network the
 * last answer holds — a kiosk must come up as a kiosk after a power cut.
 */
export function entryWithoutAnswer(status: number | undefined, remembered: string | null): Entry {
  if (status !== 401 && (remembered === 'device' || remembered === 'user')) {
    return remembered
  }

  return 'device'
}

/**
 * The household a notification leads to (`?household=`), when it is one of
 * the account's and not the one on screen. Anything else is ignored: the
 * server would refuse a foreign household anyway.
 */
export function householdFromUrl(search: string, households: { id: number, current: boolean }[]): number | null {
  const id = Number(new URLSearchParams(search).get('household'))
  const target = households.find(entry => entry.id === id)

  return target && !target.current ? target.id : null
}
