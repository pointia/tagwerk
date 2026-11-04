<script setup lang="ts">
import type { MealDto } from '../composables/useMeals'
import { formatIsoDate, isoWeek } from '../utils/calendar'
import { intlLocale } from '../utils/intlLocale'
import { weekDays, weekStart } from '../utils/mealWeek'

const { t } = useI18n()

interface Props {
  today: string
}

const props: Props = defineProps<Props>()

/*
 * A planned recipe opens its instructions. The view lives in the app, not
 * in this layer — the wall also keeps its resting mode away while it is open.
 */
const emit = defineEmits<{ recipe: [MealDto] }>()

// Read only for an account that may not plan meals.
const { can } = useActor()
const canEdit = computed(() => can('meals.edit'))

const {
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
  periodToList,
} = useMeals()

/* The meal whose eaters are being chosen, and the choice so far. */
const { busy: shopping, run: shop } = useBusy()

const eating = ref<MealDto | null>(null)
const chosen = ref<number[]>([])
useEscape(() => { eating.value = null }, () => eating.value !== null)

function portions(count: number): string {
  return new Intl.NumberFormat(intlLocale.value, { maximumFractionDigits: 1 }).format(count)
}

function chooseEaters(entry: MealDto): void {
  eating.value = entry
  chosen.value = entry.eaters ?? people.value.filter(person => !person.guest).map(person => person.id)
}

function toggleEater(id: number): void {
  chosen.value = chosen.value.includes(id)
    ? chosen.value.filter(present => present !== id)
    : [...chosen.value, id]
}

async function saveEaters(): Promise<void> {
  if (eating.value && chosen.value.length > 0) {
    await setEaters(eating.value, chosen.value)
    eating.value = null
  }
}

const { armed, tap } = useTwoStep()

/*
 * One calendar week at a time, Monday to Sunday — the way a family plans its
 * shopping. `<` and `>` page by weeks; the plan comes up on the current one.
 */
const offset = ref(0)
const monday = computed(() => weekStart(props.today, offset.value))
const dates = computed(() => weekDays(monday.value))
const sunday = computed(() => dates.value[6] as string)

const weekTitle = computed(() => {
  if (offset.value === 0) {
    return t('meals.week.this')
  }

  if (offset.value === 1) {
    return t('meals.week.next')
  }

  return offset.value === -1 ? t('meals.week.last') : t('meals.week.other', { n: isoWeek(monday.value) })
})

const weekRange = computed(() => `${formatIsoDate(monday.value, { day: 'numeric', month: 'short' })} – ${formatIsoDate(sunday.value, { day: 'numeric', month: 'short' })}`)

/* The shopping trip covers what is still ahead in the week on screen. */
const shoppingFrom = computed(() => monday.value < props.today ? props.today : monday.value)
const weekHasRecipes = computed(() => sunday.value >= props.today && meals.value.some(entry =>
  entry.recipe_id !== null && entry.date >= shoppingFrom.value && entry.date <= sunday.value))

const days = computed(() => dates.value.map((iso) => {
  const onDay = meals.value.filter(entry => entry.date === iso)

  return {
    iso,
    title: iso === props.today
      ? t('common.today')
      : formatIsoDate(iso, { weekday: 'long', day: 'numeric', month: 'long' }),
    /*
     * The meals the household cooks — and one it stopped cooking only where
     * something is still planned, so nothing disappears unseen.
     */
    blocks: (['lunch', 'dinner'] as const)
      .filter(kind => kinds.value.some(entry => entry.value === kind) || onDay.some(entry => entry.kind === kind))
      .map(kind => ({
        kind,
        label: kind === 'lunch' ? t('meals.lunch') : t('meals.dinner'),
        entries: onDay.filter(entry => entry.kind === kind),
        plannable: kinds.value.some(entry => entry.value === kind),
      })),
  }
}))

const dialog = ref<{ iso: string, kind: 'lunch' | 'dinner' } | null>(null)
useEscape(() => { dialog.value = null }, () => dialog.value !== null)
const customText = ref('')
const search = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null

