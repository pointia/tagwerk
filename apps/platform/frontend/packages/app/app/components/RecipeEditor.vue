<script setup lang="ts">
import type { Recipe, RecipeBookEntry, RecipeInput } from '~/composables/useRecipes'

interface Props {
  /** Null creates a new recipe. */
  recipe: Recipe | null
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  saved: [recipe: Recipe]
  removed: []
}>()

const { t } = useI18n()
const { save, fromLink, remove } = useRecipes()
const { armed, tap } = useTwoStep()
useEscape(() => emit('close'))

const form = reactive<RecipeInput>({
  title: props.recipe?.title ?? '',
  intro: props.recipe?.intro ?? '',
  steps: props.recipe?.steps ?? '',
  servings: props.recipe?.base_servings ?? 4,
  minutes: props.recipe?.minutes ?? null,
  difficulty: props.recipe?.difficulty ?? null,
  vegetarian: props.recipe?.vegetarian ?? false,
  categories: [...(props.recipe?.categories ?? [])],
  ingredient_text: props.recipe?.ingredient_text ?? '',
})

const categoryText = ref(form.categories.join(', '))
const photo = ref<File | null>(null)
const dropPhoto = ref(false)
/* Photos of the recipe itself: kept ones from the server, new ones still local. */
const keptPages = ref((props.recipe?.pages ?? []).map(page => page.id))
const newPages = ref<{ file: File, url: string }[]>([])
const MAX_PAGES = 8
const pageRoom = computed(() => MAX_PAGES - keptPages.value.length - newPages.value.length)
const busy = ref(false)
const problem = ref<string | null>(null)

const difficulties: NonNullable<RecipeBookEntry['difficulty']>[] = ['easy', 'medium', 'hard']

const preview = computed(() => photo.value ? URL.createObjectURL(photo.value) : null)
watch(preview, (_, old) => {
  if (old) {
    URL.revokeObjectURL(old)
  }
})

function pick(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null

  photo.value = file
  dropPhoto.value = false
}

function addPages(event: Event): void {
  const input = event.target as HTMLInputElement
  const files = [...(input.files ?? [])].slice(0, Math.max(0, pageRoom.value))

  newPages.value.push(...files.map(file => ({ file, url: URL.createObjectURL(file) })))
  input.value = ''
}

function dropNewPage(index: number): void {
  const [page] = newPages.value.splice(index, 1)

  if (page) {
    URL.revokeObjectURL(page.url)
  }
}

onUnmounted(() => newPages.value.forEach(page => URL.revokeObjectURL(page.url)))

async function submit(): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true
  problem.value = null

  try {
    const saved = await save(props.recipe?.id ?? null, {
      ...form,
      title: form.title.trim(),
      categories: categoryText.value.split(',').map(category => category.trim()).filter(Boolean),
    }, photo.value, dropPhoto.value, {
      add: newPages.value.map(page => page.file),
      drop: (props.recipe?.pages ?? []).map(page => page.id).filter(id => !keptPages.value.includes(id)),
    })

    emit('saved', saved)
  }
  catch (error: unknown) {
    problem.value = error instanceof Error ? error.message : t('recipes.saveFailed')
  }
  finally {
    busy.value = false
  }
}

const link = ref('')

/* Saved straight away: the detail opens, and "Bearbeiten" corrects what the page got wrong. */
async function importLink(): Promise<void> {
  if (busy.value || link.value.trim() === '') {
    return
  }

  busy.value = true
  problem.value = null

  try {
    emit('saved', await fromLink(link.value.trim()))
  }
  catch (error: unknown) {
    problem.value = error instanceof Error ? error.message : t('recipes.linkFailed')
  }
  finally {
    busy.value = false
  }
}

