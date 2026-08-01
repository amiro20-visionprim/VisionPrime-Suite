<script setup lang="ts">
export type StatusTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'

withDefaults(
  defineProps<{
    label: string
    tone?: StatusTone
  }>(),
  {
    tone: 'neutral',
  },
)

const toneClasses: Record<StatusTone, string> = {
  neutral: 'bg-ink-muted',
  info: 'bg-info-600',
  success: 'bg-success-600',
  warning: 'bg-warning-600',
  danger: 'bg-danger-600',
}
</script>

<template>
  <span class="text-ink inline-flex items-center gap-2 text-sm">
    <span :class="['size-2 shrink-0 rounded-full', toneClasses[tone]]" aria-hidden="true" />
    <span>{{ label }}</span>
  </span>
</template>
