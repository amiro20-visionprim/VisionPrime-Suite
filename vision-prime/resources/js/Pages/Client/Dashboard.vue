<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

defineProps<{
  growthSummary: null | {
    priority_money_pages: number
    high_conversion_risks: number
    recommendations: number
  }
}>()

import ClientSummaryCard from '@/client/components/ClientSummaryCard.vue'
import PortalSectionHeader from '@/client/components/PortalSectionHeader.vue'
import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
</script>

<template>
  <Head title="پرتال مشتری" />
  <ClientPortalLayout>
    <header>
      <p class="text-brand-700 text-sm font-bold tracking-wide">VISION PRIME CLIENT PORTAL</p>
      <h1 class="text-page-title font-display text-ink-strong mt-3 font-bold">نمای کلی رشد سایت</h1>
      <p class="text-ink-muted mt-3 max-w-2xl leading-7">
        خلاصه‌ای از وضعیت، کارهای انجام‌شده و تصمیم‌هایی که برای ادامه مسیر نیاز هستند.
      </p>
    </header>
    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <ClientSummaryCard
        title="وضعیت دیده‌شدن"
        value="—"
        description="پس از اتصال سرچ کنسول نمایش داده می‌شود."
        status-label="در انتظار داده"
        status-tone="info"
      /><ClientSummaryCard
        title="صفحه‌های اولویت‌دار رشد"
        :value="growthSummary ? String(growthSummary.priority_money_pages) : '—'"
        description="صفحه‌هایی که برای بهبود رشد و تبدیل در اولویت هستند."
      /><ClientSummaryCard
        title="ریسک‌های مهم تبدیل"
        :value="growthSummary ? String(growthSummary.high_conversion_risks) : '—'"
        description="موارد مهمی که می‌توانند مانع تبدیل بازدیدکننده به مشتری شوند."
      /><ClientSummaryCard
        title="اقدام‌های پیشنهادی"
        :value="growthSummary ? String(growthSummary.recommendations) : '—'"
        description="اقدام‌های خلاصه و قابل بررسی برای رشد سایت."
      />
    </section>
    <section class="mt-10">
      <PortalSectionHeader
        title="اولویت‌های این دوره"
        description="به‌محض آماده‌شدن داده‌های سایت، مهم‌ترین فرصت‌ها و گام بعدی در این بخش نمایش داده می‌شوند."
      />
      <div class="mt-5">
        <VEmptyState
          title="تحلیل سایت هنوز شروع نشده است"
          description="تیم شما پس از اتصال منابع داده، اولویت‌ها و توصیه‌های قابل فهم را در اینجا منتشر می‌کند."
        />
      </div>
    </section>
    <section class="mt-10 grid gap-5 lg:grid-cols-2">
      <VCard
        title="آخرین گزارش"
        description="گزارش‌های مدیریتی و روند اثر اقدامات در این قسمت در دسترس خواهند بود."
        ><p class="text-ink-muted text-sm leading-7">
          هنوز گزارشی برای مشاهده منتشر نشده است.
        </p></VCard
      ><VCard
        title="فعالیت‌های اخیر"
        description="کارهای مهم انجام‌شده به‌جای داده‌های فنی خام، به‌صورت خلاصه و قابل پیگیری نمایش داده می‌شوند."
        ><p class="text-ink-muted text-sm leading-7">هنوز فعالیتی برای نمایش وجود ندارد.</p></VCard
      >
    </section>
  </ClientPortalLayout>
</template>
