<script setup lang="ts">
import VCard from '@/shared/ui/VCard.vue'
import VStatusDot, { type StatusTone } from '@/shared/ui/VStatusDot.vue'

withDefaults(
  defineProps<{
    title: string
    value: string
    description: string
    statusLabel?: string
    statusTone?: StatusTone
  }>(),
  {
    statusLabel: '',
    statusTone: 'neutral',
  },
)
</script>

<template>
  <VCard :title="title"
    ><div class="flex items-end justify-between gap-3">
      <p class="text-ink-strong text-2xl font-bold">{{ value }}</p>
      <VStatusDot v-if="statusLabel" :label="statusLabel" :tone="statusTone" />
    </div>
    <p class="text-ink-muted mt-3 text-sm leading-6">{{ description }}</p></VCard
  >
</template>
