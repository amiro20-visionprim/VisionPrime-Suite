<script setup lang="ts">
import VBreadcrumb, { type BreadcrumbItem } from './VBreadcrumb.vue'
import VStatusDot from './VStatusDot.vue'
import type { StatusTone } from './VStatusDot.vue'

withDefaults(
  defineProps<{
    title: string
    description?: string
    breadcrumbs?: BreadcrumbItem[]
    status?: { label: string; tone?: StatusTone }
  }>(),
  {
    description: '',
    breadcrumbs: () => [],
    status: undefined,
  },
)
</script>

<template>
  <header class="space-y-4">
    <VBreadcrumb v-if="breadcrumbs.length" :items="breadcrumbs" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-page-title font-display text-ink-strong font-bold">{{ title }}</h1>
          <VStatusDot v-if="status" :label="status.label" :tone="status.tone" />
        </div>
        <p v-if="description" class="text-ink-muted mt-2 max-w-3xl leading-7">{{ description }}</p>
      </div>
      <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-3">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>
