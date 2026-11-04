<script setup lang="ts">
/*
 * A number set by swiping: up for more, down for less, like the wheel of a
 * kitchen timer. Arrow keys do the same for anyone without a finger on it.
 */
const props = defineProps<{ value: number, label: string, max: number }>()

const emit = defineEmits<{ step: [steps: number] }>()

/** Finger travel per step: short enough to reach 30 minutes in one swipe. */
const STEP_PX = 20

let lastY: number | null = null

function down(event: PointerEvent): void {
  lastY = event.clientY
  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
}

function move(event: PointerEvent): void {
  if (lastY === null) {
    return
  }

  const steps = Math.trunc((lastY - event.clientY) / STEP_PX)

  if (steps !== 0) {
    lastY -= steps * STEP_PX
    emit('step', steps)
  }
}

function up(): void {
  lastY = null
}

function key(event: KeyboardEvent): void {
  const steps = { ArrowUp: 1, ArrowDown: -1 }[event.key]

  if (steps !== undefined) {
    event.preventDefault()
    emit('step', steps)
  }
}
</script>

<template>
  <span
    role="spinbutton"
    tabindex="0"
    :aria-label="props.label"
    :aria-valuenow="props.value"
    aria-valuemin="0"
    :aria-valuemax="props.max"
    class="relative cursor-ns-resize touch-none rounded-2xl px-2 transition-colors select-none hover:bg-surface-2 focus-visible:bg-surface-2 focus-visible:outline-none"
    @pointerdown="down"
    @pointermove="move"
    @pointerup="up"
    @pointercancel="up"
    @keydown="key"
  >
    <!-- One line on purpose: whitespace around the digits would read "05 : 00". -->
    <TwIcon name="up" :size="20" class="absolute -top-5 left-1/2 -translate-x-1/2 text-ink-3" aria-hidden="true" />{{ String(props.value).padStart(2, '0') }}<TwIcon name="down" :size="20" class="absolute -bottom-5 left-1/2 -translate-x-1/2 text-ink-3" aria-hidden="true" />
  </span>
</template>
