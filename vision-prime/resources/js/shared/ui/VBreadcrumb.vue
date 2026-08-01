<script setup lang="ts">
export interface BreadcrumbItem {
  label: string
  href?: string
}

defineProps<{
  items: BreadcrumbItem[]
}>()
</script>

<template>
  <nav v-if="items.length" aria-label="مسیر صفحه">
    <ol class="text-ink-muted flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
      <li
        v-for="(item, index) in items"
        :key="`${item.label}-${index}`"
        class="inline-flex items-center gap-2"
      >
        <span v-if="index" class="text-line-strong" aria-hidden="true">/</span>
        <a
          v-if="item.href && index !== items.length - 1"
          :href="item.href"
          class="transition-ui hover:text-brand-700"
          >{{ item.label }}</a
        >
        <span
          v-else
          :class="index === items.length - 1 ? 'text-ink font-medium' : ''"
          :aria-current="index === items.length - 1 ? 'page' : undefined"
          >{{ item.label }}</span
        >
      </li>
    </ol>
  </nav>
</template>
