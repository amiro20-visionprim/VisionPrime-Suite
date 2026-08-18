<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import VButton from '@/shared/ui/VButton.vue'
import VDrawer from '@/shared/ui/VDrawer.vue'
import VThemeToggle from '@/shared/ui/VThemeToggle.vue'

interface NavigationItem {
  label: string
  href: string
}

const navigation: NavigationItem[] = [
  { label: 'محصول', href: '/product' },
  { label: 'قابلیت‌ها', href: '/features' },
  { label: 'قیمت‌گذاری', href: '/pricing' },
  { label: 'امنیت', href: '/security' },
]

const mobileMenuOpen = ref(false)
const page = usePage()
const currentPath = computed(() => page.url.split('?')[0])

function isActive(href: string): boolean {
  return currentPath.value === href
}

function closeMenu(): void {
  mobileMenuOpen.value = false
}
</script>

<template>
  <header class="border-line bg-surface/95 border-b backdrop-blur">
    <div
      class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-5 px-5 sm:px-8 lg:px-10"
    >
      <Link
        href="/"
        class="text-ink-strong inline-flex shrink-0 items-center gap-2.5"
        aria-label="Vision Prime SUITE، صفحه اصلی"
      >
        <span
          class="bg-gradient-brand shadow-md shadow-indigo-500/25 flex size-9 items-center justify-center rounded-xl text-sm font-bold text-white"
          >VP</span
        >
        <span class="font-display text-lg font-bold tracking-tight">Vision Prime SUITE</span>
      </Link>

      <nav class="hidden items-center gap-1 lg:flex" aria-label="ناوبری اصلی">
        <Link
          v-for="item in navigation"
          :key="item.href"
          :href="item.href"
          :class="[
            'transition-ui rounded-ui px-3 py-2 text-sm font-medium',
            isActive(item.href)
              ? 'bg-brand-50 text-brand-700'
              : 'text-ink hover:bg-surface-muted hover:text-ink-strong',
          ]"
        >
          {{ item.label }}
        </Link>
      </nav>

      <div class="hidden items-center gap-3 lg:flex">
        <VThemeToggle />
        <Link href="/login" class="text-ink hover:text-brand-700 text-sm font-semibold">ورود</Link>
        <VButton href="/demo" size="sm">درخواست دموی اختصاصی</VButton>
      </div>

      <button
        type="button"
        class="rounded-ui border-line text-ink-strong border p-2 lg:hidden"
        aria-label="بازکردن منو"
        @click="mobileMenuOpen = true"
      >
        <span class="block h-0.5 w-5 bg-current" />
        <span class="mt-1 block h-0.5 w-5 bg-current" />
        <span class="mt-1 block h-0.5 w-5 bg-current" />
      </button>
    </div>

    <VDrawer v-model="mobileMenuOpen" title="منو" side="start">
      <nav class="space-y-1" aria-label="ناوبری موبایل">
        <Link
          v-for="item in navigation"
          :key="item.href"
          :href="item.href"
          :class="[
            'rounded-ui block px-3 py-3 text-sm font-semibold',
            isActive(item.href) ? 'bg-brand-50 text-brand-700' : 'text-ink hover:bg-surface-muted',
          ]"
          @click="closeMenu"
        >
          {{ item.label }}
        </Link>
      </nav>
      <template #footer>
        <div class="space-y-3">
          <Link
            href="/login"
            class="text-ink block text-center text-sm font-semibold"
            @click="closeMenu"
            >ورود به حساب</Link
          >
          <VButton href="/demo" class="w-full" @click="closeMenu">درخواست دموی اختصاصی</VButton>
        </div>
      </template>
    </VDrawer>
  </header>
</template>
