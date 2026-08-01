<script setup lang="ts">
import { computed, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    id?: string
    label?: string
    hint?: string
    error?: string
    placeholder?: string
    required?: boolean
    disabled?: boolean
    rows?: number
    dir?: 'rtl' | 'ltr' | 'auto'
  }>(),
  {
    modelValue: '',
    id: '',
    label: '',
    hint: '',
    error: '',
    placeholder: '',
    required: false,
    disabled: false,
    rows: 4,
    dir: 'auto',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const generatedId = useId()
const textareaId = computed(() => props.id || `vp-textarea-${generatedId}`)
const describedBy = computed(() => {
  const ids = [
    props.hint ? `${textareaId.value}-hint` : '',
    props.error ? `${textareaId.value}-error` : '',
  ].filter(Boolean)
  return ids.length ? ids.join(' ') : undefined
})
</script>

<template>
  <div class="space-y-2">
    <label v-if="label" :for="textareaId" class="text-ink-strong block text-sm font-medium">
      {{ label }}
      <span v-if="required" class="text-danger-600" aria-hidden="true">*</span>
    </label>
    <textarea
      :id="textareaId"
      v-bind="$attrs"
      :value="modelValue ?? ''"
      :rows="rows"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :dir="dir"
      :aria-invalid="Boolean(error)"
      :aria-describedby="describedBy"
      :class="[
        'transition-ui rounded-ui bg-surface text-ink-strong placeholder:text-ink-muted focus:border-brand-600 disabled:bg-surface-muted w-full resize-y border px-3 py-2.5 text-sm leading-6 focus:outline-none disabled:cursor-not-allowed',
        error ? 'border-danger-600' : 'border-line hover:border-line-strong',
      ]"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <p v-if="error" :id="`${textareaId}-error`" class="text-danger-600 text-sm" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="`${textareaId}-hint`" class="text-ink-muted text-sm leading-6">
      {{ hint }}
    </p>
  </div>
</template>
