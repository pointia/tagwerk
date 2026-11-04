<script setup lang="ts">
/**
 * Extra stars for one person, always with a reason — or stars a sibling
 * gives them.
 *
 * The reason is required because it lands in the history: a child reading
 * "+3" a week later should know what for. A gift's reason the server writes
 * itself ("Geschenk von Lena"). Saving happens in the calling view — on the
 * wall the PIN has already been asked for before this opens.
 */
interface Giver {
  id: number
  name: string
  balance: number
}

interface Props {
  name: string
  /** The current stars; unknown (undefined) leaves the check to the server. */
  balance?: number
  /** Who could give stars instead; without them the dialog offers extra stars only. */
  givers?: Giver[]
  busy: boolean
  error: string | null
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  save: [{ points: number, reason: string }]
  gift: [{ from: number, points: number, note: string }]
}>()

useEscape(() => emit('close'))

/*
 * Minus one only, and never below zero — the server holds the same rule.
 * Why, and what to do instead, the info beside it explains to the parents.
 */
const STEPS = [1, 2, 3, 5, -1]

const empty = computed(() => props.balance !== undefined && props.balance <= 0)
const explained = ref(false)

const points = ref(1)
const reason = ref('')

const mode = ref<'bonus' | 'gift'>('bonus')
const canGift = computed(() => (props.givers ?? []).length > 0)

// A gift goes up to everything the giver has, never further — the server holds the same rule.
const giverId = ref<number | null>(null)
const giver = computed(() => props.givers?.find(person => person.id === giverId.value) ?? null)
const amount = ref(1)
const note = ref('')

function chooseGiver(person: Giver): void {
  giverId.value = person.id
  amount.value = Math.min(Math.max(amount.value, 1), person.balance)
}

const valid = computed(() => mode.value === 'gift'
  ? giver.value !== null && amount.value >= 1 && amount.value <= giver.value.balance
  : reason.value.trim() !== '')

