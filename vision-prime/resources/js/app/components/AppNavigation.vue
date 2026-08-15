<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface NavigationItem {
  label: string
  href: string
  exact?: boolean
}

interface NavigationGroup {
  label: string
  items: NavigationItem[]
}

const baseGroups: NavigationGroup[] = [
  { label: 'نمای کلی', items: [{ label: 'داشبورد', href: '/app/dashboard', exact: true }] },
  {
    label: 'فضای کاری',
    items: [
      { label: 'مشتریان', href: '/app/clients' },
      { label: 'پروژه‌ها', href: '/app/projects' },
      { label: 'سایت‌ها', href: '/app/sites' },
    ],
  },
  {
    label: 'هوش رشد',
    items: [
      { label: 'فرصت‌های رشد', href: '/app/opportunities' },
      { label: 'صفحات درآمدزا', href: '/app/money-pages' },
      { label: 'ریسک‌های تبدیل', href: '/app/conversion-risks' },
      { label: 'URLها و محتوا', href: '/app/url-profiles' },
    ],
  },
  {
    label: 'گردش‌کار',
    items: [
      { label: 'پیشنهادها', href: '/app/recommendations' },
      { label: 'بررسی و تأییدها', href: '/app/reviews' },
      { label: 'تغییرات اجرایی', href: '/app/commands' },
      { label: 'تولید مقاله', href: '/app/ai-drafts/article/create' },
      { label: 'تولید محصول', href: '/app/ai-drafts/product/create' },
      { label: 'گزارش‌ها', href: '/app/reports' },
    ],
  },
  {
    label: 'تنظیمات',
    items: [
      { label: 'سازمان و اعضا', href: '/app/settings/organization' },
      { label: 'یکپارچه‌سازی‌ها', href: '/app/settings/integrations' },
      { label: 'گزارش ممیزی', href: '/app/settings/audit-log' },
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
      items: [{ label: 'لیدها و دادهٔ تبلیغات', href: '/app/marketing' }],
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
  <nav class="space-y-6" aria-label="ناوبری فضای کاری">
    <section v-for="group in groups" :key="group.label">
      <p class="text-ink-muted px-3 text-xs font-bold tracking-wide">{{ group.label }}</p>
      <ul class="mt-2 space-y-1">
        <li v-for="item in group.items" :key="item.href">
          <Link
            :href="item.href"
            :class="[
              'transition-ui rounded-ui block px-3 py-2.5 text-sm font-medium',
              isActive(item)
                ? 'bg-brand-50 text-brand-700'
                : 'text-ink hover:bg-surface-muted hover:text-ink-strong',
            ]"
            >{{ item.label }}</Link
          >
        </li>
      </ul>
    </section>
  </nav>
</template>
