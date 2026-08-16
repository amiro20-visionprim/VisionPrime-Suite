<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import {
  formatJalaliDate,
  formatLocalizedDate,
  formatNumber,
  formatPercent,
  formatTechnicalNumber,
} from '@/lib/locale'

const sampleDate = '2026-07-25T12:30:00Z'
</script>

<template>
  <Head title="RTL & Localization" />

  <main class="bg-canvas min-h-screen px-5 py-10 sm:px-8 lg:px-12" dir="rtl">
    <div class="mx-auto max-w-4xl space-y-8">
      <header class="border-line border-b pb-8">
        <p class="text-brand-700 text-sm font-bold tracking-wide">VISION PRIME SUITE / DEVELOPMENT</p>
        <h1 class="text-page-title font-display text-ink-strong mt-3 font-bold">RTL و بومی‌سازی</h1>
        <p class="text-ink-muted mt-3 max-w-2xl leading-8">
          مرجع کنترل نمایش فارسی، تاریخ جلالی، عدد و محتوای ترکیبی RTL/LTR.
        </p>
      </header>

      <section class="grid gap-5 sm:grid-cols-2">
        <article class="rounded-card border-line bg-surface shadow-card border p-5">
          <p class="text-ink-muted text-sm">تاریخ جلالی</p>
          <p class="text-ink-strong mt-3 text-xl font-semibold">
            {{ formatJalaliDate(sampleDate) }}
          </p>
          <p class="text-ink mt-2 text-sm">{{ formatLocalizedDate(sampleDate, 'fa') }}</p>
        </article>
        <article class="rounded-card border-line bg-surface shadow-card border p-5">
          <p class="text-ink-muted text-sm">اعداد و درصد</p>
          <p class="text-ink-strong mt-3 text-xl font-semibold">{{ formatNumber(12450) }} نمایش</p>
          <p class="text-ink mt-2 text-sm">{{ formatPercent(8.4) }} نرخ کلیک</p>
        </article>
      </section>

      <section class="rounded-card border-line bg-surface shadow-card border p-5">
        <h2 class="text-ink-strong font-semibold">محتوای ترکیبی</h2>
        <dl class="mt-5 space-y-4 text-sm">
          <div class="grid gap-2 sm:grid-cols-[10rem_1fr]">
            <dt class="text-ink-muted">آدرس صفحه</dt>
            <dd class="font-latin text-ink-strong break-all" dir="ltr">
              https://example.ir/services/seo-audit
            </dd>
          </div>
          <div class="grid gap-2 sm:grid-cols-[10rem_1fr]">
            <dt class="text-ink-muted">Query</dt>
            <dd class="font-latin text-ink-strong" dir="ltr">wordpress seo audit pricing</dd>
          </div>
          <div class="grid gap-2 sm:grid-cols-[10rem_1fr]">
            <dt class="text-ink-muted">Metric فنی</dt>
            <dd class="font-latin text-ink-strong" dir="ltr">
              {{ formatTechnicalNumber(8.42) }} avg. position
            </dd>
          </div>
        </dl>
      </section>
    </div>
  </main>
</template>
