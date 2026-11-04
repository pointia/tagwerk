<script setup lang="ts">
const emit = defineEmits<{
  close: []
  unlocked: []
}>()

useEscape(() => emit('close'))

const { unlock } = usePoints()

const input = ref('')
const wrong = ref(false)
const { busy, run } = useBusy()

function tap(digit: string): void {
  if (input.value.length < 12) {
    input.value += digit
    wrong.value = false
  }
}

function destroy(): void {
  input.value = input.value.slice(0, -1)
  wrong.value = false
}

async function confirm(): Promise<void> {
  if (await run(() => unlock(input.value))) {
    emit('unlocked')

    return
  }

  // The input is cleared, not just marked: otherwise you keep typing after a
  // wrong digit and never get back to four.
  wrong.value = true
  input.value = ''
}

/*
 * A keyboard types the same PIN: digits (number row or keypad), Backspace and
 * Enter. Escape stays with `useEscape`, whose stack closes only the topmost
 * popup. Handled keys are kept from the focused button — Enter on a digit
 * key would otherwise add that digit too.
 */
function onKey(event: KeyboardEvent): void {
  if (event.ctrlKey || event.metaKey || event.altKey) {
    return
  }

  if (/^[0-9]$/.test(event.key)) {
    tap(event.key)
  }
  else if (event.key === 'Backspace') {
    destroy()
  }
  else if (event.key === 'Enter') {
    if (input.value.length >= 4) {
      void confirm()
    }
  }
  else {
    return
  }

  event.preventDefault()
}

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim p-6" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="pin-title"
      class="w-full max-w-sm rounded-3xl bg-surface p-7 shadow-floating"
    >
      <h2 id="pin-title" class="font-display text-3xl font-semibold">
        {{ $t('pin.title') }}
      </h2>

      <p class="mt-1 text-base text-ink-2">
        {{ $t('pin.hint') }}
      </p>

      <!-- At least four slots: that way you see how much is still missing. -->
      <div
        data-testid="pin-display"
        class="mt-6 flex h-16 items-center justify-center gap-3 rounded-2xl border-2 transition-colors"
        :class="wrong ? 'border-danger bg-danger-soft' : 'border-line bg-canvas'"
        aria-hidden="true"
      >
        <span
          v-for="rank in Math.max(4, input.length)"
          :key="rank"
          class="size-4 rounded-full transition-colors"
          :class="rank <= input.length ? 'bg-ink' : 'bg-surface-3'"
        />
      </div>

      <p class="sr-only" aria-live="polite">
        {{ $t('pin.entered', input.length) }}
      </p>

      <p v-if="wrong" role="alert" class="mt-2 text-base font-medium text-danger">
        {{ $t('pin.wrong') }}
      </p>

      <div class="mt-5 grid grid-cols-3 gap-3">
        <button
          v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
          :key="digit"
          type="button"
          data-testid="pin-key"
          class="min-h-16 rounded-2xl bg-surface-2 text-3xl font-semibold tabular-nums transition-colors hover:bg-surface-3 active:bg-surface-3"
          @click="tap(digit)"
        >
          {{ digit }}
        </button>

        <button
          type="button"
          :aria-label="$t('pin.backspace')"
          class="flex min-h-16 items-center justify-center rounded-2xl bg-surface-2 text-ink-2 transition-colors hover:bg-surface-3"
          @click="destroy"
        >
          <TwIcon name="backspace" :size="28" />
        </button>

        <button
          type="button"
          data-testid="pin-key"
          class="min-h-16 rounded-2xl bg-surface-2 text-3xl font-semibold tabular-nums transition-colors hover:bg-surface-3 active:bg-surface-3"
          @click="tap('0')"
        >
          0
        </button>

        <button
          type="button"
          data-testid="pin-ok"
          class="flex min-h-16 items-center justify-center gap-2 rounded-2xl bg-accent text-xl font-bold text-accent-ink transition-opacity disabled:opacity-35"
          :disabled="input.length < 4 || busy"
          @click="confirm"
        >
          <TwPending :busy="busy" />
          OK
        </button>
      </div>

      <button
        type="button"
        class="mt-4 min-h-12 w-full rounded-2xl text-lg font-medium text-ink-2 transition-colors hover:bg-surface-2"
        @click="emit('close')"
      >
        {{ $t('common.cancel') }}
      </button>
    </div>
  </div>
</template>
