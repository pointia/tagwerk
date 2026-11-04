<script setup lang="ts">
import type { MealDto } from '../../../shared/app/composables/useMeals'

/*
 * A planned meal's recipe, opened from the meal plan: the cooking view. The
 * ingredients go to the list from here — whoever opens the instructions is
 * about to cook or to shop for it.
 */
interface Props {
  meal: MealDto
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{ close: [] }>()

const { recipe, loading, error: recipeError, hint: recipeHint, open, toTheList, toggleFavorite } = useRecipes()
const { ingredientsToList, error: mealError, hint: mealHint } = useMeals()

const source = computed(() => ({ provider: props.meal.recipe_provider ?? '', id: props.meal.recipe_id ?? '' }))

/* The amounts start at the eaters' portions, rounded — a recipe counts whole servings. */
const planned = Math.max(1, Math.round(props.meal.servings))
const servings = ref(planned)

const notice = ref<string | null>(null)
const failed = ref(false)

async function show(count: number): Promise<void> {
  servings.value = count
  await open(source.value, count)
}

const { busy: shopping, run } = useBusy()

/*
 * At the planned portions the meal's own path buys exactly for its eaters;
 * once someone changed the portions here, the amounts on screen count.
 */
async function toList(): Promise<void> {
  if (servings.value === planned) {
    await ingredientsToList(props.meal)
    notice.value = mealError.value ?? mealHint.value
    failed.value = mealError.value !== null
  }
  else {
    await toTheList(source.value, servings.value)
    notice.value = recipeError.value ?? recipeHint.value
    failed.value = recipeError.value !== null
  }
}

onMounted(() => {
  recipe.value = null
  void show(planned)
})

/* Without the recipe (a source that is down) the escape still closes. */
useEscape(() => emit('close'), () => recipe.value === null)
</script>

<template>
  <RecipeDetail
    v-if="recipe"
    :recipe="recipe"
    :name="meal.title"
    :loading="loading"
    planned
    :notice="notice"
    :notice-failed="failed"
    @close="emit('close')"
    @servings="show"
    :shopping="shopping"
    @to-list="run(toList)"
    @favorite="toggleFavorite({ ...recipe, name: meal.title })"
  />

  <div
    v-else
    data-testid="planned-recipe-loading"
    class="fixed inset-0 z-40 flex items-center justify-center bg-ink/40 p-6"
    @click.self="emit('close')"
  >
    <p v-if="recipeError" role="alert" class="rounded-3xl bg-surface px-6 py-5 text-lg font-medium text-ink shadow-floating">
      {{ recipeError }}
    </p>
    <TwSpinner v-else :size="40" class="text-surface" />
  </div>
</template>
