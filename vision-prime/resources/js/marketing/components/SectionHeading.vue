<script setup lang="ts">
withDefaults(
  defineProps<{
    eyebrow?: string
    title: string
    description?: string
    centered?: boolean
  }>(),
  {
    eyebrow: '',
    description: '',
    centered: false,
  },
)
</script>

<template>
  <div :class="centered ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'">
    <p v-if="eyebrow" class="text-brand-700 text-sm font-bold tracking-wide">{{ eyebrow }}</p>
    <h2 class="text-section-title font-display text-ink-strong mt-3 font-bold sm:text-3xl">
      {{ title }}
    </h2>
    <p v-if="description" class="text-ink-muted mt-4 leading-8">{{ description }}</p>
  </div>
</template>
