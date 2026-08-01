<script setup lang="ts">
import { computed, useId } from 'vue'

export interface SelectOption {
  label: string
  value: string
  disabled?: boolean
}

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    options: SelectOption[]
    id?: string
    label?: string
    hint?: string
    error?: string
    placeholder?: string
    required?: boolean
    disabled?: boolean
  }>(),
  {
    modelValue: '',
    id: '',
    label: '',
    hint: '',
    error: '',
    placeholder: 'یک گزینه را انتخاب کنید',
    required: false,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const generatedId = useId()
const selectId = computed(() => props.id || `vp-select-${generatedId}`)
const describedBy = computed(() => {
  const ids = [
    props.hint ? `${selectId.value}-hint` : '',
    props.error ? `${selectId.value}-error` : '',
  ].filter(Boolean)
  return ids.length ? ids.join(' ') : undefined
})
</script>

<template>
  <div class="space-y-2">
    <label v-if="label" :for="selectId" class="text-ink-strong block text-sm font-medium">
      {{ label }}
      <span v-if="required" class="text-danger-600" aria-hidden="true">*</span>
    </label>
    <select
      :id="selectId"
      v-bind="$attrs"
      :value="modelValue ?? ''"
      :required="required"
      :disabled="disabled"
      :aria-invalid="Boolean(error)"
      :aria-describedby="describedBy"
      :class="[
        'transition-ui rounded-ui bg-surface text-ink-strong focus:border-brand-600 disabled:bg-surface-muted min-h-11 w-full appearance-none border px-3 text-sm focus:outline-none disabled:cursor-not-allowed',
        error ? 'border-danger-600' : 'border-line hover:border-line-strong',
      ]"
      @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <option value="" disabled>{{ placeholder }}</option>
      <option
        v-for="option in options"
        :key="option.value"
        :value="option.value"
        :disabled="option.disabled"
      >
        {{ option.label }}
      </option>
    </select>
    <p v-if="error" :id="`${selectId}-error`" class="text-danger-600 text-sm" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="`${selectId}-hint`" class="text-ink-muted text-sm leading-6">
      {{ hint }}
    </p>
  </div>
</template>
