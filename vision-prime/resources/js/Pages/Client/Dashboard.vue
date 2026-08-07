<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientSummaryCard from '@/client/components/ClientSummaryCard.vue'
import PortalSectionHeader from '@/client/components/PortalSectionHeader.vue'
import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'

interface GrowthSummary {
  priority_money_pages: number
  high_conversion_risks: number
  recommendations: number
}

interface DashboardOpportunity {
  id: number
  type: string
  score: number
  explanation: string
  site_name: string
}

interface DashboardActivity {
  action: string
  label: string
  actor_name: string | null
  occurred_at: string
}

const props = defineProps<{
  growthSummary: GrowthSummary | null
  opportunities: DashboardOpportunity[]
  latestReport: null | {
    id: number
    type: string
    period_start: string
    period_end: string
    content: Record<string, unknown> | null
    published_at: string
  }
  recentActivities: DashboardActivity[]
}>()

const typeLabels: Record<string, string> = {
  conversion_boost: 'بهبود تبدیل',
  ctr_gap: 'شکاف نرخ کلیک',
  keyword_opportunity: 'فرصت کلیدواژه',
  content_gap: 'شکاف محتوا',
  cannibalization: 'هم‌خواری کلیدواژه',
  revenue_opportunity: 'فرصت درآمدی',
}

function reportCount(report: NonNullable<typeof props.latestReport>, key: string): string | null {
  const value = report.content?.[key]
  return typeof value === 'number' ? String(value) : null
}
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
        description="مهم‌ترین فرصت‌هایی که برای رشد سایت در این بازه شناسایی شده‌اند."
      />
      <div v-if="props.opportunities.length" class="mt-5 space-y-3">
        <VCard v-for="opportunity in props.opportunities" :key="opportunity.id">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <p class="text-ink-strong font-semibold">
                  {{ typeLabels[opportunity.type] ?? opportunity.type }}
                </p>
                <VBadge tone="neutral">{{ opportunity.site_name }}</VBadge>
              </div>
              <p class="text-ink-muted mt-1.5 text-sm leading-6">{{ opportunity.explanation }}</p>
            </div>
            <VBadge tone="warning">امتیاز {{ opportunity.score }}</VBadge>
          </div>
        </VCard>
      </div>
      <VEmptyState
        v-else
        class="mt-5"
        title="هنوز فرصتی شناسایی نشده است"
        description="به‌محض تحلیل داده‌های سایت، مهم‌ترین اولویت‌ها اینجا نمایش داده می‌شوند."
      />
    </section>

    <section class="mt-10 grid gap-5 lg:grid-cols-2">
      <VCard
        title="آخرین گزارش"
        description="گزارش‌های مدیریتی و روند اثر اقدامات در این قسمت در دسترس هستند."
      >
        <div v-if="props.latestReport" class="mt-2 space-y-2">
          <p class="text-ink-strong font-semibold">{{ props.latestReport.type }}</p>
          <p class="text-ink-muted text-sm">
            {{ formatJalaliDate(props.latestReport.period_start) }} تا
            {{ formatJalaliDate(props.latestReport.period_end) }}
          </p>
          <p class="text-ink-muted text-sm">
            فرصت‌های باز:
            {{ reportCount(props.latestReport, 'opportunities') ?? '—' }}
            · ریسک‌های مهم:
            {{ reportCount(props.latestReport, 'high_risks') ?? '—' }}
            · اقدام‌ها:
            {{ reportCount(props.latestReport, 'recommendations') ?? '—' }}
          </p>
          <p class="text-ink-muted text-xs">
            منتشرشده در {{ formatJalaliDate(props.latestReport.published_at) }}
          </p>
        </div>
        <p v-else class="text-ink-muted mt-2 text-sm leading-7">
          هنوز گزارشی برای مشاهده منتشر نشده است.
        </p>
      </VCard>

      <VCard
        title="فعالیت‌های اخیر"
        description="کارهای مهم انجام‌شده به‌صورت خلاصه و قابل پیگیری."
      >
        <ul v-if="props.recentActivities.length" class="mt-2 space-y-3">
          <li
            v-for="activity in props.recentActivities"
            :key="`${activity.action}-${activity.occurred_at}`"
            class="flex items-start justify-between gap-3 border-b border-line pb-3 last:border-0 last:pb-0"
          >
            <div>
              <p class="text-ink-strong text-sm font-medium">{{ activity.label }}</p>
              <p class="text-ink-muted mt-0.5 text-xs">{{ activity.actor_name ?? 'سیستم' }}</p>
            </div>
            <p class="text-ink-muted shrink-0 text-xs">
              {{ formatJalaliDate(activity.occurred_at) }}
            </p>
          </li>
        </ul>
        <p v-else class="text-ink-muted mt-2 text-sm leading-7">هنوز فعالیتی برای نمایش وجود ندارد.</p>
      </VCard>
    </section>
  </ClientPortalLayout>
</template>
