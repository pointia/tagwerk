import { actorCan } from '../utils/actor'
import type { Actor } from '../utils/actor'

/**
 * The one place views ask "may I?" — never "am I the wall or the phone?".
 * The app sets the actor once it knows: the wall from its agenda, the phone
 * from `/api/auth/me`.
 */
export function useActor() {
  const actor = useState<Actor | null>('actor', () => null)
  const { withPin } = usePinGate()

  return {
    actor,
    kind: computed(() => actor.value?.kind ?? null),
    personId: computed(() => actor.value?.personId ?? null),
    can: (permission: string): boolean => actorCan(actor.value, permission),
    /** Writes go through the PIN gate; an account never gets the 423. */
    write: withPin,
    set: (next: Actor | null): void => {
      actor.value = next
    },
  }
}
