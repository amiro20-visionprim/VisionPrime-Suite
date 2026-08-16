<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import VIcon, { type IconName } from '@/shared/ui/VIcon.vue'

interface NavItem {
  label: string
  hint: string
  href: string
  icon: IconName
  exact?: boolean
}

const items: NavItem[] = [
  { label: 'خانه', hint: 'سایت من در یک نگاه', href: '/client/dashboard', icon: 'chart-line', exact: true },
  { label: 'رشد من', hint: 'چقدر دیده می‌شوم', href: '/client/growth', icon: 'trend-up' },
  { label: 'اولویت‌ها', hint: 'کجا بهتر شویم', href: '/client/opportunities', icon: 'lightbulb' },
  { label: 'تأییدهای من', hint: 'منتظر تصمیم شما', href: '/client/decisions', icon: 'user-check' },
  { label: 'گزارش‌ها', hint: 'گزارش‌های دوره‌ای', href: '/client/reports', icon: 'file' },
  { label: 'وضعیت سایت', hint: 'سلامت فنی سایت', href: '/client/site-health', icon: 'gauge' },
]

const page = usePage()
const currentPath = computed(() => page.url.split('?')[0])

function isActive(item: NavItem): boolean {
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
        'transition-ui rounded-ui group flex items-center gap-3 px-3 py-2.5',
        isActive(item)
          ? 'bg-brand-50 text-brand-700'
          : 'text-ink hover:bg-surface-muted hover:text-ink-strong',
      ]"
    >
      <span
        :class="[
          'rounded-ui flex size-8 shrink-0 items-center justify-center transition-colors',
          isActive(item) ? 'bg-brand-100 text-brand-700' : 'bg-surface-muted text-ink-muted group-hover:text-ink-strong',
        ]"
      >
        <VIcon :name="item.icon" :tone="isActive(item) ? 'brand' : 'neutral'" size="sm" />
      </span>
      <span class="min-w-0">
        <span class="block text-sm font-semibold">{{ item.label }}</span>
        <span class="text-ink-muted block truncate text-[11px]">{{ item.hint }}</span>
      </span>
    </Link>
  </nav>
</template>
