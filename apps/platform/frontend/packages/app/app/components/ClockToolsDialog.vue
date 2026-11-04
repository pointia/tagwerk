<script setup lang="ts">
import type { ClockTool } from '~/composables/useClockTools'
import { adjustTimer, clockText, timerParts } from '~/utils/clockTools'

const { t } = useI18n()

/*
 * Stopwatch and timer from the clock tile. Big digits and big buttons: the
 * timer is set with floury fingers from two steps away.
 */
const {
  open,
  stopwatch,
  timer,
  ringing,
  elapsed,
  remaining,
  startStopwatch,
  stopStopwatch,
  resetStopwatch,
  setTimer,
  startTimer,
  pauseTimer,
  cancelTimer,
  close,
} = useClockTools()

useEscape(close)

const TABS = computed((): { value: ClockTool, label: string, icon: 'stopwatch' | 'timer' }[] => [
  { value: 'stopwatch', label: t('clock.stopwatch'), icon: 'stopwatch' },
  { value: 'timer', label: t('clock.timer'), icon: 'timer' },
])

/** Minutes offered at a tap; kitchen and homework both fit. */
const PRESETS = [1, 3, 5, 10, 15, 30]

const MINUTE = 60_000

const timerRunning = computed(() => timer.value.endsAt !== null)

// Set, but not started: the time can still be changed.
const timerIdle = computed(() => !timerRunning.value && !ringing.value)

const parts = computed(() => timerParts(timer.value.left))

