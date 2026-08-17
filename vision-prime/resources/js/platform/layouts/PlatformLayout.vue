<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

import PlatformNavigation from '@/platform/components/PlatformNavigation.vue'
import VButton from '@/shared/ui/VButton.vue'
import VSupportAssistant from '@/shared/ui/VSupportAssistant.vue'
import VThemeToggle from '@/shared/ui/VThemeToggle.vue'

const page = usePage<{ auth?: { user?: { name: string; email: string } } }>()
const supportOpen = ref(false)

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
        <Link href="/platform/dashboard" class="text-ink-strong inline-flex items-center gap-2.5"
          ><span
            class="rounded-ui flex size-9 items-center justify-center bg-gradient-to-br from-brand-600 to-brand-900 text-sm font-bold text-white"
            >PI</span
          ><span class="font-display text-lg font-bold">فرماندهی پلتفرم</span></Link
        >
        <p class="text-ink-muted mt-1 text-xs">رصد کل اکوسیستم — ویژن پرایم سوئیت</p>
      </div>
      <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-5"><PlatformNavigation /></div>
      <div class="border-line border-t p-4">
        <p class="text-ink-strong truncate text-sm font-semibold">
          {{ page.props.auth?.user?.name }}
        </p>
        <p class="font-latin text-ink-muted mt-1 truncate text-xs" dir="ltr">
          {{ page.props.auth?.user?.email }}
        </p>
        <Link
          href="/app/dashboard"
          class="text-brand-600 mt-2 inline-flex items-center gap-1.5 text-xs font-semibold"
          >بازگشت به فضای کاری ←</Link
        >
        <VButton class="mt-3 w-full" variant="ghost" size="sm" @click="logout"
          >خروج از حساب</VButton
        >
      </div>
    </aside>

    <div class="lg:ps-64">
      <header
        class="border-line bg-surface/95 sticky top-0 z-20 flex h-16 items-center justify-between border-b px-5 backdrop-blur sm:px-8"
      >
        <div class="text-ink-muted hidden text-sm lg:block">
          اتاق فرماندهی — استثناها و تصمیمها، نه جزئیات روزمره
        </div>
        <div class="flex items-center gap-3">
          <VThemeToggle />
          <button
            type="button"
            class="transition-ui rounded-ui border-line text-ink-strong hover:bg-surface-muted inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold"
            aria-label="راهنما و پشتیبانی"
            @click="supportOpen = true"
          >
            راهنما و پشتیبانی
          </button>
        </div>
      </header>
      <main class="p-5 sm:p-8"><slot /></main>
    </div>

    <VSupportAssistant v-model="supportOpen" />
  </div>
</template>
