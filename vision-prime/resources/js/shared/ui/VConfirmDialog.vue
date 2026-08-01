<script setup lang="ts">
import VButton from './VButton.vue'
import VModal from './VModal.vue'

withDefaults(
  defineProps<{
    modelValue: boolean
    title: string
    description: string
    confirmLabel?: string
    cancelLabel?: string
    loading?: boolean
    tone?: 'danger' | 'primary'
  }>(),
  {
    confirmLabel: 'تأیید',
    cancelLabel: 'انصراف',
    loading: false,
    tone: 'primary',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
}>()
</script>

<template>
  <VModal
    :model-value="modelValue"
    :title="title"
    :close-on-backdrop="!loading"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <p class="text-ink leading-7">{{ description }}</p>
    <template #footer>
      <div class="flex flex-wrap justify-end gap-3">
        <VButton
          variant="secondary"
          :disabled="loading"
          @click="emit('update:modelValue', false)"
          >{{ cancelLabel }}</VButton
        >
        <VButton
          :variant="tone === 'danger' ? 'danger' : 'primary'"
          :loading="loading"
          @click="emit('confirm')"
          >{{ confirmLabel }}</VButton
        >
      </div>
    </template>
  </VModal>
</template>
