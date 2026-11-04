<script setup lang="ts">
import type { MealDto, MealKindDto } from '../composables/useMeals'
import { formatIsoDate, isoWeek } from '../utils/calendar'
import { weekDays, weekStart } from '../utils/mealWeek'
import { isOffline } from '../utils/network'

/*
 * Plans a recipe from the recipe itself: a week with `<` `>`, a button per
 * meal the household cooks, and what is planned there already — so nobody
 * puts a second dinner on Tuesday without seeing the first.
 */
interface Props {
  /** The recipe's name, for the heading. */
  name: string
  today: string
  busy?: boolean
  error?: string | null
}

const props: Props = withDefaults(defineProps<Props>(), { busy: false, error: null })

const emit = defineEmits<{
  close: []
  pick: [date: string, kind: MealDto['kind']]
}>()

useEscape(() => emit('close'))

const { t } = useI18n()

const offset = ref(0)
const monday = computed(() => weekStart(props.today, offset.value))
const dates = computed(() => weekDays(monday.value))

const planned = ref<MealDto[]>([])
const kinds = ref<MealKindDto[]>([])
const loadError = ref<string | null>(null)

watch(monday, async (from) => {
  if (isOffline()) {
    loadError.value = t('network.offline')

    return
  }

  try {
    const data = await $fetch<{ meals: MealDto[], kinds?: MealKindDto[] }>('/api/wall/meals', {
      params: { from, to: dates.value[6] },
      baseURL: '/',
      credentials: 'same-origin',
    })

    // Only the week still shown draws: a quick page on leaves answers behind.
    if (monday.value === from) {
      planned.value = data.meals
      kinds.value = data.kinds ?? [{ value: 'lunch', label: t('meals.lunch') }, { value: 'dinner', label: t('meals.dinner') }]
      loadError.value = null
    }
  }
  catch {
    loadError.value = t('meals.loadFailed')
  }
}, { immediate: true })

const weekTitle = computed(() => {
  if (offset.value === 0) {
    return t('meals.week.this')
  }

  if (offset.value === 1) {
    return t('meals.week.next')
  }

  return offset.value === -1 ? t('meals.week.last') : t('meals.week.other', { n: isoWeek(monday.value) })
})

function titles(iso: string, kind: MealDto['kind']): string {
  return planned.value
    .filter(entry => entry.date === iso && entry.kind === kind)
    .map(entry => entry.title)
    .join(', ')
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-end justify-center bg-scrim sm:items-center sm:p-6" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="meal-slot-title"
      data-testid="meal-slot-picker"
      class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-t-3xl bg-surface p-5 shadow-floating sm:rounded-3xl sm:p-7"
    >
      <h2 id="meal-slot-title" class="font-display text-2xl font-semibold break-words">
        {{ $t('meals.planTitle', { name: props.name }) }}
      </h2>
      <p class="mt-1 text-base text-ink-3">
        {{ $t('meals.pick') }}
      </p>

      <div class="mt-4 flex items-center gap-1 self-start rounded-2xl bg-surface-2 p-1">
        <button
          type="button"
          data-testid="slot-week-back"
          :aria-label="$t('meals.week.back')"
          :disabled="offset <= 0"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2 transition-colors enabled:hover:bg-surface disabled:opacity-35"
          @click="offset--"
        >
          <TwIcon name="back" :size="20" />
        </button>

        <span data-testid="slot-week-title" class="min-w-36 text-center text-base font-semibold">{{ weekTitle }}</span>

        <button
          type="button"
          data-testid="slot-week-forward"
          :aria-label="$t('meals.week.forward')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2 transition-colors hover:bg-surface"
          @click="offset++"
        >
          <TwIcon name="forward" :size="20" />
        </button>
      </div>

      <p v-if="loadError || props.error" role="alert" class="mt-3 text-base font-medium text-danger">
        {{ props.error ?? loadError }}
      </p>

      <ul class="mt-3 min-h-0 flex-1 space-y-2 overflow-y-auto">
        <!-- Past days of the week stay out: a recipe is planned ahead. -->
        <li
          v-for="iso in dates.filter(day => day >= props.today)"
          :key="iso"
          data-testid="slot-day"
          :data-date="iso"
          class="rounded-2xl bg-surface-2/70 p-3"
        >
          <p class="mb-2 text-base font-semibold capitalize">
            {{ iso === props.today ? $t('common.today') : formatIsoDate(iso, { weekday: 'long', day: 'numeric', month: 'long' }) }}
          </p>

          <div class="grid gap-2" :class="kinds.length > 1 ? 'grid-cols-2' : 'grid-cols-1'">
            <button
              v-for="kind in kinds"
              :key="kind.value"
              type="button"
              data-testid="slot"
              :data-kind="kind.value"
              :disabled="props.busy"
              class="flex min-h-12 min-w-0 flex-col items-start justify-center rounded-xl border-2 border-line bg-surface px-3 py-1.5 text-left transition-colors enabled:hover:border-accent disabled:opacity-50"
              @click="emit('pick', iso, kind.value)"
            >
              <span class="text-base font-semibold">{{ kind.label }}</span>
              <span v-if="titles(iso, kind.value)" class="w-full truncate text-sm text-ink-3">{{ titles(iso, kind.value) }}</span>
            </button>
          </div>
        </li>
      </ul>

      <button
        type="button"
        class="mt-4 min-h-12 rounded-2xl text-lg font-medium text-ink-2 transition-colors hover:bg-surface-2"
        @click="emit('close')"
      >
        {{ $t('common.cancel') }}
      </button>
    </div>
  </div>
</template>
