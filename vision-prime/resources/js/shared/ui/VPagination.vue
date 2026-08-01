<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: number
    totalPages: number
    ariaLabel?: string
  }>(),
  {
    ariaLabel: 'صفحه‌بندی',
  },
)

const emit = defineEmits<{
  'update:modelValue': [page: number]
}>()

const pages = computed<(number | 'ellipsis')[]>(() => {
  if (props.totalPages <= 7)
    return Array.from({ length: props.totalPages }, (_, index) => index + 1)

  const current = Math.min(Math.max(props.modelValue, 1), props.totalPages)
  if (current <= 4) return [1, 2, 3, 4, 5, 'ellipsis', props.totalPages]
  if (current >= props.totalPages - 3)
    return [
      1,
      'ellipsis',
      props.totalPages - 4,
      props.totalPages - 3,
      props.totalPages - 2,
      props.totalPages - 1,
      props.totalPages,
    ]

  return [1, 'ellipsis', current - 1, current, current + 1, 'ellipsis', props.totalPages]
})

function setPage(page: number): void {
  if (page >= 1 && page <= props.totalPages && page !== props.modelValue)
    emit('update:modelValue', page)
}
</script>

<template>
  <nav
    v-if="totalPages > 1"
    :aria-label="ariaLabel"
    class="flex items-center justify-between gap-3"
    dir="rtl"
  >
    <button
      type="button"
      class="transition-ui rounded-ui border-line text-ink hover:bg-surface-muted min-h-9 border px-3 text-sm disabled:cursor-not-allowed disabled:opacity-45"
      :disabled="modelValue <= 1"
      @click="setPage(modelValue - 1)"
    >
      بعدی
    </button>
    <ol class="flex items-center gap-1">
      <li v-for="(page, index) in pages" :key="`${page}-${index}`">
        <span
          v-if="page === 'ellipsis'"
          class="text-ink-muted inline-flex min-h-9 min-w-9 items-center justify-center"
          aria-hidden="true"
          >…</span
        >
        <button
          v-else
          type="button"
          :aria-current="page === modelValue ? 'page' : undefined"
          :class="[
            'transition-ui rounded-ui min-h-9 min-w-9 px-2 text-sm font-medium',
            page === modelValue ? 'bg-brand-700 text-white' : 'text-ink hover:bg-surface-muted',
          ]"
          @click="setPage(page)"
        >
          {{ page }}
        </button>
      </li>
    </ol>
    <button
      type="button"
      class="transition-ui rounded-ui border-line text-ink hover:bg-surface-muted min-h-9 border px-3 text-sm disabled:cursor-not-allowed disabled:opacity-45"
      :disabled="modelValue >= totalPages"
      @click="setPage(modelValue + 1)"
    >
      قبلی
    </button>
  </nav>
</template>
