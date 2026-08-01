<script setup lang="ts">
withDefaults(
  defineProps<{
    eyebrow?: string
    title: string
    description: string
  }>(),
  {
    eyebrow: 'VISION PRIME',
  },
)
</script>

<template>
  <section class="border-line bg-canvas border-b">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10 lg:py-24">
      <p class="text-brand-700 text-sm font-bold tracking-wide">{{ eyebrow }}</p>
      <h1 class="font-display text-display text-ink-strong mt-4 max-w-3xl font-bold sm:text-5xl">
        {{ title }}
      </h1>
      <p class="text-ink-muted mt-5 max-w-2xl text-base leading-8 sm:text-lg">{{ description }}</p>
      <div v-if="$slots.actions" class="mt-8 flex flex-wrap gap-3"><slot name="actions" /></div>
    </div>
  </section>
</template>
