<script setup lang="ts">
import type { IconName } from '../../../shared/app/components/TwIcon.vue'
import type { Recipe, RecipeBookEntry, RecipeFilters, RecipeList } from '~/composables/useRecipes'
import type { MealDto } from '../../../shared/app/composables/useMeals'
import { formatIsoDate } from '../../../shared/app/utils/calendar'

interface Props {
  /** The household's today: planning offers this week onwards. */
  today: string
}

const props: Props = defineProps<Props>()

const { t } = useI18n()

const { recipes, categories, recipe, suggestions, error, hint, loading, load, suggest, toggleFavorite, open, toTheList } = useRecipes()
/* A tablet offers writing and asks for the PIN on 423; an account only what it may. */
const { can } = useActor()

const filters = reactive<RecipeFilters>({
  q: '',
  max_minutes: null,
  vegetarian: false,
  difficulty: null,
  category: null,
  list: null,
})

const lists: { key: RecipeList, icon: IconName }[] = [
  { key: 'favorites', icon: 'heart' },
  { key: 'often', icon: 'repeat' },
  { key: 'forgotten', icon: 'history' },
  { key: 'own', icon: 'pencil' },
]

const opened = ref<RecipeBookEntry | null>(null)
/* `undefined`: no editor; `null`: a new recipe. */
const editing = ref<Recipe | null | undefined>(undefined)

/*
 * Delayed, because on the wall people type with a finger: one request per
 * keystroke would hit the recipe service for every letter.
 */
let timer: ReturnType<typeof setTimeout> | null = null

watch(() => filters.q, () => {
  if (timer) {
    clearTimeout(timer)
  }

  timer = setTimeout(() => load(filters), 300)
})

watch(() => [filters.max_minutes, filters.vegetarian, filters.difficulty, filters.category, filters.list], () => load(filters))

const filtered = computed(() => filters.max_minutes !== null || filters.vegetarian || filters.difficulty !== null || filters.category !== null || filters.list !== null)

/* Ideas belong to the plain book; whoever searches or narrows has a plan already. */
const showIdeas = computed(() => !filtered.value && filters.q === '' && suggestions.value.length > 0)

async function show(entry: RecipeBookEntry): Promise<void> {
  opened.value = entry
  recipe.value = null
  await open(entry)
}

function close(): void {
  opened.value = null
  recipe.value = null
}

async function servings(count: number): Promise<void> {
  if (opened.value) {
    await open(opened.value, count)
  }
}

/*
 * Planning from the recipe: a week to tap a meal in, the same entry the meal
 * plan makes. The recipe closes after it, and a line says where it went.
 */
const { create: planMeal, error: planError } = useMeals()
const planning = ref(false)
const planBusy = ref(false)

function startPlanning(): void {
  planError.value = null
  planning.value = true
}

async function plan(date: string, kind: MealDto['kind']): Promise<void> {
  const entry = opened.value

  if (entry === null) {
    return
  }

  planBusy.value = true

  const done = await planMeal({
    planned_on: date,
    kind,
    title: recipe.value?.title ?? entry.name,
    recipe_id: entry.id,
    recipe_provider: entry.provider,
  })

  planBusy.value = false

  if (done) {
    planning.value = false
    close()
    hint.value = t('meals.planned', {
      day: date === props.today ? t('common.today') : formatIsoDate(date, { weekday: 'long', day: 'numeric', month: 'long' }),
      kind: kind === 'lunch' ? t('meals.lunch') : t('meals.dinner'),
    })
  }
}

const { busy: shopping, run } = useBusy()

async function shop(): Promise<void> {
  const card = opened.value

  if (card) {
    await run(() => toTheList(card, recipe.value?.servings))
    close()
  }
}

async function saved(result: Recipe): Promise<void> {
  editing.value = undefined
  opened.value = { id: result.id, name: result.title ?? '', provider: 'builtin', provider_label: result.provider_label, minutes: null, difficulty: null, vegetarian: null, photo: null, favorite: result.favorite }
  await load(filters)
}

async function removed(): Promise<void> {
  editing.value = undefined
  close()
  await load(filters)
}

function reset(): void {
  Object.assign(filters, { max_minutes: null, vegetarian: false, difficulty: null, category: null, list: null })
}

onMounted(() => Promise.all([load(filters), suggest()]))
</script>

