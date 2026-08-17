<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import ClientNavigation from '@/client/components/ClientNavigation.vue'
import ClientSwitcher from '@/client/components/ClientSwitcher.vue'
import { formatLocalizedDate } from '@/lib/locale'
import VButton from '@/shared/ui/VButton.vue'
import VDrawer from '@/shared/ui/VDrawer.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import VOnboardingTour from '@/shared/ui/VOnboardingTour.vue'
import VSupportAssistant from '@/shared/ui/VSupportAssistant.vue'
import VThemeToggle from '@/shared/ui/VThemeToggle.vue'
import type { AppPageProps } from '@/types/app'

const page = usePage<AppPageProps & { auth?: { user?: { name: string } } }>()
const mobileNavigationOpen = ref(false)
const supportOpen = ref(false)

const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'دوست عزیز')
const today = computed(() => formatLocalizedDate(new Date(), 'fa'))

function logout(): void {
  router.post('/logout')
}
</script>

<template>
  <div class="bg-canvas min-h-screen" dir="rtl">
    <aside
      class="border-line bg-surface fixed inset-y-0 start-0 z-30 hidden w-64 border-e lg:flex lg:flex-col"
    >
      <div class="border-line border-b p-5">
        <div class="flex items-center gap-2.5">
          <span class="bg-gradient-brand rounded-ui flex size-10 items-center justify-center text-white">
            <VIcon name="sparkles" size="lg" />
          </span>
          <div class="min-w-0">
            <p class="font-display text-ink-strong text-base leading-tight font-bold">پرتال رشد شما</p>
            <p class="text-ink-muted mt-0.5 text-[11px]">قدرت‌گرفته از سوئیت</p>
          </div>
        </div>
      </div>
      <div class="p-4"><ClientSwitcher /></div>
      <div class="min-h-0 flex-1 overflow-y-auto px-3"><ClientNavigation /></div>
      <div class="border-line border-t p-4">
        <p class="text-ink-strong truncate text-sm font-semibold">
          {{ page.props.auth?.user?.name }}
        </p>
        <VButton class="mt-3 w-full" variant="ghost" size="sm" @click="logout">خروج</VButton>
      </div>
    </aside>

    <div class="lg:ps-64">
      <header
        class="border-line bg-surface/95 sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b px-5 backdrop-blur sm:px-8"
      >
        <div class="flex min-w-0 items-center gap-3">
          <button
            type="button"
            class="rounded-ui border-line text-ink-strong border p-2 lg:hidden"
            aria-label="بازکردن منوی پرتال"
            @click="mobileNavigationOpen = true"
          >
            <span class="block h-0.5 w-5 bg-current" /><span
              class="mt-1 block h-0.5 w-5 bg-current"
            /><span class="mt-1 block h-0.5 w-5 bg-current" />
          </button>
          <div class="min-w-0">
            <p class="text-ink-strong truncate text-sm font-bold sm:text-base">
              سلام، {{ firstName }} 👋
            </p>
            <p class="text-ink-muted truncate text-xs">{{ today }}</p>
          </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <VThemeToggle />
          <button
            type="button"
            class="transition-ui rounded-ui border-line text-ink-strong hover:bg-surface-muted inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold"
            @click="supportOpen = true"
          >
            <VIcon name="support" tone="brand" size="sm" />
            <span class="hidden sm:inline">راهنما و پشتیبانی</span>
            <span class="sm:hidden">راهنما</span>
          </button>
        </div>
      </header>

      <main class="p-5 sm:p-8">
        <VOnboardingTour />
        <slot />
      </main>
    </div>

    <VDrawer v-model="mobileNavigationOpen" title="پرتال رشد شما" side="start">
      <ClientSwitcher />
      <div class="mt-6"><ClientNavigation /></div>
      <template #footer
        ><VButton class="w-full" variant="ghost" @click="logout">خروج</VButton></template
      >
    </VDrawer>

    <VSupportAssistant v-model="supportOpen" />
  </div>
</template>
