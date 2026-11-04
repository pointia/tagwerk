/** Who is using the app: a paired device or a signed-in account. */
export interface Actor {
  kind: 'device' | 'user'
  personId: number | null
  /** What the account may change; a device asks for the PIN instead. */
  permissions: string[]
}

/*
 * A device may try everything: without a PIN set the wall is allowed anyway,
 * with one the server answers 423 and the PIN unlocks. An account sees only
 * what it may do — it has no PIN to fall back on. Either way the server
 * checks again.
 */
export function actorCan(actor: Actor | null, permission: string): boolean {
  if (actor === null) {
    return false
  }

  return actor.kind === 'device' || actor.permissions.includes(permission)
}
