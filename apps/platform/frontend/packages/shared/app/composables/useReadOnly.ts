/**
 * The household is read-only: the server refused a write with 402. Its
 * message says why, its link where to resolve it; the call still fails for
 * whoever made it, so nothing shows as saved.
 */
export interface ReadOnlyRefusal {
  message: string
  url: string | null
  action: string | null
}

export function useReadOnly() {
  const refusal = useState<ReadOnlyRefusal | null>('read-only', () => null)

  return {
    refusal,
    dismiss: (): void => {
      refusal.value = null
    },
  }
}
