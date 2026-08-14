<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

const productLinks = [
  { label: 'معرفی محصول', href: '/product' },
  { label: 'قابلیت‌ها', href: '/features' },
  { label: 'قیمت‌گذاری', href: '/pricing' },
]

const audienceLinks = [
  { label: 'آژانس‌های SEO', href: '/for-agencies' },
  { label: 'فروشگاه‌های اینترنتی', href: '/for-ecommerce' },
  { label: 'کلینیک‌ها', href: '/for-clinics' },
  { label: 'مراکز آموزشی', href: '/for-education' },
  { label: 'سفر و هتلداری', href: '/for-hospitality' },
]

const companyLinks = [
  { label: 'امنیت', href: '/security' },
  { label: 'درباره ما', href: '/about' },
  { label: 'تماس با ما', href: '/contact' },
]

const contactLinks = [
  { label: '۰۹۰۲۴۱۵۱۶۳۰', href: 'tel:+989024151630' },
  { label: 'واتس‌اپ', href: 'https://wa.me/989024151630' },
  { label: 'تلگرام', href: 'https://t.me/+989024151630' },
  { label: 'hello@visionprime-suite.ir', href: 'mailto:hello@visionprime-suite.ir' },
]
</script>

<template>
  <footer class="border-line bg-surface border-t">
    <div
      class="mx-auto grid max-w-7xl gap-10 px-5 py-12 sm:px-8 md:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1fr_1.1fr] lg:px-10"
    >
      <div>
        <Link href="/" class="text-ink-strong inline-flex items-center gap-2.5">
          <span
            class="rounded-ui bg-brand-700 flex size-9 items-center justify-center text-sm font-bold text-white"
            >VP</span
          >
          <span class="font-display text-lg font-bold">Vision Prime</span>
        </Link>
        <p class="text-ink-muted mt-4 max-w-sm leading-7">
          پلتفرم هوشمند عملیات، کنترل تغییرات و گزارش‌دهی SEO برای سایت‌های وردپرسی.
        </p>
      </div>
      <div>
        <h2 class="text-ink-strong text-sm font-bold">محصول</h2>
        <ul class="mt-4 space-y-3 text-sm">
          <li v-for="item in productLinks" :key="item.href">
            <Link :href="item.href" class="text-ink hover:text-brand-700">{{ item.label }}</Link>
          </li>
        </ul>
      </div>
      <div>
        <h2 class="text-ink-strong text-sm font-bold">برای تیم‌ها</h2>
        <ul class="mt-4 space-y-3 text-sm">
          <li v-for="item in audienceLinks" :key="item.href">
            <Link :href="item.href" class="text-ink hover:text-brand-700">{{ item.label }}</Link>
          </li>
        </ul>
      </div>
      <div>
        <h2 class="text-ink-strong text-sm font-bold">Vision Prime</h2>
        <ul class="mt-4 space-y-3 text-sm">
          <li v-for="item in companyLinks" :key="item.href">
            <Link :href="item.href" class="text-ink hover:text-brand-700">{{ item.label }}</Link>
          </li>
        </ul>
      </div>
      <div>
        <h2 class="text-ink-strong text-sm font-bold">در تماس باشید</h2>
        <ul class="mt-4 space-y-3 text-sm">
          <li v-for="item in contactLinks" :key="item.label">
            <a
              :href="item.href"
              target="_blank"
              rel="noopener noreferrer"
              class="text-ink hover:text-brand-700"
              dir="ltr"
              >{{ item.label }}</a
            >
          </li>
        </ul>
        <p class="text-ink-muted mt-4 text-xs leading-5">
          پاسخ‌گویی در کمتر از ۲۴ ساعت کاری
        </p>
      </div>
    </div>
    <div class="border-line border-t">
      <div
        class="text-ink-muted mx-auto flex max-w-7xl flex-col gap-2 px-5 py-5 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10"
      >
        <span>© {{ new Date().getFullYear() }} Vision Prime. همه حقوق محفوظ است.</span>
        <span>ساخته‌شده برای عملیات SEO حرفه‌ای</span>
      </div>
    </div>
  </footer>
</template>
