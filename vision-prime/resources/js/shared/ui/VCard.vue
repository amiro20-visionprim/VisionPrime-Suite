<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    description?: string
    padded?: boolean
  }>(),
  {
    title: '',
    description: '',
    padded: true,
  },
)
</script>

<template>
  <section class="rounded-card border-line bg-surface shadow-card border">
    <header
      v-if="title || description || $slots.action"
      :class="[
        'border-line flex items-start justify-between gap-4 border-b',
        padded ? 'p-5' : 'px-5 py-4',
      ]"
    >
      <div class="min-w-0">
        <h2 v-if="title" class="text-ink-strong text-base font-bold">{{ title }}</h2>
        <p v-if="description" class="text-ink-muted mt-1 text-sm leading-6">{{ description }}</p>
      </div>
      <div v-if="$slots.action" class="shrink-0"><slot name="action" /></div>
    </header>
    <div :class="padded ? 'p-5' : ''"><slot /></div>
  </section>
</template>