function save(): void {
  if (!valid.value || props.busy) {
    return
  }

  if (mode.value === 'gift' && giver.value !== null) {
    emit('gift', { from: giver.value.id, points: amount.value, note: note.value.trim() })
  }
  else {
    emit('save', { points: points.value, reason: reason.value.trim() })
  }
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-scrim p-3 sm:p-6" @click.self="emit('close')">
    <form
      role="dialog"
      aria-modal="true"
      aria-labelledby="bonus-title"
      data-testid="bonus-dialog"
      class="max-h-full w-full max-w-lg overflow-y-auto rounded-3xl bg-surface p-5 shadow-floating sm:p-8"
      @submit.prevent="save"
    >
      <div class="flex items-start justify-between gap-4">
        <h2 id="bonus-title" class="font-display text-2xl font-semibold sm:text-3xl">
          {{ mode === 'gift' ? $t('points.gift.title', { name: props.name }) : $t('points.bonusFor', { name: props.name }) }}
        </h2>

        <button
          type="button"
          :aria-label="$t('common.close')"
          class="-mt-1 -mr-2 flex size-12 items-center justify-center rounded-full text-ink-2 transition-colors hover:bg-surface-2"
          @click="emit('close')"
        >
          <TwIcon name="close" />
        </button>
      </div>

      <div v-if="canGift" role="group" :aria-label="$t('points.gift.kind')" class="mt-5 grid grid-cols-2 gap-1 rounded-2xl bg-surface-2 p-1">
        <button
          v-for="option in (['bonus', 'gift'] as const)"
          :key="option"
          type="button"
          data-testid="bonus-mode"
          :data-value="option"
          :aria-pressed="mode === option"
          class="min-h-12 rounded-xl px-3 text-base font-semibold transition-colors"
          :class="mode === option ? 'bg-surface text-ink' : 'text-ink-2'"
          @click="mode = option"
        >
          {{ option === 'gift' ? $t('points.gift.mode') : $t('points.gift.bonusMode') }}
        </button>
      </div>

      <template v-if="mode === 'gift'">
        <fieldset class="mt-5">
          <legend class="text-base font-semibold text-ink-2">
            {{ $t('points.gift.from') }}
          </legend>

          <div class="mt-2 flex flex-wrap gap-2">
            <button
              v-for="person in props.givers"
              :key="person.id"
              type="button"
              data-testid="gift-from"
              :aria-pressed="giverId === person.id"
              :disabled="person.balance <= 0"
              class="flex min-h-12 items-center gap-2 rounded-2xl px-4 text-lg font-semibold transition-colors disabled:opacity-35"
              :class="giverId === person.id ? 'bg-accent text-accent-ink' : 'bg-surface-2 text-ink-2'"
              @click="chooseGiver(person)"
            >
              {{ person.name }}
              <span class="flex items-center gap-1 tabular-nums">
                <TwStar />
                {{ person.balance }}
              </span>
            </button>
          </div>

          <p v-if="props.givers?.every(person => person.balance <= 0)" class="mt-2 text-base text-ink-3">
            {{ $t('points.gift.nobody') }}
          </p>
        </fieldset>

        <fieldset v-if="giver" class="mt-5">
          <legend class="text-base font-semibold text-ink-2">
            {{ $t('points.howMany') }}
          </legend>

          <div class="mt-2 flex items-center gap-2">
            <button
              type="button"
              data-testid="gift-less"
              :aria-label="$t('points.gift.less')"
              :disabled="amount <= 1"
              class="flex size-12 items-center justify-center rounded-full bg-surface-2 text-2xl text-ink disabled:opacity-35"
              @click="amount--"
            >
              −
            </button>
            <span data-testid="gift-count" class="flex min-w-20 items-center justify-center gap-1.5 text-2xl font-bold tabular-nums" aria-live="polite">
              <TwStar />
              {{ amount }}
            </span>
            <button
              type="button"
              data-testid="gift-more"
              :aria-label="$t('points.gift.more')"
              :disabled="amount >= giver.balance"
              class="flex size-12 items-center justify-center rounded-full bg-surface-2 text-2xl text-ink disabled:opacity-35"
              @click="amount++"
            >
              +
            </button>
            <button
              type="button"
              data-testid="gift-all"
              :disabled="amount === giver.balance"
              class="ml-1 min-h-12 rounded-2xl bg-surface-2 px-4 text-base font-semibold text-ink-2 disabled:opacity-35"
              @click="amount = giver.balance"
            >
              {{ $t('points.gift.all', { count: giver.balance }) }}
            </button>
          </div>
        </fieldset>

        <label class="mt-5 block">
          <span class="text-base font-semibold text-ink-2">{{ $t('points.gift.note') }}</span>
          <input
            v-model="note"
            maxlength="200"
            data-testid="gift-note"
            :placeholder="$t('points.gift.notePlaceholder')"
            class="mt-1.5 w-full rounded-2xl border-2 border-line bg-canvas px-4 py-3 text-lg text-ink outline-none transition-colors focus:border-accent"
          >
        </label>
      </template>

      <fieldset v-if="mode === 'bonus'" class="mt-5">
        <legend class="text-base font-semibold text-ink-2">
          {{ $t('points.howMany') }}
        </legend>

        <div class="mt-2 flex flex-wrap gap-2">
          <button
            v-for="step in STEPS"
            :key="step"
            type="button"
            data-testid="bonus-points"
            :aria-pressed="points === step"
            :disabled="step < 0 && empty"
            class="flex min-h-12 min-w-16 items-center justify-center gap-1.5 rounded-2xl px-4 text-lg font-bold tabular-nums transition-colors disabled:opacity-35"
            :class="points === step
              ? (step > 0 ? 'bg-points text-points-ink' : 'bg-ink-2 text-surface')
              : 'bg-surface-2 text-ink-2'"
            @click="points = step"
          >
            <TwStar />
            {{ step > 0 ? `+${step}` : step }}
          </button>

          <button
            type="button"
            data-testid="deduct-info"
            :aria-label="$t('points.deduct.why')"
            :aria-expanded="explained"
            aria-controls="deduct-info"
            class="flex size-12 items-center justify-center rounded-full transition-colors hover:bg-surface-2 hover:text-ink"
            :class="explained ? 'bg-surface-2 text-ink' : 'text-ink-3'"
            @click="explained = !explained"
          >
            <TwIcon name="info" :size="22" />
          </button>
        </div>

        <p v-if="empty" class="mt-2 text-base text-ink-3">
          {{ $t('points.deduct.empty', { name: props.name }) }}
        </p>
      </fieldset>

      <section
        v-if="mode === 'bonus' && explained"
        id="deduct-info"
        data-testid="deduct-explained"
        class="tw-fade-down mt-4 rounded-2xl bg-surface-2 px-5 py-4 text-base text-ink-2"
      >
        <h3 class="font-semibold text-ink">
          {{ $t('points.deduct.title') }}
        </h3>
        <ul class="mt-2 list-disc space-y-1.5 pl-5">
          <li>{{ $t('points.deduct.one') }}</li>
          <li>{{ $t('points.deduct.zero') }}</li>
          <li>{{ $t('points.deduct.praise') }}</li>
          <li>{{ $t('points.deduct.calm') }}</li>
        </ul>
        <h3 class="mt-4 font-semibold text-ink">
          {{ $t('points.deduct.moreTitle') }}
        </h3>
        <p class="mt-1.5">
          {{ $t('points.deduct.more') }}
        </p>
        <p class="mt-1.5">
          {{ $t('points.deduct.correction') }}
        </p>
      </section>

      <label v-if="mode === 'bonus'" class="mt-5 block">
        <span class="text-base font-semibold text-ink-2">{{ $t('points.whatFor') }}</span>
        <input
          v-model="reason"
          autofocus
          required
          maxlength="255"
          data-testid="bonus-reason"
          :placeholder="$t('points.whatForPlaceholder')"
          class="mt-1.5 w-full rounded-2xl border-2 border-line bg-canvas px-4 py-3 text-lg text-ink outline-none transition-colors focus:border-accent"
        >
      </label>

      <p
        v-if="props.error"
        role="alert"
        class="mt-5 rounded-2xl bg-danger-soft px-5 py-3 text-lg text-danger"
      >
        {{ props.error }}
      </p>

      <div class="mt-6 flex justify-end gap-2 sm:gap-3">
        <button
          type="button"
          class="min-h-13 rounded-2xl px-5 text-lg font-semibold text-ink-2 transition-colors hover:bg-surface-2"
          @click="emit('close')"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          type="submit"
          data-testid="bonus-save"
          :disabled="!valid || props.busy"
          class="inline-flex items-center justify-center gap-2 min-h-13 rounded-2xl bg-accent px-6 text-lg font-semibold text-accent-ink transition-opacity disabled:opacity-35"
        >
          <TwPending :busy="props.busy" />
          {{ mode === 'gift' ? $t('points.gift.save') : $t('points.give') }}
        </button>
      </div>
    </form>
  </div>
</template>
