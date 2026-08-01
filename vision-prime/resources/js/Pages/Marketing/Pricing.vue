<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import MarketingPageHero from '@/marketing/components/MarketingPageHero.vue'
import MarketingLayout from '@/marketing/layouts/MarketingLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'

const plans = [
  {
    title: 'Agency',
    description: 'برای آژانس‌هایی با چند مشتری و فرآیند استاندارد عملیات SEO.',
    items: ['مدیریت مشتری، پروژه و سایت', 'GSC و تحلیل فرصت‌ها', 'پرتال مشتری و گزارش‌ها'],
  },
  {
    title: 'Professional',
    description: 'برای تیم‌های SEO با نیاز به Review، AI Workflow و عملیات کنترل‌شده.',
    items: ['همه امکانات Agency', 'گردش‌کار تأیید و AI Gateway', 'سیاست خودکارسازی هر سایت'],
  },
  {
    title: 'Enterprise',
    description: 'برای سازمان‌های بزرگ با نیاز به Private Deployment و اتوماسیون پیشرفته.',
    items: [
      'Private deployment اختیاری',
      'Policyهای Enterprise و اتوماسیون R3',
      'پشتیبانی و استقرار اختصاصی',
    ],
  },
]
</script>

<template>
  <Head title="قیمت‌گذاری" />
  <MarketingLayout>
    <MarketingPageHero
      title="قیمت‌گذاری متناسب با تعداد سایت، سطح عملیات و مدل استقرار شما."
      description="برای حفظ دقت در Scope، قیمت و پیکربندی نهایی در جلسه دمو بر اساس سایت‌ها، اعضای تیم و سطح خودکارسازی تعیین می‌شود."
    />
    <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
      <div class="grid gap-5 lg:grid-cols-3">
        <VCard
          v-for="plan in plans"
          :key="plan.title"
          :title="plan.title"
          :description="plan.description"
          ><ul class="text-ink space-y-3 text-sm leading-6">
            <li v-for="item in plan.items" :key="item" class="flex gap-2">
              <span class="text-success-600">●</span>{{ item }}
            </li>
          </ul>
          <VButton
            href="/demo"
            class="mt-7 w-full"
            :variant="plan.title === 'Professional' ? 'primary' : 'secondary'"
            >دریافت پیشنهاد</VButton
          ></VCard
        >
      </div>
    </section>
  </MarketingLayout>
</template>
