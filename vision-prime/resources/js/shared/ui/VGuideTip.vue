<script setup lang="ts">
import { ref } from 'vue'
import { Lightbulb } from '@lucide/vue'

withDefaults(
  defineProps<{
    text: string
    /** نمایش فقط آیکون بدون دایرهٔ پس‌زمینه (مناسب هدر کارت‌ها) */
    bare?: boolean
  }>(),
  {
    bare: false,
  },
)

const open = ref(false)
</script>

<template>
  <span
    class="group relative inline-flex"
    @mouseenter="open = true"
    @mouseleave="open = false"
    @focusin="open = true"
    @focusout="open = false"
  >
    <button
      type="button"
      :class="[
        'text-warning-600 hover:bg-warning-50 focus:outline-none',
        bare ? 'rounded-full p-0.5' : 'rounded-full p-1',
      ]"
      :aria-label="'راهنما: ' + text"
      @click.stop="open = !open"
    >
      <Lightbulb class="size-4" aria-hidden="true" />
    </button>
    <span
      role="tooltip"
      :class="[
        'rounded-ui bg-ink-strong pointer-events-none absolute z-40 w-max max-w-60 rounded-lg px-3 py-2 text-xs leading-6 text-white shadow-lg transition-all duration-150',
        open ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-1 opacity-0',
        bare ? 'top-full end-0 mt-1.5' : 'top-full end-0 mt-1',
      ]"
    >
      {{ text }}
    </span>
  </span>
</template>
