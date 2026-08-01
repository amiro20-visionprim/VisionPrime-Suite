<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const items = [
  { label: 'نمای کلی', href: '/client/dashboard', exact: true },
  { label: 'رشد و فرصت‌ها', href: '/client/growth' },
  { label: 'سلامت سایت', href: '/client/site-health' },
  { label: 'اولویت‌ها', href: '/client/opportunities' },
  { label: 'نیازمند تصمیم شما', href: '/client/decisions' },
  { label: 'گزارش‌ها', href: '/client/reports' },
  { label: 'فعالیت‌ها', href: '/client/activity' },
]

const page = usePage()
const currentPath = computed(() => page.url.split('?')[0])

function isActive(item: (typeof items)[number]): boolean {
  return item.exact
    ? currentPath.value === item.href
    : currentPath.value === item.href || currentPath.value.startsWith(`${item.href}/`)
}
</script>

<template>
  <nav class="space-y-1" aria-label="ناوبری پرتال مشتری">
    <Link
      v-for="item in items"
      :key="item.href"
      :href="item.href"
      :class="[
        'transition-ui rounded-ui block px-3 py-2.5 text-sm font-medium',
        isActive(item)
          ? 'bg-brand-50 text-brand-700'
          : 'text-ink hover:bg-surface-muted hover:text-ink-strong',
      ]"
      >{{ item.label }}</Link
    >
  </nav>
</template>
