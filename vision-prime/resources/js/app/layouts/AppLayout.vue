<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

import AppNavigation from '@/app/components/AppNavigation.vue'
import AppNotificationBell from '@/app/components/AppNotificationBell.vue'
import OrganizationSwitcher from '@/app/components/OrganizationSwitcher.vue'
import VDrawer from '@/shared/ui/VDrawer.vue'
import VButton from '@/shared/ui/VButton.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import VOnboardingTour, { type TourStep } from '@/shared/ui/VOnboardingTour.vue'
import VSupportAssistant from '@/shared/ui/VSupportAssistant.vue'
import VThemeToggle from '@/shared/ui/VThemeToggle.vue'
import type { AppPageProps } from '@/types/app'

const agencyTourSteps: TourStep[] = [
  {
    icon: 'sparkles',
    title: 'به فضای کاری سوئیت خوش آمدید',
    body: 'اینجا تمام عملیات SEO سازمان شما در یک جا گرد آمده: مشتریان، سایت‌ها، فرصت‌ها و تأییدها.',
  },
  {
    icon: 'list',
    title: 'ناوبری گروه‌بندی‌شده',
    body: 'بخش‌های کار بر اساس موضوع دسته‌بندی شده‌اند: فضای کاری، هوش رشد، گردش‌کار و تنظیمات. هر گروه با آیکون مخصوص خودش.',
  },
  {
    icon: 'user-check',
    title: 'صف تأییدها',
    body: 'در داشبورد، «در انتظار تأیید» نشان می‌دهد چه پیشنهادها و بازبینی‌هایی منتظر اقدام شماست.',
  },
  {
    icon: 'lightbulb',
    title: 'اولویت‌های نقش شما',
    body: 'داشبورد بر اساس نقش شما چیده می‌شود؛ هر نقش میانبرهای مخصوص خودش را می‌بیند. سؤالی داشتید از «مرکز آموزش» شروع کنید.',
    cta: { label: 'بازدید از مرکز آموزش', href: '/app/training' },
  },
]

const page = usePage<AppPageProps & { auth?: { user?: { name: string; email: string } } }>()
const mobileNavigationOpen = ref(false)
const supportOpen = ref(false)

function logout(): void {
  router.post('/logout')
}
</script>

<template>
  <div class="bg-canvas min-h-screen" dir="rtl">
    <aside
      class="border-line bg-surface fixed inset-y-0 start-0 z-30 hidden w-68 border-e lg:flex lg:flex-col"
    >
      <div class="border-line border-b p-5">
        <Link href="/app/dashboard" class="text-ink-strong inline-flex items-center gap-2.5"
          ><span
            class="rounded-ui bg-brand-700 flex size-9 items-center justify-center text-sm font-bold text-white"
            >VP</span
          ><span class="font-display text-lg font-bold">Vision Prime SUITE</span></Link
        >
      </div>
      <div class="p-4"><OrganizationSwitcher /></div>
      <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-5"><AppNavigation /></div>
      <div class="border-line border-t p-4">
        <p class="text-ink-strong truncate text-sm font-semibold">
          {{ page.props.auth?.user?.name }}
        </p>
        <p class="font-latin text-ink-muted mt-1 truncate text-xs" dir="ltr">
          {{ page.props.auth?.user?.email }}
        </p>
        <VButton class="mt-3 w-full" variant="ghost" size="sm" @click="logout"
          >خروج از حساب</VButton
        >
      </div>
    </aside>

    <div class="lg:ps-68">
      <header
        class="border-line bg-surface/95 sticky top-0 z-20 flex h-16 items-center justify-between border-b px-5 backdrop-blur sm:px-8"
      >
        <button
          type="button"
          class="rounded-ui border-line text-ink-strong border p-2 lg:hidden"
          aria-label="بازکردن منوی فضای کاری"
          @click="mobileNavigationOpen = true"
        >
          <span class="block h-0.5 w-5 bg-current" /><span
            class="mt-1 block h-0.5 w-5 bg-current"
          /><span class="mt-1 block h-0.5 w-5 bg-current" />
        </button>
        <div class="text-ink-muted hidden text-sm lg:block">عملیات SEO، شفاف و قابل کنترل</div>
        <div class="flex items-center gap-3">
          <VThemeToggle />
          <button
            type="button"
            class="transition-ui rounded-ui border-line text-ink-strong hover:bg-surface-muted inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold"
            aria-label="راهنما و پشتیبانی"
            @click="supportOpen = true"
          >
            <VIcon name="support" tone="brand" size="sm" />
            <span class="hidden sm:inline">راهنما و پشتیبانی</span>
          </button>
          <AppNotificationBell />
          <span class="text-ink hidden text-sm sm:inline">{{
            page.props.currentOrganization?.name
          }}</span
          ><VButton href="/app/sites" size="sm">افزودن سایت</VButton>
        </div>
      </header>
      <main class="p-5 sm:p-8">
        <VOnboardingTour storage-key="vp-app-onboarding-done" :steps="agencyTourSteps" />
        <slot />
      </main>
    </div>

    <VSupportAssistant v-model="supportOpen" />

    <VDrawer v-model="mobileNavigationOpen" title="Vision Prime SUITE" side="start">
      <OrganizationSwitcher />
      <div class="mt-6"><AppNavigation /></div>
      <template #footer
        ><VButton class="w-full" variant="ghost" @click="logout">خروج از حساب</VButton></template
      >
    </VDrawer>
  </div>
</template>
