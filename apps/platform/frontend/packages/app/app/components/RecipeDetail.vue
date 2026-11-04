<script setup lang="ts">
import type { Ingredient, Recipe } from '~/composables/useRecipes'

interface Props {
  recipe: Recipe
  /** The card's name, for sources that only deliver ingredients. */
  name: string
  loading: boolean
  /** Opened from the meal plan: it is planned already, and planning again is not the point. */
  planned?: boolean
  /** A line above the buttons: what the last one did, or why it failed. */
  notice?: string | null
  noticeFailed?: boolean
  /** The ingredients are on their way to the list. */
  shopping?: boolean
}

const props: Props = withDefaults(defineProps<Props>(), { planned: false, notice: null, noticeFailed: false, shopping: false })

const emit = defineEmits<{
  'close': []
  'edit': []
  'servings': [count: number]
  'to-list': []
  'plan': []
  'favorite': []
}>()

const { can } = useActor()
useEscape(() => emit('close'))

/* Cooking with flour on the fingers: the screen should not go dark mid-recipe. */
let wakeLock: WakeLockSentinel | null = null

onMounted(async () => {
  try {
    wakeLock = await navigator.wakeLock?.request('screen') ?? null
  }
  catch {
    wakeLock = null
  }
})

onUnmounted(() => {
  void wakeLock?.release()
})

const title = computed(() => props.recipe.title ?? props.name)

const pages = computed(() => props.recipe.pages ?? [])
/* A photographed card says it all: no empty ingredient list beneath it. */
const photographedOnly = computed(() => pages.value.length > 0 && props.recipe.ingredients.length === 0 && !props.recipe.steps)

/** Ingredients under their group headings, in the recipe's order. */
const groups = computed(() => {
  const list: { name: string | null, items: Ingredient[] }[] = []

  for (const ingredient of props.recipe.ingredients) {
    const last = list.at(-1)

    if (last && last.name === ingredient.group) {
      last.items.push(ingredient)
    }
    else {
      list.push({ name: ingredient.group, items: [ingredient] })
    }
  }

  return list
})

const { t } = useI18n()

/* CC BY-SA asks for the authors and the licence, the photo's included. */
const credit = computed(() => {
  const license = props.recipe.license

  if (!license) {
    return ''
  }

  const photo = license.photo_author
    ? t('recipes.photoBy', { author: [license.photo_author, license.photo_license].filter(Boolean).join(', ') })
    : null

  return [license.author, license.text, photo].filter(Boolean).join(' · ')
})
</script>

