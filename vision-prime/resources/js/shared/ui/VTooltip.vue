<script setup lang="ts">
defineProps<{
  text: string
}>()
</script>

<template>
  <span class="group relative inline-flex">
    <slot />
    <span
      role="tooltip"
      class="rounded-ui bg-brand-900 shadow-card pointer-events-none absolute start-1/2 bottom-full z-30 mb-2 w-max max-w-56 -translate-x-1/2 px-2.5 py-1.5 text-center text-xs leading-5 text-white opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100"
    >
      {{ text }}
    </span>
  </span>
</template>