function open(iso: string, kind: 'lunch' | 'dinner'): void {
  dialog.value = { iso, kind }
  customText.value = ''
  search.value = ''
  searchRecipes()
}

async function enter(title: string, recipeId: string | null = null, provider: string | null = null): Promise<void> {
  if (!dialog.value || title.trim() === '') {
    return
  }

  const success = await create({
    planned_on: dialog.value.iso,
    kind: dialog.value.kind,
    title: title.trim(),
    recipe_id: recipeId,
    recipe_provider: provider,
  })

  if (success) {
    dialog.value = null
  }
}

/*
 * Only search after a typing pause: otherwise a request goes to Tandoor per
 * letter, and a slow answer to "Nu" overwrites the faster one to "Nudeln".
 */
watch(search, (term) => {
  if (searchTimer !== null) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => searchRecipes(term), 300)
})

watch(monday, () => load(monday.value, sunday.value), { immediate: true })

onUnmounted(() => {
  if (searchTimer !== null) {
    clearTimeout(searchTimer)
  }
})
</script>

<template>
  <div class="h-full overflow-y-auto pb-6">
    <p
      v-if="error"
      role="alert"
      class="mb-4 flex items-center gap-3 rounded-2xl bg-warning px-5 py-3 text-base font-medium text-warning-ink"
    >
      <TwIcon name="warning" :size="20" />
      {{ error }}
    </p>

    <p
      v-else-if="hint"
      role="status"
      class="mb-4 flex items-center gap-3 rounded-2xl bg-success-soft px-5 py-3 text-base font-medium text-success"
    >
      <TwIcon name="check" :size="20" />
      {{ hint }}
    </p>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="flex min-w-0 flex-1 items-center gap-1 rounded-2xl bg-surface-2 p-1 sm:flex-none">
        <button
          type="button"
          data-testid="week-back"
          :aria-label="$t('meals.week.back')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2 transition-colors hover:bg-surface hover:text-ink"
          @click="offset--"
        >
          <TwIcon name="back" :size="20" />
        </button>

        <span class="min-w-0 flex-1 px-1 text-center leading-tight sm:min-w-44">
          <span data-testid="week-title" class="block text-base font-semibold">{{ weekTitle }}</span>
          <span class="block text-sm text-ink-3">{{ weekRange }}</span>
        </span>

        <button
          type="button"
          data-testid="week-forward"
          :aria-label="$t('meals.week.forward')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2 transition-colors hover:bg-surface hover:text-ink"
          @click="offset++"
        >
          <TwIcon name="forward" :size="20" />
        </button>
      </div>

      <button
        v-if="offset !== 0"
        type="button"
        data-testid="week-today"
        class="min-h-11 rounded-2xl px-4 text-base font-semibold text-accent transition-colors hover:bg-surface-2"
        @click="offset = 0"
      >
        {{ $t('meals.week.this') }}
      </button>

      <button
        v-if="canEdit && weekHasRecipes"
        type="button"
        data-testid="week-ingredients"
        class="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-surface px-5 text-base font-medium text-ink shadow-card sm:ml-auto sm:w-auto"
        :disabled="shopping"
        @click="shop(() => periodToList(shoppingFrom, sunday))"
      >
        <TwIcon name="lists" :size="20" />
        {{ $t('meals.weekIngredients') }}
        <TwPending :busy="shopping" />
      </button>
    </div>

    <!-- minmax(0, …): a long recipe title must truncate, not widen the page. -->
    <div class="grid grid-cols-[minmax(0,1fr)] gap-4 xl:grid-cols-2">
      <section
        v-for="day in days"
        :key="day.iso"
        data-testid="meal-day"
        :data-date="day.iso"
        :aria-labelledby="`essen-${day.iso}`"
        class="rounded-3xl bg-surface px-5 pt-5 pb-3 shadow-card"
        :class="day.iso === props.today ? 'ring-2 ring-today' : ''"
      >
        <h2 :id="`essen-${day.iso}`" class="mb-2 font-display text-2xl font-semibold capitalize">
          {{ day.title }}
        </h2>

        <div
          v-for="block in day.blocks"
          :key="block.kind"
          class="flex flex-col gap-1 border-t border-line py-2 first-of-type:border-t-0 sm:flex-row sm:items-start sm:gap-3"
        >
          <!-- On a phone above the meals: beside them it left the title a few letters. -->
          <span class="shrink-0 pt-1 text-sm font-bold tracking-wider text-ink-3 uppercase sm:w-20 sm:pt-3.5">{{ block.label }}</span>

          <div class="w-full min-w-0 flex-1 space-y-1">
            <div
              v-for="entry in block.entries"
              :key="entry.id"
              data-testid="meal"
              class="flex min-h-12 items-center gap-1.5 rounded-2xl bg-surface-2/70 py-1 pr-1"
              :class="entry.photo ? 'pl-1' : 'pl-4'"
            >
              <button
                v-if="entry.photo && entry.recipe_id"
                type="button"
                data-testid="meal-photo"
                :aria-label="$t('meals.recipeOf', { name: entry.title })"
                class="shrink-0 overflow-hidden rounded-xl"
                @click="emit('recipe', entry)"
              >
                <img
                  :src="entry.photo"
                  alt=""
                  referrerpolicy="no-referrer"
                  loading="lazy"
                  class="size-12 bg-surface-3 object-cover sm:size-16"
                >
              </button>

              <span class="min-w-0 flex-1 truncate text-lg font-medium" :class="entry.photo ? 'pl-2' : ''">{{ entry.title }}</span>

              <button
                type="button"
                data-testid="meal-eaters"
                :disabled="!canEdit"
                :aria-label="$t('meals.eaters', { count: portions(entry.servings) })"
                class="flex min-h-11 shrink-0 items-center gap-1 rounded-xl px-2 text-base font-medium text-ink-2 transition-colors enabled:hover:bg-surface"
                @click="chooseEaters(entry)"
              >
                <TwIcon name="servings" :size="18" />
                {{ portions(entry.servings) }}
              </button>

              <button
                v-if="entry.recipe_id"
                type="button"
                data-testid="meal-recipe"
                :aria-label="$t('meals.instructions')"
                class="flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-success-soft text-base font-semibold text-success sm:px-3"
                @click="emit('recipe', entry)"
              >
                <TwIcon name="recipes" :size="18" />
                <span class="hidden sm:inline">{{ $t('meals.instructions') }}</span>
              </button>

              <button
                v-if="canEdit"
                type="button"
                :aria-label="armed === `essen-${entry.id}` ? $t('common.removeNamedConfirm', { name: entry.title }) : $t('common.removeNamed', { name: entry.title })"
                class="flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1.5 rounded-xl text-sm font-bold transition-colors"
                :class="armed === `essen-${entry.id}`
                  ? 'bg-danger px-3 text-surface'
                  : 'text-ink-3 hover:bg-danger-soft hover:text-danger'"
                @click="tap(`essen-${entry.id}`, () => remove(entry))"
              >
                <TwIcon name="delete" :size="20" />
                <span v-if="armed === `essen-${entry.id}`">{{ $t('common.removeAsk') }}</span>
              </button>
            </div>

            <button
              v-if="canEdit && block.plannable"
              type="button"
              data-testid="meal-plan"
              class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-base font-semibold text-ink-3 transition-colors hover:bg-surface-2 hover:text-ink"
              @click="open(day.iso, block.kind)"
            >
              <TwIcon name="plus" :size="18" />
              {{ $t('meals.add') }}
            </button>
          </div>
        </div>
      </section>
    </div>

    <div
      v-if="dialog"
      class="fixed inset-0 z-30 flex items-center justify-center bg-scrim p-6"
      @click.self="dialog = null"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="essen-dialog-title"
        class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-3xl bg-surface p-7 shadow-floating"
      >
        <h2 id="essen-dialog-title" class="font-display text-3xl font-semibold">
          {{ dialog.kind === 'lunch' ? $t('meals.planLunch') : $t('meals.planDinner') }}
        </h2>

        <form class="mt-5 flex gap-2" @submit.prevent="enter(customText)">
          <input
            v-model="customText"
            type="text"
            data-testid="meal-title"
            :aria-label="$t('meals.own')"
            :placeholder="$t('meals.ownPlaceholder')"
            class="min-h-13 min-w-0 flex-1 rounded-2xl border-2 border-line bg-canvas px-4 text-lg text-ink outline-none transition-colors placeholder:text-ink-3 focus:border-accent"
          >

          <button type="submit" class="min-h-13 rounded-2xl bg-accent px-5 text-lg font-semibold text-accent-ink">
            {{ $t('meals.add') }}
          </button>
        </form>

        <template v-if="recipeSources.length > 0">
          <input
            v-model="search"
            type="search"
            :aria-label="$t('recipes.search')"
            :placeholder="$t('recipes.search')"
            class="mt-4 min-h-13 rounded-2xl border-2 border-line bg-canvas px-4 text-lg text-ink outline-none transition-colors placeholder:text-ink-3 focus:border-accent"
          >

          <ul class="mt-2 min-h-0 flex-1 overflow-y-auto">
            <li v-for="recipe in recipes" :key="recipe.id">
              <button
                type="button"
                data-testid="recipe"
                class="min-h-12 w-full rounded-2xl px-4 text-left text-lg transition-colors hover:bg-surface-2"
                @click="enter(recipe.name, recipe.id, recipe.provider)"
              >
                {{ recipe.name }}

                <!-- Where the recipe comes from — otherwise indistinguishable with several sources. -->
                <span v-if="recipeSources.length > 1" class="ml-2 text-base text-ink-3">
                  {{ recipe.provider_label }}
                </span>
              </button>
            </li>
          </ul>
        </template>

        <p v-else class="mt-4 text-base text-ink-3">
          {{ $t('meals.noSource') }}
        </p>

        <button
          type="button"
          class="mt-4 min-h-12 rounded-2xl text-lg font-medium text-ink-2 transition-colors hover:bg-surface-2"
          @click="dialog = null"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </div>

    <div
      v-if="eating"
      class="fixed inset-0 z-30 flex items-center justify-center bg-scrim p-6"
      @click.self="eating = null"
    >
      <div
        role="dialog"
        aria-modal="true"
        :aria-label="$t('meals.whoEats')"
        class="w-full max-w-md rounded-3xl bg-surface p-7 shadow-floating"
      >
        <h2 class="font-display text-2xl font-semibold">
          {{ $t('meals.whoEats') }}
        </h2>
        <p class="mt-1 text-base text-ink-3">
          {{ $t('meals.portionsHint') }}
        </p>

        <div class="mt-5 flex flex-wrap gap-2">
          <button
            v-for="person in people"
            :key="person.id"
            type="button"
            :aria-pressed="chosen.includes(person.id)"
            class="flex min-h-12 items-center gap-2 rounded-2xl border-2 px-4 text-lg font-medium transition-colors"
            :class="chosen.includes(person.id) ? 'border-accent bg-accent-soft text-ink' : 'border-line text-ink-3'"
            @click="toggleEater(person.id)"
          >
            <span class="size-3 rounded-full" :style="{ backgroundColor: person.color ?? undefined }" />
            {{ person.name }}
          </button>
        </div>

        <div class="mt-6 flex gap-2">
          <button
            type="button"
            data-testid="meal-eaters-save"
            :disabled="chosen.length === 0"
            class="min-h-13 flex-1 rounded-2xl bg-accent px-5 text-lg font-semibold text-accent-ink disabled:opacity-50"
            @click="saveEaters"
          >
            {{ $t('common.save') }}
          </button>
          <button
            type="button"
            class="min-h-13 rounded-2xl px-5 text-lg font-medium text-ink-2 hover:bg-surface-2"
            @click="eating = null"
          >
            {{ $t('common.cancel') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
