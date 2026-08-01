<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    closeOnBackdrop?: boolean
    closeOnEscape?: boolean
    size?: 'sm' | 'md' | 'lg'
  }>(),
  {
    title: '',
    closeOnBackdrop: true,
    closeOnEscape: true,
    size: 'md',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const panel = ref<HTMLElement | null>(null)
const sizeClasses = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl' }

function close(): void {
  emit('update:modelValue', false)
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.closeOnEscape) {
    close()
    return
  }

  if (event.key !== 'Tab' || !panel.value) return

  const focusable = [
    ...panel.value.querySelectorAll<HTMLElement>(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ]
  if (!focusable.length) {
    event.preventDefault()
    return
  }

  const first = focusable[0]
  const last = focusable.at(-1)
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      document.addEventListener('keydown', handleKeydown)
      await nextTick()
      panel.value
        ?.querySelector<HTMLElement>('[data-autofocus], button, input, select, textarea')
        ?.focus()
    } else {
      document.removeEventListener('keydown', handleKeydown)
    }
  },
)

onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <button
        type="button"
        class="bg-brand-900/30 absolute inset-0"
        aria-label="بستن پنجره"
        @click="closeOnBackdrop && close()"
      />
      <section
        ref="panel"
        :class="[
          'rounded-panel bg-surface shadow-panel relative z-10 max-h-[calc(100vh-2rem)] w-full overflow-auto',
          sizeClasses[size],
        ]"
        role="dialog"
        aria-modal="true"
        :aria-label="title || 'پنجره گفتگو'"
      >
        <header
          v-if="title || $slots.header"
          class="border-line flex items-start justify-between gap-4 border-b p-5"
        >
          <slot name="header"
            ><h2 class="text-ink-strong text-lg font-bold">{{ title }}</h2></slot
          >
          <button
            type="button"
            class="transition-ui rounded-ui text-ink-muted hover:bg-surface-muted hover:text-ink-strong -m-1 p-1"
            aria-label="بستن"
            @click="close"
          >
            ×
          </button>
        </header>
        <div class="p-5"><slot /></div>
        <footer v-if="$slots.footer" class="border-line border-t p-5">
          <slot name="footer" />
        </footer>
      </section>
    </div>
  </Teleport>
</template>
