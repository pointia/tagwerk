<script setup lang="ts">
import { moved } from '../utils/menu'
import type { IconName } from './TwIcon.vue'

/*
 * Sort and hide the main menu. Arrows instead of dragging: on a tablet in the
 * hallway a tap hits more reliably than a drag, and a phone scrolls while you
 * drag.
 */
interface Entry {
  value: string
  label: string
  icon: IconName
}

interface Props {
  /** In the current order, hidden ones included. */
  entries: Entry[]
  hidden: string[]
  /** The start area: hiding it would leave the device in an invisible area. */
  locked: string
}

// Annotated on purpose: in the apps' type check the macro's result is `any`
// for components that live in this layer.
const props: Props = defineProps<Props>()

const emit = defineEmits<{ order: [string[]], hidden: [string[]] }>()

const keys = computed(() => props.entries.map(entry => entry.value))
const shownCount = computed(() => keys.value.filter(key => !props.hidden.includes(key)).length)

function shift(key: string, step: -1 | 1): void {
  emit('order', moved(keys.value, key, step))
}

/** At least one stays visible, and never the start area goes. */
function canHide(key: string): boolean {
  return key !== props.locked && shownCount.value > 1
}

function flip(key: string): void {
  if (props.hidden.includes(key)) {
    emit('hidden', props.hidden.filter(entry => entry !== key))
  }
  else if (canHide(key)) {
    emit('hidden', [...props.hidden, key])
  }
}
</script>

<template>
  <ul class="space-y-1.5" data-testid="menu-editor">
    <li
      v-for="(entry, index) in entries"
      :key="entry.value"
      data-testid="menu-entry"
      :data-value="entry.value"
      class="flex min-h-12 items-center gap-3 rounded-2xl bg-surface-2 py-1 pr-2 pl-4"
    >
      <TwIcon :name="entry.icon" :size="20" :class="hidden.includes(entry.value) ? 'text-ink-3' : 'text-ink-2'" />

      <span class="min-w-0 flex-1 truncate text-base font-semibold" :class="hidden.includes(entry.value) ? 'text-ink-3' : 'text-ink'">
        {{ entry.label }}
      </span>

      <button
        type="button"
        :aria-label="$t('menu.up', { area: entry.label })"
        class="flex size-11 items-center justify-center rounded-full text-ink-2 disabled:opacity-30"
        :disabled="index === 0"
        @click="shift(entry.value, -1)"
      >
        <TwIcon name="back" :size="20" class="rotate-90" />
      </button>

      <button
        type="button"
        :aria-label="$t('menu.down', { area: entry.label })"
        class="flex size-11 items-center justify-center rounded-full text-ink-2 disabled:opacity-30"
        :disabled="index === entries.length - 1"
        @click="shift(entry.value, 1)"
      >
        <TwIcon name="forward" :size="20" class="rotate-90" />
      </button>

      <button
        type="button"
        role="switch"
        data-testid="menu-visible"
        :aria-checked="!hidden.includes(entry.value)"
        :aria-label="$t('menu.show', { area: entry.label })"
        :disabled="!hidden.includes(entry.value) && !canHide(entry.value)"
        class="flex min-h-11 items-center px-1 disabled:opacity-40"
        @click="flip(entry.value)"
      >
        <span
          class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
          :class="hidden.includes(entry.value) ? 'bg-surface-3' : 'bg-accent'"
          aria-hidden="true"
        >
          <span
            class="absolute top-1 size-5 rounded-full bg-surface shadow transition-all"
            :class="hidden.includes(entry.value) ? 'left-1' : 'left-6'"
          />
        </span>
      </button>
    </li>
  </ul>
</template>
