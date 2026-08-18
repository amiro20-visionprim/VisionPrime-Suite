<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

import VThemeToggle from '@/shared/ui/VThemeToggle.vue'

withDefaults(
  defineProps<{
    title: string
    description?: string
  }>(),
  {
    description: '',
  },
)
</script>

<template>
  <Head :title="title" />
  <main class="bg-canvas relative min-h-screen px-5 py-8 sm:px-8 sm:py-12" dir="rtl">
    <div class="absolute top-5 end-5 z-10"><VThemeToggle /></div>
    <div
      class="rounded-panel border-line bg-surface shadow-panel mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl overflow-hidden border lg:grid-cols-[1.1fr_0.9fr]"
    >
      <section class="order-2 flex flex-col justify-between p-6 sm:p-10 lg:order-1 lg:p-12">
        <div>
          <Link href="/" class="text-brand-700 inline-flex items-center gap-2">
            <span
              class="rounded-ui bg-brand-700 flex size-9 items-center justify-center text-sm font-bold text-white"
              >VP</span
            >
            <span class="font-display text-lg font-bold">Vision Prime SUITE</span>
          </Link>
          <div class="mt-10 max-w-md">
            <h1 class="text-page-title font-display text-ink-strong font-bold">{{ title }}</h1>
            <p v-if="description" class="text-ink-muted mt-3 leading-7">{{ description }}</p>
          </div>
          <div class="mt-8 max-w-md"><slot /></div>
        </div>
        <p class="text-ink-muted mt-12 text-sm">
          پلتفرم هوشمند عملیات و رشد SEO برای سایت‌های وردپرسی
        </p>
      </section>
      <aside class="bg-brand-900 order-1 hidden p-12 text-white lg:order-2 lg:block">
        <p class="text-brand-200 text-sm font-semibold tracking-wide">PREMIUM SEO INTELLIGENCE</p>
        <h2 class="mt-6 text-3xl leading-relaxed font-bold">
          از داده تا اقدام، با کنترل کامل تیم شما.
        </h2>
        <p class="text-brand-100 mt-5 max-w-sm leading-8">
          فرصت‌های رشد را کشف کنید، تغییرات را با اطمینان مدیریت کنید و نتیجه را شفاف به مشتری نشان
          دهید.
        </p>
        <div class="text-brand-100 mt-12 border-t border-white/15 pt-6 text-sm leading-7">
          تمام فعالیت‌های حساس در سوئیت قابل بررسی، تأیید و ثبت هستند.
        </div>
      </aside>
    </div>
  </main>
</template>
