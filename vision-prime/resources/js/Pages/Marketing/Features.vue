<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'

const features = [
  ['SEO Intelligence', 'تشخیص نیت، اتصال Query به صفحه و فرصت‌های رشد قابل توضیح.'],
  [
    'Revenue Opportunity Scoring',
    'رتبه‌بندی فرصت‌ها با توجه به داده، intent، CTR gap و اهمیت کسب‌وکاری.',
  ],
  ['Money Page Optimizer', 'تمرکز روی صفحه‌هایی که بیشترین ظرفیت اثر تجاری را دارند.'],
  ['Conversion Risk Detector', 'شناسایی ریسک‌های محتوایی و تبدیل با شدت و توصیه مشخص.'],
  ['GSC Integration', 'دریافت و اتصال داده‌های Query و Page از سرچ کنسول.'],
  ['AI Workflow', 'خروجی AI نسخه‌دار، قابل بررسی و بدون انتشار مستقیم.'],
  ['Secure Commands', 'اجرای کنترل‌شده تغییرات مجاز با HMAC، expiry و rollback.'],
  ['Client Reporting', 'گزارش مدیریتی، impact timeline و گام‌های بعدی برای مشتری.'],
]
</script>

<template>
  <Head title="قابلیت‌ها" />
  <MarketingLayout>
    <MarketingPageHero
      title="قابلیت‌هایی که تیم SEO را از تحلیل تا نتیجه همراهی می‌کنند."
      description="هر ماژول برای یک نقطه مشخص از عملیات رشد طراحی شده و در یک جریان مشترک کار می‌کند."
    />
    <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
      <SectionHeading eyebrow="PLATFORM MODULES" title="هوش، کنترل و گزارش در کنار هم." />
      <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        <VCard v-for="feature in features" :key="feature[0]" :title="feature[0]"
          ><p class="text-ink-muted leading-7">{{ feature[1] }}</p></VCard
        >
      </div>
      <div class="mt-12 text-center">
        <VButton href="/demo" size="lg">بررسی سناریوی تیم شما در دمو</VButton>
      </div>
    </section>
  </MarketingLayout>
</template>
