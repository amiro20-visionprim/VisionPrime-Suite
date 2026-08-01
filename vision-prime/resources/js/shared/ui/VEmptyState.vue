<script setup lang="ts">
import VButton from './VButton.vue'

withDefaults(
  defineProps<{
    title: string
    description: string
    actionLabel?: string
  }>(),
  {
    actionLabel: '',
  },
)

const emit = defineEmits<{
  action: []
}>()
</script>

<template>
  <section
    class="rounded-panel border-line-strong bg-surface border border-dashed p-8 text-center sm:p-12"
  >
    <div
      v-if="$slots.icon"
      class="rounded-card bg-brand-50 text-brand-700 mx-auto flex size-12 items-center justify-center"
    >
      <slot name="icon" />
    </div>
    <h2 class="text-ink-strong mt-4 text-lg font-bold">{{ title }}</h2>
    <p class="text-ink-muted mx-auto mt-2 max-w-md leading-7">{{ description }}</p>
    <div v-if="actionLabel || $slots.action" class="mt-5">
      <slot name="action"
        ><VButton @click="emit('action')">{{ actionLabel }}</VButton></slot
      >
    </div>
  </section>
</template>
