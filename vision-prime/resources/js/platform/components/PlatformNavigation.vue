<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import VIcon, { type IconName } from '@/shared/ui/VIcon.vue'

interface NavigationItem {
  label: string
  href: string
  icon: IconName
  hint: string
}

const items: NavigationItem[] = [
  { label: 'داشبورد فرماندهی', href: '/platform/dashboard', icon: 'chart-line', hint: 'KPI و تصمیمها' },
  { label: 'سازمانها', href: '/platform/organizations', icon: 'building', hint: 'رصد و مدیریت' },
  { label: 'اشتراکها', href: '/platform/subscriptions', icon: 'calendar-clock', hint: 'پلن و پرداخت' },
  { label: 'پلن‌ها', href: '/platform/plans', icon: 'shopping-bag', hint: 'قیمت و ظرفیت' },
  { label: 'پرداختها', href: '/platform/payments', icon: 'chart-bar', hint: 'درآمد و معوق' },
  { label: 'فاکتورها', href: '/platform/invoices', icon: 'file', hint: 'صدور و پیگیری' },
  { label: 'رصد فنی', href: '/platform/operations', icon: 'gauge', hint: 'صف و اتصالها' },
  { label: 'گزارشها', href: '/platform/reports', icon: 'news', hint: 'درآمد و رشد' },
  { label: 'پنل پیامک', href: '/platform/sms', icon: 'megaphone', hint: 'ارسال و تاریخچه' },
]

const page = usePage()
const url = computed(() => String(page.url))

function isActive(item: NavigationItem): boolean {
  return url.value.startsWith(item.href)
}
</script>

<template>
  <nav class="space-y-1">
    <Link
      v-for="item in items"
      :key="item.href"
      :href="item.href"
      class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors"
      :class="isActive(item) ? 'bg-brand-600/10 text-brand-600' : 'text-ink-strong hover:bg-surface-muted'"
    >
      <span
        class="flex size-9 shrink-0 items-center justify-center rounded-lg"
        :class="isActive(item) ? 'bg-brand-600/15' : 'bg-surface-muted group-hover:bg-surface'"
      >
        <VIcon :name="item.icon" :tone="isActive(item) ? 'brand' : 'neutral'" size="md" />
      </span>
      <span class="min-w-0">
        <span class="block truncate text-sm font-semibold">{{ item.label }}</span>
        <span class="text-ink-muted block truncate text-xs">{{ item.hint }}</span>
      </span>
    </Link>
  </nav>
</template>
