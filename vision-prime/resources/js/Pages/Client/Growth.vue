<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import { tips } from '@/lib/tips'
import VAreaChart from '@/shared/ui/VAreaChart.vue'
import VBarChart from '@/shared/ui/VBarChart.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VGuideTip from '@/shared/ui/VGuideTip.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VStatCard, { type StatTrend } from '@/shared/ui/VStatCard.vue'
import type { IconName, IconTone } from '@/shared/ui/VIcon.vue'

interface GrowthSummary {
  priority_money_pages: number
  high_conversion_risks: number
  recommendations: number
}

interface ClientOpportunity {
  id: number
  type: string
  score: number
  explanation: string
  site_name: string
}

interface TrendPoint {
  date: string
  clicks: number
  impressions: number
  position: number
}

interface KpiMetric {
  value: number | null
  delta: number | null
}

interface Kpis {
  clicks: KpiMetric
  impressions: KpiMetric
  position: KpiMetric
}

const props = defineProps<{
  growthSummary: GrowthSummary | null
  opportunities: ClientOpportunity[]
  trend: TrendPoint[]
  kpis: Kpis | null
}>()

const faNum = (value: number): string =>
  new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 1 }).format(value)

const typeLabels: Record<string, string> = {
  conversion_boost: 'بهبود تبدیل',
  ctr_gap: 'جذابیت بیشتر در گوگل',
  keyword_opportunity: 'فرصت کلیدواژه',
  content_gap: 'شکاف محتوا',
  cannibalization: 'هم‌خواری کلیدواژه',
  revenue_opportunity: 'فرصت درآمدی',
}

function trendOf(delta: number | null): StatTrend {
  if (delta === null || Math.abs(delta) < 1) return 'flat'
  return delta > 0 ? 'up' : 'down'
}

function deltaLabel(delta: number | null): string {
  if (delta === null) return 'دادهٔ کافی برای مقایسه نیست'
  const sign = delta > 0 ? '+' : ''
  return `${sign}${faNum(delta)}٪ نسبت به دو هفتهٔ قبل`
}

function positionTrend(delta: number | null): StatTrend {
  if (delta === null || Math.abs(delta) < 0.3) return 'flat'
  return delta > 0 ? 'up' : 'down'
}

function positionLabel(delta: number | null): string {
  if (delta === null) return 'دادهٔ کافی برای مقایسه نیست'
  if (Math.abs(delta) < 0.3) return 'بدون تغییر محسوس'
  return delta > 0 ? 'جایگاه بهتری در گوگل' : 'جایگاه کمی افت کرده'
}

const kpiCards = computed(() => [
  {
    label: 'بازدید از گوگل',
    value: props.kpis?.clicks.value ?? 0,
    icon: 'trend-up' as IconName,
    iconTone: 'brand' as IconTone,
    hint: tips.clicks,
    trend: trendOf(props.kpis?.clicks.delta ?? null),
    trendLabel: deltaLabel(props.kpis?.clicks.delta ?? null),
  },
  {
    label: 'نمایش در گوگل',
    value: props.kpis?.impressions.value ?? 0,
    icon: 'eye' as IconName,
    iconTone: 'violet' as IconTone,
    hint: tips.impressions,
    trend: trendOf(props.kpis?.impressions.delta ?? null),
    trendLabel: deltaLabel(props.kpis?.impressions.delta ?? null),
  },
  {
    label: 'رتبهٔ متوسط در گوگل',
    value: props.kpis?.position.value ?? 0,
    icon: 'chart-bar' as IconName,
    iconTone: 'success' as IconTone,
    hint: tips.position,
    trend: positionTrend(props.kpis?.position.delta ?? null),
    trendLabel: positionLabel(props.kpis?.position.delta ?? null),
  },
])

const clickPoints = computed(() =>
  props.trend.map((point) => ({
    label: formatJalaliDate(point.date).split('/')[2] ?? '',
    value: point.clicks,
  })),
)

const impressionBars = computed(() =>
  props.trend.map((point, index) => ({
    label:
      index % 5 === 0 || index === props.trend.length - 1
        ? (formatJalaliDate(point.date).split('/')[2] ?? '')
        : '',
    value: point.impressions,
  })),
)
</script>

<template>
  <Head title="رشد من | پرتال مشتری" />
  <ClientPortalLayout>
    <VPageHeader
      title="رشد من"
      description="چقدر سایت شما در گوگل دیده می‌شود و چه روندی دارد — به زبان ساده."
    />

    <section v-if="props.kpis" v-stagger class="mt-8 grid gap-4 sm:grid-cols-3">
      <VStatCard
        v-for="card in kpiCards"
        :key="card.label"
        :label="card.label"
        :value="card.value"
        :icon="card.icon"
        :icon-tone="card.iconTone"
        :hint="card.hint"
        :trend="card.trend"
        :trend-label="card.trendLabel"
      />
    </section>
    <VEmptyState
      v-if="!props.kpis"
      class="mt-8"
      title="داده‌ای برای نمایش نیست"
      description="پس از اتصال سرچ کنسول، روند رشد سایت شما اینجا نمایش داده می‌شود."
    />

    <section v-if="props.trend.length" class="mt-8 grid gap-5 lg:grid-cols-2">
      <VCard
        title="بازدید روزانه از گوگل"
        description="چند بار در هر روز کاربران از گوگل وارد سایت شما شده‌اند."
      >
        <div class="flex items-center gap-2">
          <VGuideTip :text="tips.clicks" />
        </div>
        <div class="mt-4">
          <VAreaChart
            :points="clickPoints"
            :height="200"
            aria-label="نمودار بازدید روزانه از گوگل"
          />
        </div>
      </VCard>

      <VCard
        title="نمایش روزانه در گوگل"
        description="چند بار سایت شما در نتایج جستجو نشان داده شده است — حتی بدون کلیک."
      >
        <div class="flex items-center gap-2">
          <VGuideTip :text="tips.impressions" />
        </div>
        <div class="mt-4">
          <VBarChart :data="impressionBars" :height="190" aria-label="نمودار نمایش روزانه در گوگل" />
        </div>
      </VCard>
    </section>

    <section class="mt-10">
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <h2 class="text-ink-strong text-lg font-bold">فرصت‌های مهم این بازه</h2>
          <VGuideTip :text="tips.opportunities" />
        </div>
        <Link
          href="/client/opportunities"
          class="text-brand-700 hover:text-brand-800 text-sm font-semibold"
          >مشاهده همه ←</Link
        >
      </div>
      <div v-if="props.opportunities.length" class="mt-4 space-y-3">
        <VCard v-for="opportunity in props.opportunities" :key="opportunity.id">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <p class="text-ink-strong font-semibold">
                  {{ typeLabels[opportunity.type] ?? opportunity.type }}
                </p>
                <span class="text-ink-muted text-xs">{{ opportunity.site_name }}</span>
              </div>
              <p class="text-ink-muted mt-1.5 text-sm leading-6">{{ opportunity.explanation }}</p>
            </div>
            <span
              class="rounded-ui bg-warning-50 text-warning-700 px-2.5 py-1 text-xs font-bold"
              >امتیاز {{ faNum(opportunity.score) }} از ۱۰۰</span
            >
          </div>
        </VCard>
      </div>
      <VEmptyState
        v-else
        class="mt-4"
        title="فرصت جدیدی شناسایی نشده است"
        description="به محض تحلیل داده‌های سایت، مهم‌ترین فرصت‌ها در اینجا نمایش داده می‌شوند."
      />
    </section>
  </ClientPortalLayout>
</template>
