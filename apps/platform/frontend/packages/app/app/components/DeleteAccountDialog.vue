<script setup lang="ts">
/*
 * Deleting one's own account. Where it is the last manager, the household
 * goes too — named here, each confirmed by its typed name, with the export
 * offered first.
 */
const emit = defineEmits<{ close: [], deleted: [] }>()

const { t } = useI18n()
const { householdsLost, deleteAccount } = useAuth()
const { busy, run } = useBusy()

const lost = ref<{ id: number, name: string }[] | null>(null)
const password = ref('')
const typed = ref<Record<number, string>>({})
const error = ref<string | null>(null)

useEscape(() => emit('close'))

onMounted(async () => {
  try {
    lost.value = await householdsLost()
  }
  catch {
    error.value = t('common.failed')
  }
})

const ready = computed(() => lost.value !== null
  && password.value !== ''
  && lost.value.every(household => typed.value[household.id] === household.name))

async function submit(): Promise<void> {
  await run(async () => {
    error.value = await deleteAccount(password.value, Object.values(typed.value))

    if (error.value === null) {
      emit('deleted')
    }
  })
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-end justify-center bg-scrim sm:items-center sm:p-6" @click.self="emit('close')">
    <form
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-account-title"
      data-testid="delete-account"
      class="max-h-full w-full max-w-md overflow-y-auto rounded-t-3xl bg-surface p-6 shadow-floating sm:rounded-3xl"
      @submit.prevent="submit"
    >
      <h2 id="delete-account-title" class="font-display text-2xl font-semibold">
        {{ $t('account.deleteTitle') }}
      </h2>

      <TwLoading v-if="lost === null && error === null" class="mt-4" />

      <template v-else-if="lost !== null">
        <p v-if="lost.length === 0" class="mt-2 text-base text-ink-2">
          {{ $t('account.deleteKeeps') }}
        </p>

        <template v-else>
          <p class="mt-2 text-base text-ink-2">
            {{ $t('account.deleteHouseholds', { households: lost.map(household => household.name).join(', ') }) }}
          </p>
          <a href="/admin/export" class="mt-2 inline-flex min-h-11 items-center font-semibold underline">{{ $t('account.export') }}</a>

          <label v-for="household in lost" :key="household.id" class="mt-4 block">
            <span class="text-sm font-semibold">{{ $t('account.typeName', { name: household.name }) }}</span>
            <input
              v-model="typed[household.id]"
              type="text"
              autocomplete="off"
              data-testid="delete-account-name"
              class="mt-1 min-h-11 w-full rounded-xl border border-line bg-canvas px-3 text-base"
            >
          </label>
        </template>

        <label class="mt-4 block">
          <span class="text-sm font-semibold">{{ $t('account.password') }}</span>
          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            data-testid="delete-account-password"
            class="mt-1 min-h-11 w-full rounded-xl border border-line bg-canvas px-3 text-base"
          >
        </label>
      </template>

      <p v-if="error" role="alert" class="mt-3 text-sm text-danger">
        {{ error }}
      </p>

      <div class="mt-6 flex gap-3">
        <button type="button" class="min-h-11 flex-1 rounded-2xl bg-surface-2 px-4 font-semibold" @click="emit('close')">
          {{ $t('common.cancel') }}
        </button>
        <button
          type="submit"
          data-testid="delete-account-confirm"
          :disabled="!ready"
          class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-2xl bg-danger px-4 font-bold text-surface disabled:opacity-50"
        >
          {{ $t('account.delete') }}
          <TwPending :busy="busy" />
        </button>
      </div>
    </form>
  </div>
</template>
