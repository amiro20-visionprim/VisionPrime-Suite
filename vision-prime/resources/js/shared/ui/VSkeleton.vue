<script setup lang="ts">
withDefaults(
  defineProps<{
    width?: string
    height?: string
    rounded?: 'sm' | 'md' | 'full'
  }>(),
  {
    width: '100%',
    height: '1rem',
    rounded: 'md',
  },
)

const roundedClasses = { sm: 'rounded-ui', md: 'rounded-card', full: 'rounded-full' }
</script>

<template>
  <span
    :class="['bg-brand-100 block animate-pulse', roundedClasses[rounded]]"
    :style="{ width, height }"
    aria-hidden="true"
  />
</template>
