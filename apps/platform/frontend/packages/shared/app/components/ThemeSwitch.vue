<script setup lang="ts">
import { intlLocale } from '../utils/intlLocale'
import type { Choice } from '../utils/theme'

const { t } = useI18n()

/*
 * The household's time zone: the wall may run in UTC and would otherwise
 * show "Dunkel ab 18:00" instead of 20:00.
 */
const props = defineProps<{ timeZone?: string }>()

const { state, choose } = useTheme()

const options = computed((): { value: Choice, label: string, icon: 'auto' | 'sun' | 'moon' }[] => [
  { value: 'auto', label: t('theme.auto'), icon: 'auto' },
  { value: 'light', label: t('theme.light'), icon: 'sun' },
  { value: 'dark', label: t('theme.dark'), icon: 'moon' },
])

const unsaved = ref(false)

async function set(choice: Choice): Promise<void> {
  unsaved.value = !await choose(choice)
}

const hint = computed(() => {
  if (unsaved.value) {
    return t('theme.unsaved')
  }

  const snapshot = state.value

  if (!snapshot) {
    return null
  }

  if (snapshot.preference !== 'auto' || !snapshot.next_change) {
    return t('theme.stays')
  }

  const clockTime = new Intl.DateTimeFormat(intlLocale.value, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: props.timeZone,
  }).format(new Date(snapshot.next_change))

  const next = snapshot.theme === 'dark' ? 'light' : 'dark'

  return t(snapshot.mode === 'sun' ? `theme.next.${next}.sun` : `theme.next.${next}.clock`, { time: clockTime })
})
</script>

<template>
  <!-- A container, not the screen, decides: in a narrow card "Automatisch"
       and its icon do not fit side by side, so the icon goes on top. -->
  <div class="@container">
    <div
      role="radiogroup"
      :aria-label="$t('theme.appearance')"
      class="grid grid-cols-3 gap-1 rounded-2xl bg-surface-2 p-1"
    >
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        role="radio"
        data-testid="theme-choice"
        :aria-checked="state?.preference === option.value"
        class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-xl px-2 py-2 text-base @sm:flex-row @sm:gap-1.5 font-medium whitespace-nowrap transition-colors"
        :class="state?.preference === option.value
          ? 'bg-surface text-ink shadow-card'
          : 'text-ink-2 hover:text-ink'"
        @click="set(option.value)"
      >
        <TwIcon :name="option.icon" :size="20" class="shrink-0" />
        {{ option.label }}
      </button>
    </div>

    <p class="mt-2 min-h-5 text-sm text-ink-3" aria-live="polite">
      {{ hint }}
    </p>
  </div>
</template>
