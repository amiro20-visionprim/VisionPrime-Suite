<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import {
  AlertTriangle,
  Brain,
  FileText,
  Gauge,
  ShieldCheck,
  Sparkles,
  Target,
} from '@lucide/vue'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VButton from '@/shared/ui/VButton.vue'

const features = [
  {
    icon: Brain,
    title: 'SEO Intelligence',
    text: 'تشخیص نیت، اتصال Query به صفحه و فرصت‌های رشد قابل توضیح.',
  },
  {
    icon: Gauge,
    title: 'Revenue Opportunity Scoring',
    text: 'رتبه‌بندی فرصت‌ها با توجه به داده، intent، CTR gap و اهمیت کسب‌وکاری.',
  },
  {
    icon: Target,
    title: 'Money Page Optimizer',
    text: 'تمرکز روی صفحه‌هایی که بیشترین ظرفیت اثر تجاری را دارند.',
  },
  {
    icon: AlertTriangle,
    title: 'Conversion Risk Detector',
    text: 'شناسایی ریسک‌های محتوایی و تبدیل با شدت و توصیه مشخص.',
  },
  {
    icon: Sparkles,
    title: 'GSC Integration',
    text: 'دریافت و اتصال داده‌های Query و Page از سرچ کنسول.',
  },
  {
    icon: Brain,
    title: 'AI Workflow',
    text: 'خروجی AI نسخه‌دار، قابل بررسی و بدون انتشار مستقیم.',
  },
  {
    icon: ShieldCheck,
    title: 'Secure Commands',
    text: 'اجرای کنترل‌شده تغییرات مجاز با HMAC، expiry و rollback.',
  },
  {
    icon: FileText,
    title: 'Client Reporting',
    text: 'گزارش مدیریتی، impact timeline و گام‌های بعدی برای مشتری.',
  },
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
        <div
          v-for="(feature, index) in features"
          :key="feature.title"
          v-reveal="{ delay: (index % 3) * 100 }"
          class="group rounded-2xl border border-line bg-surface p-6 shadow-card transition-all duration-300 hover:-translate-y-1.5 hover:border-brand-200 hover:shadow-panel"
        >
          <span
            class="bg-gradient-brand inline-flex size-11 items-center justify-center rounded-xl text-white shadow-md transition-transform duration-300 group-hover:scale-110"
          >
            <component :is="feature.icon" class="size-5" aria-hidden="true" />
          </span>
          <h3 class="text-ink-strong mt-5 text-lg font-bold">{{ feature.title }}</h3>
          <p class="text-ink-muted mt-2.5 leading-7">{{ feature.text }}</p>
        </div>
      </div>
      <div v-reveal class="mt-12 text-center">
        <VButton href="/demo" size="lg" variant="gradient">بررسی سناریوی تیم شما در دمو</VButton>
      </div>
    </section>
  </MarketingLayout>
</template>
