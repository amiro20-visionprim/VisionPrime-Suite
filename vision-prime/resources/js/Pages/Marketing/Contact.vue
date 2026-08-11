<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'

const phoneDisplay = '۰۹۰۲ ۴۱۵ ۱۶۳۰'
const phoneHref = 'tel:+989024151630'
const whatsappHref = 'https://wa.me/989024151630'
const telegramHref = 'https://t.me/+989024151630'
const email = 'hello@visionprime-suite.ir'

const channels = [
  {
    title: 'تماس تلفنی',
    value: phoneDisplay,
    href: phoneHref,
    note: 'همه‌روزه، ۹ صبح تا ۹ شب',
    icon: '📞',
  },
  {
    title: 'واتس‌اپ',
    value: phoneDisplay,
    href: whatsappHref,
    note: 'پاسخ‌گویی در کمتر از ۲۴ ساعت کاری',
    icon: '💬',
  },
  {
    title: 'تلگرام',
    value: '@' + phoneDisplay.replaceAll(' ', ''),
    href: telegramHref,
    note: 'همان شمارهٔ واتس‌اپ',
    icon: '✈️',
  },
  {
    title: 'بله',
    value: phoneDisplay,
    href: phoneHref,
    note: 'پیام‌رسان بله — همان شماره',
    icon: '🟢',
  },
  {
    title: 'ایمیل',
    value: email,
    href: `mailto:${email}`,
    note: 'برای مدارک، قرارداد و مسائل رسمی',
    icon: '✉️',
  },
]
</script>

<template>
  <Head title="تماس با ما" />
  <MarketingLayout>
    <MarketingPageHero
      title="همین حالا گفت‌وگو را شروع کنیم."
      description="برای مشاوره، جلسهٔ دمو یا سؤال دربارهٔ پلن‌ها، از هر راهی که راحت‌ترید در تماس باشید — پاسخ‌گویی در کمتر از ۲۴ ساعت کاری."
    >
      <template #actions
        ><VButton href="/demo" size="lg">درخواست دموی اختصاصی</VButton></template
      >
    </MarketingPageHero>

    <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <a
          v-for="channel in channels"
          :key="channel.title"
          :href="channel.href"
          target="_blank"
          rel="noopener noreferrer"
          class="rounded-panel border-line bg-surface shadow-panel group block border p-6 transition hover:-translate-y-0.5"
        >
          <div class="text-3xl">{{ channel.icon }}</div>
          <h2 class="text-ink-strong mt-4 text-base font-bold">{{ channel.title }}</h2>
          <p class="text-brand-700 mt-2 text-lg font-bold group-hover:underline" dir="ltr">
            {{ channel.value }}
          </p>
          <p class="text-ink-muted mt-2 text-sm leading-6">{{ channel.note }}</p>
        </a>

        <div class="rounded-panel border-line bg-surface border p-6">
          <div class="text-3xl">📋</div>
          <h2 class="text-ink-strong mt-4 text-base font-bold">برای مسائل رسمی</h2>
          <div class="mt-3 space-y-3">
            <div class="flex items-center justify-between gap-3">
              <span class="text-ink-muted text-sm">تلفن ثابت</span>
              <VBadge tone="info">به‌زودی</VBadge>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-ink-muted text-sm">نماد اعتماد الکترونیکی</span>
              <VBadge tone="info">به‌زودی</VBadge>
            </div>
          </div>
          <p class="text-ink-muted mt-4 text-xs leading-5">
            تا زمان ثبت رسمی، همهٔ ارتباطات از طریق کانال‌های بالا انجام می‌شود.
          </p>
        </div>
      </div>

      <div class="rounded-panel bg-brand-900 mt-10 flex flex-col items-center gap-4 px-6 py-10 text-center text-white sm:px-10">
        <p class="font-display text-xl font-bold leading-relaxed sm:text-2xl">
          ترجیح می‌دهید اول جواب سؤال‌هایتان را بگیرید؟
        </p>
        <p class="text-brand-100 max-w-xl leading-7">
          در جلسهٔ دموی رایگان، وضعیت سایت خودتان را بررسی می‌کنیم و به همهٔ سؤال‌های شما دربارهٔ
          قیمت، امنیت و زمان پیاده‌سازی پاسخ می‌دهیم.
        </p>
        <VButton href="/demo" size="lg" variant="secondary" class="mt-2"
          >درخواست دموی اختصاصی</VButton
        >
      </div>
    </section>
  </MarketingLayout>
</template>