<template>
  <div class="flex h-full flex-col">
    <p
      v-if="error"
      role="alert"
      class="mb-4 flex shrink-0 items-center gap-3 rounded-2xl bg-warning px-5 py-3 text-base font-medium text-warning-ink"
    >
      <TwIcon name="warning" :size="20" />
      {{ error }}
    </p>

    <p
      v-else-if="hint"
      role="status"
      class="mb-4 shrink-0 rounded-2xl bg-surface-2 px-5 py-3 text-base font-medium text-ink-2"
    >
      {{ hint }}
    </p>

    <div class="mb-3 flex shrink-0 gap-2">
      <input
        v-model="filters.q"
        type="search"
        data-testid="recipe-search"
        :aria-label="$t('recipes.search')"
        :placeholder="$t('recipes.search')"
        class="min-h-13 min-w-0 flex-1 rounded-2xl border-2 border-line bg-canvas px-4 text-lg text-ink outline-none transition-colors placeholder:text-ink-3 focus:border-accent"
      >
      <button
        v-if="can('recipes.edit')"
        type="button"
        data-testid="recipe-new"
        :aria-label="$t('recipes.newTitle')"
        class="flex min-h-13 min-w-13 items-center justify-center rounded-2xl bg-accent text-accent-ink"
        @click="editing = null"
      >
        <TwIcon name="plus" :size="24" />
      </button>
    </div>

    <div class="-mx-1 mb-4 flex shrink-0 gap-2 overflow-x-auto px-1 pb-1" data-testid="recipe-filters">
      <button
        v-for="item in lists"
        :key="item.key"
        type="button"
        :data-testid="`recipe-list-${item.key}`"
        :aria-pressed="filters.list === item.key"
        class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-base font-medium transition-colors"
        :class="filters.list === item.key ? 'bg-accent text-accent-ink' : 'bg-surface text-ink'"
        @click="filters.list = filters.list === item.key ? null : item.key"
      >
        <TwIcon :name="item.icon" :size="18" />
        {{ $t(`recipes.lists.${item.key}`) }}
      </button>
      <button
        type="button"
        :aria-pressed="filters.max_minutes === 30"
        class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-base font-medium transition-colors"
        :class="filters.max_minutes === 30 ? 'bg-accent text-accent-ink' : 'bg-surface text-ink'"
        @click="filters.max_minutes = filters.max_minutes === 30 ? null : 30"
      >
        <TwIcon name="clock" :size="18" />
        {{ $t('recipes.quick') }}
      </button>
      <button
        type="button"
        :aria-pressed="filters.vegetarian"
        class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-base font-medium transition-colors"
        :class="filters.vegetarian ? 'bg-accent text-accent-ink' : 'bg-surface text-ink'"
        @click="filters.vegetarian = !filters.vegetarian"
      >
        <TwIcon name="vegetarian" :size="18" />
        {{ $t('recipes.vegetarian') }}
      </button>
      <button
        v-for="level in (['easy', 'medium', 'hard'] as const)"
        :key="level"
        type="button"
        :aria-pressed="filters.difficulty === level"
        class="min-h-11 shrink-0 rounded-full px-4 text-base font-medium transition-colors"
        :class="filters.difficulty === level ? 'bg-accent text-accent-ink' : 'bg-surface text-ink'"
        @click="filters.difficulty = filters.difficulty === level ? null : level"
      >
        {{ $t(`recipes.difficulty.${level}`) }}
      </button>
      <select
        v-if="categories.length > 0"
        v-model="filters.category"
        :aria-label="$t('recipes.categories')"
        class="min-h-11 shrink-0 rounded-full bg-surface px-4 text-base font-medium text-ink"
      >
        <option :value="null">
          {{ $t('recipes.allCategories') }}
        </option>
        <option v-for="category in categories" :key="category" :value="category">
          {{ category }}
        </option>
      </select>
    </div>

    <section v-if="showIdeas" class="mb-4 shrink-0" data-testid="recipe-ideas">
      <div class="mb-2 flex items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-ink-2">
          {{ $t('recipes.ideas') }}
        </h2>
        <button
          type="button"
          data-testid="recipe-ideas-other"
          class="flex min-h-11 items-center gap-1.5 rounded-full bg-surface px-4 text-base font-medium text-ink"
          @click="suggest"
        >
          <TwIcon name="shuffle" :size="18" />
          {{ $t('recipes.otherIdeas') }}
        </button>
      </div>
      <ul class="-mx-1 flex gap-3 overflow-x-auto px-1 pb-1">
        <li v-for="entry in suggestions" :key="`${entry.provider}-${entry.id}`" class="w-44 shrink-0">
          <button
            type="button"
            data-testid="recipe-idea"
            class="flex h-full w-full flex-col overflow-hidden rounded-2xl bg-surface text-left transition-colors hover:bg-surface-2"
            @click="show(entry)"
          >
            <img
              v-if="entry.photo"
              :src="entry.photo"
              alt=""
              loading="lazy"
              referrerpolicy="no-referrer"
              class="h-24 w-full bg-surface-2 object-cover"
            >
            <span class="flex flex-1 items-start gap-1.5 p-3 text-base font-semibold text-ink">
              <TwIcon v-if="entry.favorite" name="heart" :size="16" class="mt-1 shrink-0 text-accent" :aria-label="$t('recipes.favorite')" />
              <span class="line-clamp-2">{{ entry.name }}</span>
            </span>
          </button>
        </li>
      </ul>
    </section>

    <div
      v-if="recipes.length === 0"
      class="flex flex-col items-center rounded-3xl border-2 border-dashed border-line px-6 py-20 text-center"
    >
      <TwIcon name="recipes" :size="44" class="text-ink-3" />
      <p class="mt-4 text-lg text-ink-2">
        {{ filtered || filters.q ? $t('recipes.nothingFound') : $t('recipes.none') }}
      </p>
      <button
        v-if="filtered"
        type="button"
        class="mt-4 min-h-12 rounded-2xl bg-surface px-5 text-base font-medium text-ink"
        @click="reset"
      >
        {{ $t('recipes.resetFilters') }}
      </button>
    </div>

    <ul v-else class="grid min-h-0 flex-1 auto-rows-min grid-cols-1 gap-3 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">
      <li v-for="entry in recipes" :key="`${entry.provider}-${entry.id}`">
        <button
          type="button"
          data-testid="recipe-card"
          class="flex min-h-24 w-full items-stretch gap-4 overflow-hidden rounded-2xl bg-surface text-left transition-colors hover:bg-surface-2"
          @click="show(entry)"
        >
          <img
            v-if="entry.photo"
            :src="entry.photo"
            alt=""
            loading="lazy"
            referrerpolicy="no-referrer"
            class="w-28 shrink-0 bg-surface-2 object-cover"
          >
          <span class="flex min-w-0 flex-1 flex-col justify-between p-4" :class="entry.photo ? 'pl-0' : ''">
            <span class="text-lg font-semibold text-ink">{{ entry.name }}</span>
            <span class="flex flex-wrap items-center gap-x-3 text-base text-ink-3">
              <span v-if="entry.minutes" class="flex items-center gap-1">
                <TwIcon name="clock" :size="16" />
                {{ $t('recipes.minutes', { count: entry.minutes }) }}
              </span>
              <TwIcon v-if="entry.vegetarian" name="vegetarian" :size="16" :aria-label="$t('recipes.vegetarian')" />
              <TwIcon v-if="entry.favorite" name="heart" :size="16" class="text-accent" :aria-label="$t('recipes.favorite')" />
              <span>{{ entry.provider_label }}</span>
            </span>
          </span>
        </button>
      </li>
    </ul>

    <RecipeDetail
      v-if="opened && recipe"
      :recipe="recipe"
      :name="opened.name"
      :loading="loading"
      @close="close"
      @servings="servings"
      :shopping="shopping"
      @to-list="shop"
      @plan="startPlanning"
      @edit="editing = recipe"
      @favorite="toggleFavorite({ ...recipe, name: opened.name })"
    />

    <MealSlotPicker
      v-if="planning && opened"
      :name="recipe?.title ?? opened.name"
      :today="props.today"
      :busy="planBusy"
      :error="planError"
      @close="planning = false"
      @pick="plan"
    />

    <RecipeEditor
      v-if="editing !== undefined"
      :recipe="editing"
      @close="editing = undefined"
      @saved="saved"
      @removed="removed"
    />
  </div>
</template>
