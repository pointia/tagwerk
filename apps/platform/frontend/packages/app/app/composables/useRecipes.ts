import { isOffline } from '../../../shared/app/utils/network'

export interface RecipeBookEntry {
  /** A string, because not every provider counts — Mealie uses slugs. */
  id: string
  name: string
  provider: string
  provider_label: string
  minutes: number | null
  difficulty: 'easy' | 'medium' | 'hard' | null
  vegetarian: boolean | null
  photo: string | null
  favorite: boolean
}

export interface Ingredient {
  name: string
  /** Amount and unit, already scaled and formatted by the server. */
  spec: string | null
  note: string | null
  group: string | null
}

export interface RecipeLicense {
  text?: string | null
  author?: string | null
  url?: string | null
  photo_author?: string | null
  photo_license?: string | null
  photo_url?: string | null
}

export interface Recipe {
  provider: string
  provider_label: string
  id: string
  /** Null for sources that only know ingredients — the card has the name. */
  title: string | null
  intro?: string | null
  steps?: string | null
  servings?: number
  base_servings?: number
  minutes?: number | null
  difficulty?: RecipeBookEntry['difficulty']
  categories?: string[]
  vegetarian?: boolean | null
  photo?: { small: string, large: string } | null
  /** Photos of the recipe itself — a card or a cookbook page. Own recipes only. */
  pages?: RecipePage[]
  source_url?: string | null
  license?: RecipeLicense | null
  ingredients: Ingredient[]
  ingredient_text?: string | null
  favorite: boolean
  can: { edit: boolean }
}

export interface RecipePage {
  id: string
  url: string
}

/** What the editor changes about the photographed pages. */
export interface PageChanges {
  add: File[]
  drop: string[]
}

export interface RecipeInput {
  title: string
  intro: string
  steps: string
  servings: number
  minutes: number | null
  difficulty: RecipeBookEntry['difficulty']
  vegetarian: boolean
  categories: string[]
  ingredient_text: string
}

export interface RecipeFilters {
  q: string
  max_minutes: number | null
  vegetarian: boolean
  difficulty: RecipeBookEntry['difficulty']
  category: string | null
  /** The household's own lists instead of every source. */
  list: RecipeList | null
}

export type RecipeList = 'favorites' | 'often' | 'forgotten' | 'own'

function path(provider: string, id: string): string {
  return `/api/wall/recipe-book/${provider}/${encodeURIComponent(id)}`
}

function message(exception: unknown): string | undefined {
  const data = (exception as { data?: { message?: string } }).data

  return data?.message
}

