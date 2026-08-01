<script setup lang="ts">
import { computed, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    id?: string
    label?: string
    hint?: string
    error?: string
    type?: string
    placeholder?: string
    required?: boolean
    disabled?: boolean
    autocomplete?: string
    dir?: 'rtl' | 'ltr' | 'auto'
  }>(),
  {
    modelValue: '',
    id: '',
    label: '',
    hint: '',
    error: '',
    placeholder: '',
    autocomplete: '',
    type: 'text',
    required: false,
    disabled: false,
    dir: 'auto',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const generatedId = useId()
const inputId = computed(() => props.id || `vp-input-${generatedId}`)
const describedBy = computed(() => {
  const ids = [
    props.hint ? `${inputId.value}-hint` : '',
    props.error ? `${inputId.value}-error` : '',
  ].filter(Boolean)
  return ids.length ? ids.join(' ') : undefined
})
</script>

<template>
  <div class="space-y-2">
    <label v-if="label" :for="inputId" class="text-ink-strong block text-sm font-medium">
      {{ label }}
      <span v-if="required" class="text-danger-600" aria-hidden="true">*</span>
    </label>
    <div class="relative">
      <span
        v-if="$slots.leading"
        class="text-ink-muted pointer-events-none absolute inset-y-0 start-3 flex items-center"
      >
        <slot name="leading" />
      </span>
      <input
        :id="inputId"
        v-bind="$attrs"
        :value="modelValue ?? ''"
        :type="type"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :dir="dir"
        :autocomplete="autocomplete"
        :aria-invalid="Boolean(error)"
        :aria-describedby="describedBy"
        :class="[
          'transition-ui rounded-ui bg-surface text-ink-strong placeholder:text-ink-muted focus:border-brand-600 disabled:bg-surface-muted min-h-11 w-full border px-3 text-sm focus:outline-none disabled:cursor-not-allowed',
          $slots.leading ? 'ps-10' : '',
          $slots.trailing ? 'pe-10' : '',
          error ? 'border-danger-600' : 'border-line hover:border-line-strong',
        ]"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <span
        v-if="$slots.trailing"
        class="text-ink-muted absolute inset-y-0 end-3 flex items-center"
      >
        <slot name="trailing" />
      </span>
    </div>
    <p v-if="error" :id="`${inputId}-error`" class="text-danger-600 text-sm" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="`${inputId}-hint`" class="text-ink-muted text-sm leading-6">
      {{ hint }}
    </p>
  </div>
</template>
