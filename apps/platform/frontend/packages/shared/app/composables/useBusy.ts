/**
 * For a button whose action waits for the server: `busy` while it runs, and
 * a second tap meanwhile is ignored — on the wall children tap again when
 * nothing seems to happen.
 */
export function useBusy() {
  const busy = ref(false)

  async function run<T>(action: () => Promise<T>): Promise<T | undefined> {
    if (busy.value) {
      return undefined
    }

    busy.value = true

    try {
      return await action()
    }
    finally {
      busy.value = false
    }
  }

  return { busy, run }
}
