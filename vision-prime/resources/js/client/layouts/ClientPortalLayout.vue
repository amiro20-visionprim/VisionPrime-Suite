<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

import ClientNavigation from '@/client/components/ClientNavigation.vue'
import ClientSwitcher from '@/client/components/ClientSwitcher.vue'
import VButton from '@/shared/ui/VButton.vue'
import VDrawer from '@/shared/ui/VDrawer.vue'
import type { AppPageProps } from '@/types/app'

const page = usePage<AppPageProps & { auth?: { user?: { name: string; email: string } } }>()
const mobileNavigationOpen = ref(false)

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
        <Link href="/client/dashboard" class="text-ink-strong inline-flex items-center gap-2.5"
          ><span
            class="rounded-ui bg-brand-700 flex size-9 items-center justify-center text-sm font-bold text-white"
            >VP</span
          ><span class="font-display text-lg font-bold">Vision Prime</span></Link
        >
        <p class="text-ink-muted mt-4 text-xs leading-5">پرتال گزارش و تصمیم‌گیری</p>
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
        class="border-line bg-surface/95 sticky top-0 z-20 flex h-16 items-center justify-between border-b px-5 backdrop-blur sm:px-8"
      >
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
        <p class="text-ink-muted hidden text-sm lg:block">
          خلاصه‌ای روشن از رشد، اولویت‌ها و گام بعدی
        </p>
        <span class="text-ink-strong text-sm font-semibold">پرتال مشتری</span>
      </header>
      <main class="p-5 sm:p-8"><slot /></main>
    </div>
    <VDrawer v-model="mobileNavigationOpen" title="پرتال مشتری" side="start">
      <ClientSwitcher />
      <div class="mt-6"><ClientNavigation /></div>
      <template #footer
        ><VButton class="w-full" variant="ghost" @click="logout">خروج</VButton></template
      >
    </VDrawer>
  </div>
</template>
