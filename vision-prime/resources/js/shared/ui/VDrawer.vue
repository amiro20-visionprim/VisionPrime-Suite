<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    side?: 'start' | 'end'
    closeOnBackdrop?: boolean
  }>(),
  {
    title: '',
    side: 'start',
    closeOnBackdrop: true,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const panel = ref<HTMLElement | null>(null)

function close(): void {
  emit('update:modelValue', false)
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') close()
}

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      document.addEventListener('keydown', handleKeydown)
      await nextTick()
      panel.value?.querySelector<HTMLElement>('[data-autofocus], button, a, input')?.focus()
    } else document.removeEventListener('keydown', handleKeydown)
  },
)

onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50">
      <button
        type="button"
        class="bg-brand-900/30 absolute inset-0"
        aria-label="بستن پنل"
        @click="closeOnBackdrop && close()"
      />
      <section
        ref="panel"
        :class="[
          'bg-surface shadow-panel absolute inset-y-0 z-10 flex w-full max-w-sm flex-col',
          side === 'start' ? 'start-0' : 'end-0',
        ]"
        role="dialog"
        aria-modal="true"
        :aria-label="title || 'پنل کناری'"
      >
        <header class="border-line flex items-center justify-between gap-3 border-b p-5">
          <h2 class="text-ink-strong font-bold">{{ title }}</h2>
          <button
            type="button"
            class="transition-ui rounded-ui text-ink-muted hover:bg-surface-muted -m-1 p-1"
            aria-label="بستن"
            @click="close"
          >
            ×
          </button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto p-5"><slot /></div>
        <footer v-if="$slots.footer" class="border-line border-t p-5">
          <slot name="footer" />
        </footer>
      </section>
    </div>
  </Teleport>
</template>