function swipe(part: 'minutes' | 'seconds', steps: number): void {
  setTimer(adjustTimer(timer.value.left, part, steps))
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim p-6" @click.self="close">
    <div
      role="dialog"
      aria-modal="true"
      :aria-label="$t('clock.title')"
      data-testid="clock-tools"
      class="w-full max-w-lg rounded-3xl bg-surface p-7 shadow-floating"
    >
      <div class="flex items-center gap-3">
        <div role="tablist" :aria-label="$t('clock.tool')" class="flex flex-1 gap-1 rounded-2xl bg-surface-2 p-1">
          <button
            v-for="tab in TABS"
            :key="tab.value"
            type="button"
            role="tab"
            :data-testid="`clock-tab-${tab.value}`"
            :aria-selected="open === tab.value"
            class="flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl text-lg font-semibold transition-colors"
            :class="open === tab.value ? 'bg-surface text-ink shadow-card' : 'text-ink-2 hover:text-ink'"
            @click="open = tab.value"
          >
            <TwIcon :name="tab.icon" :size="22" />
            {{ tab.label }}
          </button>
        </div>

        <button
          type="button"
          :aria-label="$t('common.close')"
          class="flex size-12 items-center justify-center rounded-full text-ink-2 transition-colors hover:bg-surface-2"
          @click="close"
        >
          <TwIcon name="close" :size="22" />
        </button>
      </div>

      <template v-if="open === 'stopwatch'">
        <p
          data-testid="stopwatch-time"
          class="my-8 text-center font-display text-7xl font-semibold tabular-nums"
          aria-live="off"
        >
          {{ clockText(elapsed) }}
        </p>

        <div class="grid grid-cols-2 gap-3">
          <button
            v-if="stopwatch.startedAt === null"
            type="button"
            data-testid="stopwatch-start"
            class="min-h-14 rounded-2xl bg-accent text-lg font-semibold text-accent-ink"
            @click="startStopwatch"
          >
            {{ stopwatch.banked > 0 ? $t('clock.resume') : $t('clock.start') }}
          </button>
          <button
            v-else
            type="button"
            data-testid="stopwatch-stop"
            class="min-h-14 rounded-2xl bg-accent text-lg font-semibold text-accent-ink"
            @click="stopStopwatch"
          >
            {{ $t('clock.stop') }}
          </button>

          <button
            type="button"
            data-testid="stopwatch-reset"
            class="min-h-14 rounded-2xl border-2 border-line text-lg font-semibold text-ink disabled:opacity-40"
            :disabled="stopwatch.startedAt !== null || stopwatch.banked === 0"
            @click="resetStopwatch"
          >
            {{ $t('clock.reset') }}
          </button>
        </div>
      </template>

      <template v-else>
        <!-- While set, not running: minutes and seconds turn with a swipe. -->
        <p
          v-if="timerIdle"
          data-testid="timer-time"
          class="mt-10 mb-7 flex items-center justify-center font-display text-7xl font-semibold tabular-nums"
        >
          <SwipeNumber
            data-testid="timer-minutes"
            :label="$t('clock.minutes')"
            :value="parts.minutes"
            :max="99"
            @step="(steps: number) => swipe('minutes', steps)"
          />
          <span aria-hidden="true">:</span>
          <SwipeNumber
            data-testid="timer-seconds"
            :label="$t('clock.seconds')"
            :value="parts.seconds"
            :max="59"
            @step="(steps: number) => swipe('seconds', steps)"
          />
        </p>
        <p
          v-else
          data-testid="timer-time"
          class="mt-8 text-center font-display text-7xl font-semibold tabular-nums"
          :class="ringing ? 'text-danger' : ''"
        >
          {{ clockText(remaining, 'up') }}
        </p>

        <p v-if="timerIdle" class="text-center text-sm text-ink-3">
          {{ $t('clock.swipeHint') }}
        </p>

        <p v-if="ringing" role="alert" class="mt-2 text-center text-2xl font-semibold text-danger">
          {{ $t('clock.timeUp') }}
        </p>

        <div v-if="timerIdle" class="mt-6 grid grid-cols-3 gap-2" role="group" :aria-label="$t('clock.pickMinutes')">
          <button
            v-for="minutes in PRESETS"
            :key="minutes"
            type="button"
            data-testid="timer-preset"
            class="min-h-12 rounded-2xl bg-surface-2 text-lg font-semibold text-ink-2 transition-colors hover:text-ink"
            @click="setTimer(minutes * MINUTE)"
          >
            {{ minutes }} min
          </button>
          <button
            type="button"
            :aria-label="$t('clock.minuteLess')"
            class="min-h-12 rounded-2xl bg-surface-2 text-lg font-semibold text-ink-2 disabled:opacity-40"
            :disabled="timer.left < MINUTE"
            @click="setTimer(timer.left - MINUTE)"
          >
            − 1
          </button>
          <span class="flex items-center justify-center text-sm text-ink-3">{{ $t('clock.minute') }}</span>
          <button
            type="button"
            :aria-label="$t('clock.minuteMore')"
            class="min-h-12 rounded-2xl bg-surface-2 text-lg font-semibold text-ink-2"
            @click="setTimer(timer.left + MINUTE)"
          >
            + 1
          </button>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3">
          <button
            v-if="ringing"
            type="button"
            data-testid="timer-dismiss"
            class="col-span-2 min-h-14 rounded-2xl bg-danger text-lg font-semibold text-surface"
            @click="cancelTimer"
          >
            {{ $t('common.off') }}
          </button>

          <template v-else>
            <button
              v-if="timerRunning"
              type="button"
              data-testid="timer-pause"
              class="min-h-14 rounded-2xl bg-accent text-lg font-semibold text-accent-ink"
              @click="pauseTimer"
            >
              {{ $t('clock.pause') }}
            </button>
            <button
              v-else
              type="button"
              data-testid="timer-start"
              class="min-h-14 rounded-2xl bg-accent text-lg font-semibold text-accent-ink disabled:opacity-40"
              :disabled="timer.left <= 0"
              @click="startTimer"
            >
              {{ $t('clock.start') }}
            </button>

            <button
              type="button"
              data-testid="timer-cancel"
              class="min-h-14 rounded-2xl border-2 border-line text-lg font-semibold text-ink disabled:opacity-40"
              :disabled="timer.left <= 0 && !timerRunning"
              @click="cancelTimer"
            >
              {{ $t('common.cancel') }}
            </button>
          </template>
        </div>
      </template>
    </div>
  </div>
</template>
