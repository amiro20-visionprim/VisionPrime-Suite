<script setup lang="ts">
export type AlertTone = 'info' | 'success' | 'warning' | 'danger'

withDefaults(
  defineProps<{
    tone?: AlertTone
    title?: string
    dismissible?: boolean
  }>(),
  {
    tone: 'info',
    title: undefined,
    dismissible: false,
  },
)

const emit = defineEmits<{
  dismiss: []
}>()

const toneClasses: Record<AlertTone, string> = {
  info: 'border-info-600/20 bg-info-50 text-info-600',
  success: 'border-success-600/20 bg-success-50 text-success-700',
  warning: 'border-warning-600/20 bg-warning-50 text-warning-700',
  danger: 'border-danger-600/20 bg-danger-50 text-danger-700',
}
</script>

<template>
  <section :class="['rounded-card flex gap-3 border p-4', toneClasses[tone]]" role="alert">
    <div class="min-w-0 flex-1">
      <h2 v-if="title" class="text-sm font-bold">{{ title }}</h2>
      <div :class="title ? 'mt-1 text-sm leading-6' : 'text-sm leading-6'"><slot /></div>
    </div>
    <button
      v-if="dismissible"
      type="button"
      class="transition-ui rounded-ui -m-1 p-1 hover:bg-white/50 focus:outline-none"
      aria-label="بستن پیام"
      @click="emit('dismiss')"
    >
      <span aria-hidden="true">×</span>
    </button>
  </section>
</template>
