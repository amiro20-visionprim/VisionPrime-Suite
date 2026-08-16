<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'

const workflow = [
  'اتصال سایت و داده‌ها',
  'کشف و رتبه‌بندی فرصت‌ها',
  'تبدیل تحلیل به پیشنهاد',
  'بررسی یا اجرای مطابق سیاست سایت',
  'سنجش اثر و گزارش‌دهی',
]
</script>

<template>
  <Head title="محصول" />
  <MarketingLayout>
    <MarketingPageHero
      title="یک سیستم عملیاتی برای تبدیل SEO به خروجی قابل اثبات."
      description="سوئیت میان داده، تحلیل، تصمیم تیم، تغییرات وردپرس و گزارش مشتری یک جریان واحد ایجاد می‌کند."
    >
      <template #actions><VButton href="/demo" size="lg">مشاهده در دمو</VButton></template>
    </MarketingPageHero>
    <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
      <SectionHeading eyebrow="OPERATING LOOP" title="از اتصال تا گزارش، بدون گم‌شدن مالکیت کار." />
      <ol class="mt-10 grid gap-4 md:grid-cols-5">
        <li
          v-for="(item, index) in workflow"
          :key="item"
          class="rounded-card border-line bg-surface border p-5"
        >
          <span class="font-latin text-brand-700 text-sm font-bold">0{{ index + 1 }}</span>
          <p class="text-ink-strong mt-4 leading-7 font-semibold">{{ item }}</p>
        </li>
      </ol>
    </section>
    <section class="border-line bg-surface border-y">
      <div class="mx-auto grid max-w-7xl gap-6 px-5 py-16 sm:px-8 lg:grid-cols-3 lg:px-10">
        <VCard title="پنل عملیاتی"
          ><p class="text-ink-muted leading-7">
            برای تیم SEO؛ شامل داده، فرصت‌ها، Review، Command و گزارش‌های اجرایی.
          </p></VCard
        ><VCard title="پرتال مشتری"
          ><p class="text-ink-muted leading-7">
            برای کارفرما؛ تمرکز روی نتیجه، تصمیم‌های موردنیاز و گزارش قابل فهم.
          </p></VCard
        ><VCard title="اتصال امن وردپرس"
          ><p class="text-ink-muted leading-7">
            برای همگام‌سازی و اجرای کنترل‌شده، بدون ذخیره کلید AI در وردپرس.
          </p></VCard
        >
      </div>
    </section>
  </MarketingLayout>
</template>
