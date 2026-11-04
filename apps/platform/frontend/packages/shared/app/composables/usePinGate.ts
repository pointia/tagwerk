/**
 * Writes the wall may only do with the parent PIN.
 *
 * On 423 the gate opens the PIN dialog — mounted once in the wall's app.vue —
 * and repeats the call after unlocking. The phone never gets 423: someone is
 * signed in there, and what they may not do is simply not offered.
 */
let pending: ((unlocked: boolean) => void) | null = null

export function usePinGate() {
  const open = useState<boolean>('pin-gate', () => false)

  /*
   * Give the `$fetch` inside a response type (`$fetch<unknown>(…)`), here
   * and in every wrapper like it: left to inference, T is matched against
   * Nuxt's typed routes, which exceeds the compiler's depth limit on
   * dynamic paths.
   */
  async function withPin<T>(call: () => Promise<T>): Promise<T> {
    try {
      return await call()
    }
    catch (error: unknown) {
      if ((error as { response?: { status?: number } })?.response?.status !== 423) {
        throw error
      }

      open.value = true

      const unlocked = await new Promise<boolean>((resolve) => {
        pending = resolve
      })

      if (!unlocked) {
        throw error
      }

      return await call()
    }
  }

  function settle(unlocked: boolean): void {
    open.value = false
    pending?.(unlocked)
    pending = null
  }

  return { open, withPin, settle }
}
