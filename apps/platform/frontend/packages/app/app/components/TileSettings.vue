<script setup lang="ts">
import type { SettingField } from '~/composables/useDashboard'

/*
 * The settings of a plugin tile, drawn from the fields the plugin declares.
 * The server checks what comes back; a secret is never shown, only whether
 * one is stored — left empty, the stored one stays.
 */
interface Props {
  title: string
  fields: SettingField[]
  settings: Record<string, unknown>
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  save: [Record<string, unknown>]
}>()

useEscape(() => emit('close'))

const values = reactive<Record<string, string | boolean>>(Object.fromEntries(props.fields.map((field) => {
  const stored = props.settings[field.key]

  if (field.kind === 'toggle') {
    return [field.key, stored === true]
  }

  return [field.key, field.kind === 'secret' || stored === null || stored === undefined ? '' : String(stored)]
})))

function save(): void {
  emit('save', { ...values })
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-scrim p-6" @click.self="emit('close')">
    <form
      role="dialog"
      aria-modal="true"
      aria-labelledby="tile-settings-title"
      data-testid="tile-settings"
      class="max-h-full w-full max-w-lg overflow-y-auto rounded-3xl bg-surface p-6 shadow-floating"
      @submit.prevent="save"
    >
      <h2 id="tile-settings-title" class="font-display text-2xl font-semibold">
        {{ props.title }}
      </h2>

      <div class="mt-4 flex flex-col gap-4">
        <template v-for="field in props.fields" :key="field.key">
          <label v-if="field.kind === 'toggle'" class="flex min-h-11 items-center justify-between gap-3 text-base">
            <span>{{ field.label }}</span>
            <input v-model="values[field.key]" type="checkbox" role="switch" :data-field="field.key" class="size-6 accent-accent">
          </label>

          <label v-else class="flex flex-col gap-1.5 text-base">
            <span class="font-semibold">
              {{ field.label }}<span v-if="field.required" aria-hidden="true"> *</span>
            </span>

            <select
              v-if="field.kind === 'select'"
              v-model="values[field.key]"
              :data-field="field.key"
              :required="field.required"
              class="min-h-11 rounded-xl border border-line bg-surface-2 px-3"
            >
              <option v-if="!field.required" value="">
                –
              </option>
              <option v-for="option in field.options" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>

            <input
              v-else
              v-model="values[field.key]"
              :data-field="field.key"
              :type="field.kind === 'secret' ? 'password' : field.kind === 'number' ? 'number' : 'text'"
              :required="field.required && !(field.kind === 'secret' && props.settings[field.key] === true)"
              :autocomplete="field.kind === 'secret' ? 'off' : undefined"
              :placeholder="field.kind === 'secret' && props.settings[field.key] === true ? $t('dashboard.secretKept') : undefined"
              class="min-h-11 rounded-xl border border-line bg-surface-2 px-3"
            >
          </label>
        </template>
      </div>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" class="min-h-11 rounded-xl px-4 text-base font-semibold text-ink-2" @click="emit('close')">
          {{ $t('common.cancel') }}
        </button>
        <button
          type="submit"
          data-testid="tile-settings-save"
          class="min-h-11 rounded-xl bg-accent px-5 text-base font-semibold text-accent-ink"
        >
          {{ $t('common.save') }}
        </button>
      </div>
    </form>
  </div>
</template>