async function destroy(): Promise<void> {
  if (props.recipe) {
    await remove(props.recipe.id)
    emit('removed')
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-40 flex items-end justify-center bg-ink/40 sm:items-center sm:p-6"
    @click.self="emit('close')"
  >
    <form
      role="dialog"
      aria-modal="true"
      :aria-label="recipe ? $t('recipes.editTitle') : $t('recipes.newTitle')"
      class="flex max-h-[92%] w-full max-w-3xl flex-col overflow-hidden rounded-t-[2rem] bg-canvas sm:max-h-full sm:rounded-3xl"
      data-testid="recipe-editor"
      @submit.prevent="submit"
    >
      <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-6">
        <h2 class="text-2xl font-semibold text-ink">
          {{ recipe ? $t('recipes.editTitle') : $t('recipes.newTitle') }}
        </h2>

        <div v-if="!recipe" class="rounded-2xl bg-surface p-4">
          <label for="recipe-link" class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.fromLink') }}</label>
          <div class="flex gap-2">
            <input
              id="recipe-link"
              v-model="link"
              type="url"
              inputmode="url"
              data-testid="recipe-link"
              :placeholder="$t('recipes.linkPlaceholder')"
              class="min-h-12 min-w-0 flex-1 rounded-2xl border-2 border-line bg-canvas px-4 text-base text-ink outline-none focus:border-accent"
              @keydown.enter.prevent="importLink"
            >
            <button
              type="button"
              data-testid="recipe-link-import"
              :disabled="busy || link.trim() === ''"
              class="min-h-12 shrink-0 rounded-2xl bg-accent px-4 text-base font-medium text-accent-ink disabled:opacity-50"
              @click="importLink"
            >
              {{ $t('recipes.linkImport') }}
            </button>
          </div>
          <p class="mt-2 text-sm text-ink-3">
            {{ $t('recipes.linkHint') }}
          </p>
        </div>

        <label class="block">
          <span class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.title') }}</span>
          <input
            v-model="form.title"
            required
            maxlength="255"
            data-testid="recipe-title"
            class="min-h-13 w-full rounded-2xl border-2 border-line bg-canvas px-4 text-lg text-ink outline-none focus:border-accent"
          >
        </label>

        <div class="flex flex-wrap items-center gap-4">
          <img
            v-if="preview || (recipe?.photo && !dropPhoto)"
            :src="preview ?? recipe?.photo?.small"
            alt=""
            class="size-24 rounded-2xl object-cover"
          >
          <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-2xl bg-surface px-4 text-base font-medium text-ink">
            <TwIcon name="photo" :size="20" />
            {{ $t('recipes.photo') }}
            <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" data-testid="recipe-photo" @change="pick">
          </label>
          <button
            v-if="recipe?.photo && !dropPhoto && !photo"
            type="button"
            class="min-h-12 rounded-2xl px-4 text-base text-ink-2 hover:bg-surface-2"
            @click="dropPhoto = true"
          >
            {{ $t('recipes.photoRemove') }}
          </button>
        </div>

        <div class="rounded-2xl bg-surface p-4" data-testid="recipe-pages">
          <span class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.pages') }}</span>
          <p class="mb-3 text-sm text-ink-3">
            {{ $t('recipes.pagesHint') }}
          </p>
          <div class="flex flex-wrap items-center gap-3">
            <div v-for="page in (recipe?.pages ?? []).filter(page => keptPages.includes(page.id))" :key="page.id" class="relative">
              <img :src="page.url" alt="" class="size-24 rounded-xl object-cover">
              <button
                type="button"
                :aria-label="$t('recipes.pageRemove')"
                class="absolute -top-2 -right-2 flex size-11 items-center justify-center rounded-full bg-canvas text-ink-2 shadow"
                @click="keptPages = keptPages.filter(id => id !== page.id)"
              >
                <TwIcon name="close" :size="18" />
              </button>
            </div>
            <div v-for="(page, index) in newPages" :key="page.url" class="relative">
              <img :src="page.url" alt="" class="size-24 rounded-xl object-cover">
              <button
                type="button"
                :aria-label="$t('recipes.pageRemove')"
                class="absolute -top-2 -right-2 flex size-11 items-center justify-center rounded-full bg-canvas text-ink-2 shadow"
                @click="dropNewPage(index)"
              >
                <TwIcon name="close" :size="18" />
              </button>
            </div>
            <label
              v-if="pageRoom > 0"
              class="flex min-h-12 cursor-pointer items-center gap-2 rounded-2xl bg-canvas px-4 text-base font-medium text-ink"
            >
              <TwIcon name="photo" :size="20" />
              {{ $t('recipes.pageAdd') }}
              <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-testid="recipe-page-input" @change="addPages">
            </label>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <label class="block">
            <span class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.servingsLabel') }}</span>
            <input
              v-model.number="form.servings"
              type="number"
              min="1"
              max="50"
              required
              class="min-h-12 w-full rounded-2xl border-2 border-line bg-canvas px-3 text-lg text-ink outline-none focus:border-accent"
            >
          </label>
          <label class="block">
            <span class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.minutesLabel') }}</span>
            <input
              v-model.number="form.minutes"
              type="number"
              min="1"
              max="2880"
              class="min-h-12 w-full rounded-2xl border-2 border-line bg-canvas px-3 text-lg text-ink outline-none focus:border-accent"
            >
          </label>
          <fieldset class="col-span-2">
            <legend class="mb-1 text-base font-medium text-ink-2">
              {{ $t('recipes.difficultyLabel') }}
            </legend>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="level in difficulties"
                :key="level"
                type="button"
                :aria-pressed="form.difficulty === level"
                class="min-h-12 rounded-2xl px-4 text-base font-medium transition-colors"
                :class="form.difficulty === level ? 'bg-accent text-accent-ink' : 'bg-surface text-ink'"
                @click="form.difficulty = form.difficulty === level ? null : level"
              >
                {{ $t(`recipes.difficulty.${level}`) }}
              </button>
            </div>
          </fieldset>
        </div>

        <label class="flex min-h-12 items-center gap-3">
          <input v-model="form.vegetarian" type="checkbox" class="size-6 accent-accent">
          <span class="text-lg text-ink">{{ $t('recipes.vegetarian') }}</span>
        </label>

        <label class="block">
          <span class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.categories') }}</span>
          <input
            v-model="categoryText"
            :placeholder="$t('recipes.categoriesHint')"
            class="min-h-12 w-full rounded-2xl border-2 border-line bg-canvas px-4 text-lg text-ink outline-none placeholder:text-ink-3 focus:border-accent"
          >
        </label>

        <label class="block">
          <span class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.ingredients') }}</span>
          <textarea
            v-model="form.ingredient_text"
            rows="8"
            data-testid="recipe-ingredients"
            :placeholder="$t('recipes.ingredientsHint')"
            class="block w-full rounded-2xl border-2 border-line bg-canvas px-4 py-3 text-lg text-ink outline-none placeholder:text-ink-3 focus:border-accent"
          />
        </label>

        <div>
          <span class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.intro') }}</span>
          <TwMarkdownEditor v-model="form.intro" :label="$t('recipes.intro')" :rows="3" />
        </div>

        <div>
          <span class="mb-1 block text-base font-medium text-ink-2">{{ $t('recipes.preparation') }}</span>
          <TwMarkdownEditor v-model="form.steps" :label="$t('recipes.preparation')" :rows="8" testid="recipe-steps" />
          <p class="mt-1 text-base text-ink-3">
            {{ $t('recipes.stepsHint') }}
          </p>
        </div>

        <p v-if="problem" role="alert" class="rounded-2xl bg-warning px-4 py-3 text-base font-medium text-warning-ink">
          {{ problem }}
        </p>
      </div>

      <div class="flex shrink-0 flex-wrap gap-2 border-t border-line p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
        <button
          type="submit"
          :disabled="busy"
          data-testid="recipe-save"
          class="inline-flex items-center justify-center gap-2 min-h-13 flex-1 rounded-2xl bg-accent px-5 text-lg font-semibold text-accent-ink disabled:opacity-60"
        >
          <TwPending :busy="busy" />
          {{ $t('common.save') }}
        </button>

        <button
          v-if="recipe"
          type="button"
          :aria-label="armed === 'recipe' ? $t('common.deleteConfirm') : $t('common.delete')"
          class="flex min-h-13 items-center gap-2 rounded-2xl px-4 text-lg font-medium transition-colors"
          :class="armed === 'recipe' ? 'bg-danger-soft text-danger' : 'text-ink-2 hover:bg-surface-2'"
          @click="tap('recipe', destroy)"
        >
          <TwIcon name="delete" :size="20" />
          <span v-if="armed === 'recipe'">{{ $t('common.deleteConfirm') }}</span>
        </button>

        <button
          type="button"
          class="min-h-13 rounded-2xl px-5 text-lg font-medium text-ink-2 transition-colors hover:bg-surface-2"
          @click="emit('close')"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </form>
  </div>
</template>
