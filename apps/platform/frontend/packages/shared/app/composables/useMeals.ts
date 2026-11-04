import { isOffline } from '../utils/network'

export interface MealDto {
  id: number
  date: string
  kind: 'lunch' | 'dinner'
  kind_label: string
  title: string
  notes: string | null
  /** A string, because not every provider counts — Mealie uses slugs. */
  recipe_id: string | null
  recipe_provider: string | null
  /** Person ids; null is the whole household. */
  eaters: number[] | null
  /** Adult portions for the eaters, computed by the server. */
  servings: number
  /** The planned recipe's picture; missing where the source has none or is down. */
  photo?: string | null
}

/** A meal the household cooks, in the day's order. */
export interface MealKindDto {
  value: MealDto['kind']
  label: string
}

export interface MealPerson {
  id: number
  name: string
  color: string | null
  /** Eats along only when chosen; "everyone" leaves guests out. */
  guest?: boolean
}

export interface RecipeDto {
  id: string
  name: string
  provider: string
  provider_label: string
}

export function useMeals() {
  const { t } = useI18n()

  const { withPin } = usePinGate()

  const meals = useState<MealDto[]>('meals', () => [])
  const people = useState<MealPerson[]>('meal-people', () => [])
  // Until the first answer both; the server narrows it to what the household cooks.
  const kinds = useState<MealKindDto[]>('meal-kinds', () => [
    { value: 'lunch', label: t('meals.lunch') },
    { value: 'dinner', label: t('meals.dinner') },
  ])
  const recipes = useState<RecipeDto[]>('recipes', () => [])
  const recipeSources = useState<{ key: string, label: string }[]>('recipe-sources', () => [])
  const error = ref<string | null>(null)
  const hint = ref<string | null>(null)

  async function load(from: string, until: string): Promise<void> {
    try {
      const data = await $fetch<{ meals: MealDto[], people: MealPerson[], kinds?: MealKindDto[] }>('/api/wall/meals', {
        params: { from: from, to: until },
        baseURL: '/', credentials: 'same-origin',
      })

      meals.value = data.meals
      people.value = data.people
      kinds.value = data.kinds ?? kinds.value
      error.value = null
    }
    catch {
      error.value = t('meals.loadFailed')
    }
  }

  async function searchRecipes(term = ''): Promise<void> {
    try {
      const data = await $fetch<{ providers: { key: string, label: string }[], recipes: RecipeDto[] }>('/api/wall/recipes', {
        params: { q: term },
        baseURL: '/', credentials: 'same-origin',
      })

      recipes.value = data.recipes
      recipeSources.value = data.providers
    }
    catch {
      recipes.value = []
    }
  }

  async function create(entry: {
    planned_on: string
    kind: 'lunch' | 'dinner'
    title: string
    recipe_id?: string | null
    recipe_provider?: string | null
  }): Promise<boolean> {
    if (isOffline()) {
      error.value = t('network.offline')

      return false
    }

    try {
      const fresh = await withPin(() => $fetch<MealDto>('/api/wall/meals', {
        method: 'POST',
        body: entry,
        baseURL: '/', credentials: 'same-origin',
      }))

      meals.value = [...meals.value, fresh]
      error.value = null

      return true
    }
    catch {
      error.value = t('meals.notSaved')

      return false
    }
  }

  /** Who eats along; null is everyone. */
  async function setEaters(entry: MealDto, eaters: number[] | null): Promise<void> {
    try {
      const fresh = await withPin(() => $fetch<MealDto>(`/api/wall/meals/${entry.id}`, {
        method: 'PATCH',
        body: { eaters },
        baseURL: '/', credentials: 'same-origin',
      }))

      meals.value = meals.value.map(present => present.id === fresh.id ? fresh : present)
      error.value = null
    }
    catch {
      error.value = t('meals.notSaved')
    }
  }

  async function remove(entry: MealDto): Promise<void> {
    if (isOffline()) {
      error.value = t('network.offline')

      return
    }

    const before = meals.value

    meals.value = meals.value.filter(present => present.id !== entry.id)

    try {
      await withPin(() => $fetch<unknown>(`/api/wall/meals/${entry.id}`, {
        method: 'DELETE',
        baseURL: '/', credentials: 'same-origin',
      }))
    }
    catch {
      meals.value = before
      error.value = t('meals.notDeleted')
    }
  }

  /**
   * Feedback matters more here than elsewhere: whoever pushes the ingredients
   * to the list goes shopping afterwards and only notices a silent failure in
   * the shop.
   */
  async function ingredientsToList(entry: MealDto): Promise<void> {
    if (isOffline()) {
      error.value = t('network.offline')

      return
    }

    try {
      const response = await withPin(() => $fetch<{ added: number }>(
        `/api/wall/meals/${entry.id}/ingredients`,
        { method: 'POST', baseURL: '/', credentials: 'same-origin' },
      ))

      hint.value = response.added === 0
        ? t('ingredients.allThere')
        : t('ingredients.added', response.added)
      error.value = null
    }
    catch {
      error.value = t('ingredients.addFailed')
    }
  }

  /** Every planned recipe of the period, equal ingredients summed. */
  async function periodToList(from: string, until: string): Promise<void> {
    if (isOffline()) {
      error.value = t('network.offline')

      return
    }

    try {
      const response = await withPin(() => $fetch<{ added: number }>('/api/wall/meals/shopping', {
        method: 'POST',
        baseURL: '/',
        body: { from, until },
        credentials: 'same-origin',
      }))

      hint.value = response.added === 0
        ? t('ingredients.allThere')
        : t('ingredients.added', response.added)
      error.value = null
    }
    catch {
      error.value = t('ingredients.addFailed')
    }
  }

  return {
    meals,
    people,
    kinds,
    recipes,
    recipeSources,
    error,
    hint,
    load,
    searchRecipes,
    create,
    remove,
    setEaters,
    ingredientsToList,
    periodToList,
  }
}