<template>
  <div
    class="fixed inset-0 z-40 flex items-end justify-center bg-ink/40 sm:items-center sm:p-6"
    @click.self="emit('close')"
  >
    <div
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      class="flex max-h-[92%] w-full max-w-4xl flex-col overflow-hidden rounded-t-[2rem] bg-canvas sm:max-h-full sm:rounded-3xl"
    >
      <div class="min-h-0 flex-1 overflow-y-auto p-6">
        <img
          v-if="recipe.photo"
          :src="recipe.photo.large"
          alt=""
          referrerpolicy="no-referrer"
          class="mb-5 aspect-[16/9] w-full rounded-2xl bg-surface-2 object-cover"
        >

        <div class="flex items-start justify-between gap-3">
          <h2 class="text-2xl font-semibold text-ink">
            {{ title }}
          </h2>
          <button
            type="button"
            data-testid="recipe-favorite"
            :aria-pressed="recipe.favorite"
            :aria-label="$t('recipes.favorite')"
            class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-full transition-colors"
            :class="recipe.favorite ? 'bg-accent text-accent-ink' : 'bg-surface text-ink-2'"
            @click="emit('favorite')"
          >
            <TwIcon name="heart" :size="22" />
          </button>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-base text-ink-2">
          <span v-if="recipe.minutes" class="flex items-center gap-1.5 rounded-full bg-surface px-3 py-1">
            <TwIcon name="clock" :size="18" />
            {{ $t('recipes.minutes', { count: recipe.minutes }) }}
          </span>
          <span v-if="recipe.difficulty" class="rounded-full bg-surface px-3 py-1">
            {{ $t(`recipes.difficulty.${recipe.difficulty}`) }}
          </span>
          <span v-if="recipe.vegetarian" class="flex items-center gap-1.5 rounded-full bg-surface px-3 py-1">
            <TwIcon name="vegetarian" :size="18" />
            {{ $t('recipes.vegetarian') }}
          </span>
          <span
            v-for="category in recipe.categories ?? []"
            :key="category"
            class="rounded-full bg-surface px-3 py-1"
          >
            {{ category }}
          </span>
        </div>

        <TwMarkdown v-if="recipe.intro" :source="recipe.intro" class="mt-4" />

        <section v-if="pages.length > 0" :aria-label="$t('recipes.pages')" class="mt-6 space-y-4" data-testid="recipe-page-list">
          <img v-for="page in pages" :key="page.id" :src="page.url" alt="" class="w-full rounded-2xl bg-surface-2 object-contain">
        </section>

        <div v-if="!photographedOnly" class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
          <section :aria-label="$t('recipes.ingredients')">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-xl font-semibold text-ink">
                {{ $t('recipes.ingredients') }}
              </h3>

              <div v-if="recipe.servings" class="flex items-center gap-1" data-testid="recipe-servings">
                <button
                  type="button"
                  :aria-label="$t('recipes.fewer')"
                  :disabled="loading || recipe.servings <= 1"
                  class="flex size-11 items-center justify-center rounded-full bg-surface text-xl text-ink disabled:opacity-40"
                  @click="emit('servings', recipe.servings - 1)"
                >
                  −
                </button>
                <span class="flex min-w-24 items-center justify-center gap-1.5 text-base text-ink-2">
                  <TwIcon name="servings" :size="18" />
                  {{ $t('recipes.servings', recipe.servings) }}
                </span>
                <button
                  type="button"
                  :aria-label="$t('recipes.more')"
                  :disabled="loading || recipe.servings >= 60"
                  class="flex size-11 items-center justify-center rounded-full bg-surface text-xl text-ink disabled:opacity-40"
                  @click="emit('servings', recipe.servings + 1)"
                >
                  +
                </button>
              </div>
            </div>

            <template v-for="(group, index) in groups" :key="`${group.name}-${index}`">
              <h4 v-if="group.name" class="mt-4 text-base font-semibold text-ink-2">
                {{ group.name }}
              </h4>
              <ul class="mt-2">
                <li
                  v-for="(ingredient, position) in group.items"
                  :key="`${ingredient.name}-${position}`"
                  class="flex min-h-11 items-baseline gap-3 border-b border-line py-2 text-lg last:border-0"
                >
                  <span class="w-24 shrink-0 text-right font-medium text-ink">{{ ingredient.spec }}</span>
                  <span class="text-ink">
                    {{ ingredient.name }}<span v-if="ingredient.note" class="text-ink-3">, {{ ingredient.note }}</span>
                  </span>
                </li>
              </ul>
            </template>

            <p v-if="recipe.ingredients.length === 0" class="py-4 text-lg text-ink-3">
              {{ $t('recipes.noIngredients') }}
            </p>
          </section>

          <section v-if="recipe.steps" :aria-label="$t('recipes.preparation')">
            <h3 class="text-xl font-semibold text-ink">
              {{ $t('recipes.preparation') }}
            </h3>
            <TwMarkdown :source="recipe.steps" steps class="mt-3" />
          </section>
        </div>

        <p v-if="recipe.source_url || credit" class="mt-8 text-sm text-ink-3" data-testid="recipe-credit">
          {{ recipe.provider_label }}<template v-if="credit">
            · {{ credit }}
          </template>
          <template v-if="recipe.source_url">
            ·
            <a
              :href="recipe.source_url"
              target="_blank"
              rel="noopener noreferrer nofollow"
              class="underline underline-offset-2"
            >{{ $t('recipes.source') }}</a>
          </template>
          <template v-if="recipe.license?.url">
            ·
            <a
              :href="recipe.license.url"
              target="_blank"
              rel="noopener noreferrer nofollow"
              class="underline underline-offset-2"
            >{{ $t('recipes.license') }}</a>
          </template>
        </p>
      </div>

      <p
        v-if="props.notice"
        data-testid="recipe-notice"
        :role="props.noticeFailed ? 'alert' : 'status'"
        class="shrink-0 border-t border-line px-5 py-3 text-base font-medium"
        :class="props.noticeFailed ? 'bg-warning text-warning-ink' : 'bg-success-soft text-success'"
      >
        {{ props.notice }}
      </p>

      <div class="flex shrink-0 flex-wrap gap-2 border-t border-line p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
        <button
          v-if="recipe.ingredients.length > 0 && can('lists.edit')"
          type="button"
          data-testid="ingredients-to-list"
          class="inline-flex min-h-13 flex-1 items-center justify-center gap-2 rounded-2xl bg-accent px-5 text-lg font-semibold text-accent-ink"
          :disabled="props.shopping"
          @click="emit('to-list')"
        >
          <TwPending :busy="props.shopping ?? false" />
          {{ $t('recipes.toList') }}
        </button>

        <button
          v-if="!props.planned && can('meals.edit')"
          type="button"
          data-testid="recipe-plan"
          class="flex min-h-13 items-center gap-2 rounded-2xl bg-surface px-5 text-lg font-medium text-ink"
          @click="emit('plan')"
        >
          <TwIcon name="meals" :size="20" />
          {{ $t('meals.plan') }}
        </button>

        <button
          v-if="!props.planned && recipe.provider === 'builtin' && can('recipes.edit')"
          type="button"
          data-testid="recipe-edit"
          class="flex min-h-13 items-center gap-2 rounded-2xl bg-surface px-5 text-lg font-medium text-ink"
          @click="emit('edit')"
        >
          <TwIcon name="pencil" :size="20" />
          {{ $t('recipes.edit') }}
        </button>

        <button
          type="button"
          class="min-h-13 rounded-2xl px-5 text-lg font-medium text-ink-2 transition-colors hover:bg-surface-2"
          @click="emit('close')"
        >
          {{ $t('common.close') }}
        </button>
      </div>
    </div>
  </div>
</template>
