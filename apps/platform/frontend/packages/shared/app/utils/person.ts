/** A member of the household as the agenda delivers it, on wall and phone. */
export interface AgendaPerson {
  id: number
  name: string
  color: string | null
  /** URL of the picture; null means the initial is enough. */
  avatar: string | null
  role: 'adult' | 'child' | 'guest'
}
