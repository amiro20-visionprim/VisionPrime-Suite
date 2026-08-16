<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import VIcon, { type IconName } from '@/shared/ui/VIcon.vue'

interface NavigationItem {
  label: string
  hint?: string
  href: string
  icon: IconName
  exact?: boolean
}

interface NavigationGroup {
  label: string
  icon: IconName
  items: NavigationItem[]
}

const baseGroups: NavigationGroup[] = [
  {
    label: 'نمای کلی',
    icon: 'chart-line',
    items: [{ label: 'داشبورد', href: '/app/dashboard', icon: 'chart-line', exact: true }],
  },
  {
    label: 'فضای کاری',
    icon: 'building',
    items: [
      { label: 'مشتریان', href: '/app/clients', icon: 'building' },
      { label: 'پروژه‌ها', href: '/app/projects', icon: 'users' },
      { label: 'سایت‌ها', href: '/app/sites', icon: 'activity' },
    ],
  },
  {
    label: 'هوش رشد',
    icon: 'trend-up',
    items: [
      { label: 'فرصت‌های رشد', href: '/app/opportunities', icon: 'lightbulb' },
      { label: 'صفحات درآمدزا', href: '/app/money-pages', icon: 'shopping-bag' },
      { label: 'ریسک‌های تبدیل', href: '/app/conversion-risks', icon: 'trend-down' },
      { label: 'URLها و محتوا', href: '/app/url-profiles', icon: 'file' },
    ],
  },
  {
    label: 'گردش‌کار',
    icon: 'list',
    items: [
      { label: 'پیشنهادها', href: '/app/recommendations', icon: 'lightbulb' },
      { label: 'بررسی و تأییدها', href: '/app/reviews', icon: 'user-check' },
      { label: 'تغییرات اجرایی', href: '/app/commands', icon: 'zap' },
      { label: 'تولید مقاله', href: '/app/ai-drafts/article/create', icon: 'file' },
      { label: 'تولید محصول', href: '/app/ai-drafts/product/create', icon: 'shopping-bag' },
      { label: 'تقویم محتوایی', href: '/app/content-calendar', icon: 'calendar' },
      { label: 'گزارش‌ها', href: '/app/reports', icon: 'news' },
    ],
  },
  {
    label: 'تنظیمات',
    icon: 'settings',
    items: [
      { label: 'سازمان و اعضا', href: '/app/settings/organization', icon: 'users' },
      { label: 'یکپارچه‌سازی‌ها', href: '/app/settings/integrations', icon: 'zap' },
      { label: 'گزارش ممیزی', href: '/app/settings/audit-log', icon: 'shield' },
    ],
  },
]

const page = usePage<{ permissions?: string[] }>()
const canViewMarketing = computed(
  () => page.props.permissions?.includes('marketing.view.organization') ?? false,
)

const groups = computed<NavigationGroup[]>(() => {
  const groupsList = [...baseGroups]
  if (canViewMarketing.value) {
    groupsList.splice(1, 0, {
      label: 'بازاریابی',
      icon: 'megaphone',
      items: [{ label: 'لیدها و دادهٔ تبلیغات', href: '/app/marketing', icon: 'megaphone' }],
    })
  }
  return groupsList
})

const currentPath = computed(() => page.url.split('?')[0])

function isActive(item: NavigationItem): boolean {
  return item.exact
    ? currentPath.value === item.href
    : currentPath.value === item.href || currentPath.value.startsWith(`${item.href}/`)
}
</script>

<template>
  <nav class="space-y-5" aria-label="ناوبری فضای کاری">
    <section v-for="group in groups" :key="group.label">
      <p class="text-ink-muted flex items-center gap-1.5 px-3 text-xs font-bold tracking-wide">
        <VIcon :name="group.icon" size="sm" />
        {{ group.label }}
      </p>
      <ul class="mt-2 space-y-1">
        <li v-for="item in group.items" :key="item.href">
          <Link
            :href="item.href"
            :class="[
              'transition-ui rounded-ui group flex items-center gap-3 px-3 py-2 text-sm font-medium',
              isActive(item)
                ? 'bg-brand-50 text-brand-700'
                : 'text-ink hover:bg-surface-muted hover:text-ink-strong',
            ]"
          >
            <span
              :class="[
                'rounded-ui flex size-7 shrink-0 items-center justify-center',
                isActive(item)
                  ? 'bg-brand-100 text-brand-700'
                  : 'bg-surface-muted text-ink-muted group-hover:text-ink-strong',
              ]"
            >
              <VIcon :name="item.icon" :tone="isActive(item) ? 'brand' : 'neutral'" size="sm" />
            </span>
            <span>{{ item.label }}</span>
          </Link>
        </li>
      </ul>
    </section>
  </nav>
</template>