export function useRecipes() {
  const { t } = useI18n()

  const { withPin } = usePinGate()

  const recipes = useState<RecipeBookEntry[]>('recipe-book', () => [])
  const categories = useState<string[]>('recipe-book-categories', () => [])
  const recipe = useState<Recipe | null>('recipe-book-recipe', () => null)
  const suggestions = useState<RecipeBookEntry[]>('recipe-book-suggestions', () => [])
  const error = ref<string | null>(null)
  const hint = ref<string | null>(null)
  const loading = ref(false)

  /* Only the latest search draws — an earlier, slower answer would otherwise win. */
  let generation = 0

  async function load(filters: Partial<RecipeFilters> = {}): Promise<void> {
    const mine = ++generation

    try {
      const data = await $fetch<{ recipes: RecipeBookEntry[], categories: string[], can: { edit: boolean } }>('/api/wall/recipe-book', {
        baseURL: '/',
        params: {
          q: filters.q || undefined,
          max_minutes: filters.max_minutes ?? undefined,
          vegetarian: filters.vegetarian ? 1 : undefined,
          difficulty: filters.difficulty ?? undefined,
          category: filters.category ?? undefined,
          list: filters.list ?? undefined,
        },
        credentials: 'same-origin',
      })

      if (mine !== generation) {
        return
      }

      recipes.value = data.recipes
      categories.value = data.categories
      error.value = null
    }
    catch {
      if (mine === generation) {
        error.value = t('recipes.loadFailed')
      }
    }
  }

  /** A handful of ideas; another seed gives others. */
  async function suggest(): Promise<void> {
    try {
      const data = await $fetch<{ recipes: RecipeBookEntry[] }>('/api/wall/recipe-book/suggestions', {
        baseURL: '/',
        params: { seed: Math.floor(Math.random() * 2147483647) },
        credentials: 'same-origin',
      })

      suggestions.value = data.recipes
    }
    catch {
      suggestions.value = []
    }
  }

  /** No PIN: liking a recipe changes nothing anyone else relies on. */
  async function toggleFavorite(entry: Pick<RecipeBookEntry, 'provider' | 'id' | 'favorite'> & { name?: string | null }): Promise<void> {
    const favorite = !entry.favorite

    try {
      await $fetch<unknown>(`${path(entry.provider, entry.id)}/favorite`, {
        baseURL: '/',
        method: favorite ? 'PUT' : 'DELETE',
        body: favorite ? { title: entry.name ?? undefined } : undefined,
        credentials: 'same-origin',
      })

      const mark = <T extends Pick<RecipeBookEntry, 'provider' | 'id'>>(item: T): T =>
        item.provider === entry.provider && item.id === entry.id ? { ...item, favorite } : item

      recipes.value = recipes.value.map(mark)
      suggestions.value = suggestions.value.map(mark)

      if (recipe.value) {
        recipe.value = mark(recipe.value)
      }

      error.value = null
    }
    catch (exception: unknown) {
      error.value = message(exception) ?? t('recipes.saveFailed')
    }
  }

  /** A whole recipe; `servings` asks the server to scale the amounts. */
  async function open(entry: Pick<RecipeBookEntry, 'provider' | 'id'>, servings?: number): Promise<void> {
    loading.value = true

    try {
      const data = await $fetch<{ recipe: Recipe }>(path(entry.provider, entry.id), {
        baseURL: '/',
        params: { servings },
        credentials: 'same-origin',
      })

      recipe.value = data.recipe
      error.value = null
    }
    catch {
      error.value = t('recipes.ingredientsFailed')
    }
    finally {
      loading.value = false
    }
  }

  /** The actual purpose of the area: look it up and go shopping. */
  async function toTheList(entry: Pick<RecipeBookEntry, 'provider' | 'id'>, servings?: number): Promise<void> {
    if (isOffline()) {
      error.value = t('network.offline')

      return
    }

    try {
      const data = await withPin(() => $fetch<{ added: number }>(`${path(entry.provider, entry.id)}/ingredients`, {
        baseURL: '/',
        method: 'POST',
        body: { servings },
        credentials: 'same-origin',
      }))

      hint.value = data.added === 0
        ? t('ingredients.allThere')
        : t('ingredients.added', data.added)
      error.value = null
    }
    catch (exception: unknown) {
      error.value = message(exception) ?? t('ingredients.addFailed')
    }
  }

  /**
   * Writes the own recipe, then its photo. Throws with the server's message,
   * so that the form stays open with what was typed.
   */
  async function save(id: string | null, input: RecipeInput, photo: File | null, dropPhoto: boolean, pages: PageChanges = { add: [], drop: [] }): Promise<Recipe> {
    try {
      let saved = (await withPin(() => $fetch<{ recipe: Recipe }>(
        id === null ? '/api/wall/recipe-book' : path('builtin', id),
        { baseURL: '/', method: id === null ? 'POST' : 'PATCH', body: input, credentials: 'same-origin' },
      ))).recipe

      if (photo !== null) {
        const body = new FormData()
        body.append('photo', photo)

        saved = (await withPin(() => $fetch<{ recipe: Recipe }>(`${path('builtin', saved.id)}/photo`, {
          baseURL: '/',
          method: 'POST',
          body,
          credentials: 'same-origin',
        }))).recipe
      }
      else if (dropPhoto && saved.photo) {
        saved = (await withPin(() => $fetch<{ recipe: Recipe }>(`${path('builtin', saved.id)}/photo`, {
          baseURL: '/',
          method: 'DELETE',
          credentials: 'same-origin',
        }))).recipe
      }

      for (const page of pages.drop) {
        saved = (await withPin(() => $fetch<{ recipe: Recipe }>(`${path('builtin', saved.id)}/pages/${page}`, {
          baseURL: '/',
          method: 'DELETE',
          credentials: 'same-origin',
        }))).recipe
      }

      // One at a time: the server keeps them in the order they arrive.
      for (const file of pages.add) {
        const body = new FormData()
        body.append('photo', file)

        saved = (await withPin(() => $fetch<{ recipe: Recipe }>(`${path('builtin', saved.id)}/pages`, {
          baseURL: '/',
          method: 'POST',
          body,
          credentials: 'same-origin',
        }))).recipe
      }

      recipe.value = saved

      return saved
    }
    catch (exception: unknown) {
      throw new Error(message(exception) ?? t('recipes.saveFailed'))
    }
  }

  /** A recipe site's page becomes an own recipe; the server reads and checks it. */
  async function fromLink(url: string): Promise<Recipe> {
    try {
      const data = await withPin(() => $fetch<{ recipe: Recipe }>('/api/wall/recipe-book/link', {
        baseURL: '/',
        method: 'POST',
        body: { url },
        credentials: 'same-origin',
      }))

      recipe.value = data.recipe

      return data.recipe
    }
    catch (exception: unknown) {
      throw new Error(message(exception) ?? t('recipes.linkFailed'))
    }
  }

  async function remove(id: string): Promise<void> {
    try {
      await withPin(() => $fetch<unknown>(path('builtin', id), {
        baseURL: '/',
        method: 'DELETE',
        credentials: 'same-origin',
      }))

      recipe.value = null
      recipes.value = recipes.value.filter(entry => !(entry.provider === 'builtin' && entry.id === id))
    }
    catch (exception: unknown) {
      error.value = message(exception) ?? t('recipes.saveFailed')
    }
  }

  return { recipes, categories, recipe, suggestions, error, hint, loading, load, suggest, toggleFavorite, open, toTheList, save, fromLink, remove }
}
