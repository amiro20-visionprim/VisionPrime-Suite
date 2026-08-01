<script setup lang="ts">
export type BadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'

withDefaults(
  defineProps<{
    tone?: BadgeTone
  }>(),
  {
    tone: 'neutral',
  },
)

const toneClasses: Record<BadgeTone, string> = {
  neutral: 'bg-surface-muted text-ink',
  info: 'bg-info-50 text-info-600',
  success: 'bg-success-50 text-success-700',
  warning: 'bg-warning-50 text-warning-700',
  danger: 'bg-danger-50 text-danger-700',
}
</script>

<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
      toneClasses[tone],
    ]"
  >
    <slot />
  </span>
</template>
