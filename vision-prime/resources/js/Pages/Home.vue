<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'

const pillars = [
  {
    title: 'اولویت‌گذاری مبتنی بر داده',
    text: 'فرصت‌هایی را ببینید که با داده سرچ کنسول، ارزش کسب‌وکاری و وضعیت صفحه رتبه‌بندی شده‌اند.',
  },
  {
    title: 'تغییرات کنترل‌شده',
    text: 'از پیشنهاد تا تأیید، اجرا و بازگشت؛ هر تغییر قابل ردیابی و مطابق سیاست هر سایت است.',
  },
  {
    title: 'گزارش قابل فهم برای مشتری',
    text: 'خروجی فنی تیم را به تصمیم، پیشرفت و گام بعدی قابل فهم برای کارفرما تبدیل کنید.',
  },
]

const modules = [
  'هوش فرصت‌های رشد',
  'بهینه‌سازی صفحات درآمدزا',
  'ریسک‌های تبدیل',
  'اتصال امن وردپرس',
  'گردش‌کار تأیید',
  'گزارش و سنجش اثر',
]
</script>

<template>
  <Head title="پلتفرم هوشمند عملیات SEO" />
  <MarketingLayout>
    <main>
      <section class="border-line bg-canvas border-b">
        <div
          class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:px-10 lg:py-24"
        >
          <div class="self-center">
            <VBadge tone="info">برای آژانس‌ها و تیم‌های SEO وردپرسی</VBadge>
            <h1
              class="font-display text-ink-strong mt-5 max-w-3xl text-4xl leading-relaxed font-bold sm:text-5xl"
            >
              از داده سرچ کنسول تا اقدام قابل تأیید؛ یک مرکز فرماندهی برای رشد SEO.
            </h1>
            <p class="text-ink-muted mt-5 max-w-2xl text-base leading-8 sm:text-lg">
              Vision Prime فرصت‌های رشد را شناسایی می‌کند، اقدامات را در مسیر کنترل‌شده پیش می‌برد و
              اثر هر تصمیم را به زبان روشن نشان می‌دهد.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
              <VButton href="/demo" size="lg">درخواست دموی اختصاصی</VButton>
              <VButton href="/product" size="lg" variant="secondary">مشاهده محصول</VButton>
            </div>
            <p class="text-ink-muted mt-5 text-sm">
              بدون انتشار مستقیم AI و بدون تغییر پرریسک خارج از سیاست سایت.
            </p>
          </div>

          <div class="rounded-panel border-line bg-surface shadow-panel border p-5 sm:p-6">
            <div class="border-line flex items-center justify-between border-b pb-4">
              <div>
                <p class="text-ink-strong text-sm font-bold">نمای کلی رشد سایت</p>
                <p class="text-ink-muted mt-1 text-xs">آخرین همگام‌سازی: امروز، ۱۰:۴۵</p>
              </div>
              <VBadge tone="success">اتصال پایدار</VBadge>
            </div>
            <div class="mt-5 grid grid-cols-3 gap-3">
              <div class="rounded-card bg-brand-50 p-3">
                <p class="text-ink-muted text-xs">فرصت‌های اولویت‌دار</p>
                <p class="text-brand-900 mt-2 text-2xl font-bold">۱۲</p>
              </div>
              <div class="rounded-card bg-surface-muted p-3">
                <p class="text-ink-muted text-xs">صفحات درآمدزا</p>
                <p class="text-ink-strong mt-2 text-2xl font-bold">۸</p>
              </div>
              <div class="rounded-card bg-success-50 p-3">
                <p class="text-ink-muted text-xs">اقدام‌های تکمیل‌شده</p>
                <p class="text-success-700 mt-2 text-2xl font-bold">۲۴</p>
              </div>
            </div>
            <div class="rounded-card border-line mt-5 border p-4">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-ink-strong font-semibold">فرصت رشد: صفحه خدمات SEO</p>
                  <p class="text-ink-muted mt-1 text-sm leading-6">
                    رتبه ۸٫۴، نمایش بالا و شکاف قابل توجه در CTR
                  </p>
                </div>
                <VBadge tone="warning">امتیاز ۸۷</VBadge>
              </div>
              <div class="bg-surface-muted mt-4 h-2 overflow-hidden rounded-full">
                <div class="bg-brand-600 h-full w-[78%] rounded-full" />
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
        <SectionHeading
          eyebrow="WHY VISION PRIME"
          title="عملیات SEO را از ابزارهای پراکنده به یک جریان قابل کنترل تبدیل کنید."
          description="برای تیم‌هایی که رشد پایدار، مسئولیت‌پذیری و شفافیت خروجی را در کنار هم می‌خواهند."
        />
        <div class="mt-10 grid gap-5 md:grid-cols-3">
          <VCard v-for="pillar in pillars" :key="pillar.title" :title="pillar.title"
            ><p class="text-ink-muted leading-7">{{ pillar.text }}</p></VCard
          >
        </div>
      </section>

      <section class="border-line bg-surface border-y">
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
          <SectionHeading
            eyebrow="CORE MODULES"
            title="همه اجزای ضروری رشد SEO در یک سیستم یکپارچه."
          />
          <div class="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="module in modules"
              :key="module"
              class="rounded-card border-line text-ink-strong border p-4 text-sm font-semibold"
            >
              {{ module }}
            </div>
          </div>
        </div>
      </section>

      <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
        <div class="rounded-panel bg-brand-900 px-6 py-10 text-white sm:px-10 sm:py-14">
          <p class="text-brand-200 text-sm font-bold tracking-wide">
            READY FOR A BETTER SEO OPERATING SYSTEM?
          </p>
          <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <h2 class="text-3xl leading-relaxed font-bold">
                رشد سایت‌هایتان را با داده، کنترل و گزارش‌پذیری جلو ببرید.
              </h2>
              <p class="text-brand-100 mt-3 max-w-2xl leading-8">
                در دمو، جریان کاری مناسب آژانس و سایت‌های وردپرسی شما را بررسی می‌کنیم.
              </p>
            </div>
            <VButton href="/demo" size="lg" variant="secondary">درخواست دمو</VButton>
          </div>
        </div>
      </section>
    </main>
  </MarketingLayout>
</template>
