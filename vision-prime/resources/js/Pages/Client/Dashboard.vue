<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import { tips } from '@/lib/tips'
import VBadge from '@/shared/ui/VBadge.vue'
import VBarChart from '@/shared/ui/VBarChart.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VGuideTip from '@/shared/ui/VGuideTip.vue'
import VIcon, { type IconName, type IconTone } from '@/shared/ui/VIcon.vue'
import VStatCard, { type StatTrend } from '@/shared/ui/VStatCard.vue'

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

interface PendingDecision {
  id: number
  type: string
  site_name: string
  created_at: string
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
  pendingDecisions: PendingDecision[]
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

const typeIcons: Record<string, { icon: IconName; tone: IconTone }> = {
  conversion_boost: { icon: 'zap', tone: 'success' },
  ctr_gap: { icon: 'search', tone: 'brand' },
  keyword_opportunity: { icon: 'trend-up', tone: 'violet' },
  content_gap: { icon: 'file', tone: 'neutral' },
  cannibalization: { icon: 'ban', tone: 'danger' },
  revenue_opportunity: { icon: 'shopping-bag', tone: 'success' },
}

function reportCount(report: NonNullable<typeof props.latestReport>, key: string): string | null {
  const value = report.content?.[key]
  return typeof value === 'number' ? String(value) : null
}

// —— بنر وضعیت هوشمند ——
interface Banner {
  tone: 'success' | 'warning' | 'neutral'
  icon: IconName
  title: string
  body: string
  action: { label: string; href: string } | null
}

const banner = computed<Banner>(() => {
  if (!props.kpis) {
    return {
      tone: 'neutral',
      icon: 'chart-line',
      title: 'در حال آماده‌سازی داشبورد شما',
      body: 'به محض اتصال سرچ کنسول، تصویر کامل رشد سایت شما اینجا نمایش داده می‌شود.',
      action: null,
    }
  }
  const pendingCount = props.pendingDecisions.length
  if (pendingCount > 0) {
    return {
      tone: 'warning',
      icon: 'bell',
      title: `${faNum(pendingCount)} مورد منتظر تصمیم شماست`,
      body: 'پیشنهادهایی که تیم ما آماده کرده و بدون تأیید شما اعمال نمی‌شود. بررسی سریع آن‌ها به رشد سایت کمک می‌کند.',
      action: { label: 'مشاهده و تصمیم‌گیری', href: '/client/decisions' },
    }
  }
  const delta = props.kpis.clicks.delta
  if (delta !== null && delta >= 1) {
    return {
      tone: 'success',
      icon: 'sparkles',
      title: 'سایت شما در حال رشد است 🎉',
      body: `بازدید از گوگل نسبت به دو هفتهٔ قبل ${faNum(delta)}٪ بیشتر شده است. همین مسیر را ادامه می‌دهیم.`,
      action: { label: 'مشاهده رشد من', href: '/client/growth' },
    }
  }
  if (delta !== null && delta <= -1) {
    return {
      tone: 'warning',
      icon: 'trend-down',
      title: 'چند مورد نیاز به توجه دارد',
      body: `بازدید از گوگل نسبت به دو هفتهٔ قبل ${faNum(Math.abs(delta))}٪ کمتر شده است. پیشنهادهای تیم را در اولویت‌ها ببینید.`,
      action: { label: 'مشاهده اولویت‌ها', href: '/client/opportunities' },
    }
  }
  return {
    tone: 'neutral',
    icon: 'chart-line',
    title: 'سایت شما در وضعیت باثباتی است',
    body: 'در حال جمع‌آوری داده برای یافتن فرصت‌های جدید رشد هستیم. جزئیات را در «رشد من» ببینید.',
    action: { label: 'مشاهده رشد من', href: '/client/growth' },
  }
})

const bannerTone: Record<Banner['tone'], string> = {
  success: 'border-success-200 bg-success-50',
  warning: 'border-warning-200 bg-warning-50',
  neutral: 'border-line-strong bg-surface-muted',
}

// —— کارت‌های KPI ——
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
  {
    label: 'منتظر تأیید شما',
    value: props.pendingDecisions.length,
    icon: 'user-check' as IconName,
    iconTone: 'warning' as IconTone,
    hint: tips['pending-decisions'],
    trend: 'flat' as StatTrend,
    trendLabel: props.pendingDecisions.length ? 'نیاز به بررسی دارد' : 'همه‌چیز بررسی شده',
  },
])

// —— چارت رشد ——
const growthBars = computed(() =>
  props.trend.map((point, index) => ({
    label:
      index % 5 === 0 || index === props.trend.length - 1
        ? (formatJalaliDate(point.date).split('/')[2] ?? '')
        : '',
    value: point.clicks,
  })),
)

// —— فعالیت‌ها ——
function activityIcon(action: string): IconName {
  if (action.startsWith('command')) {
    return action.includes('rollback') ? 'rotate' : 'zap'
  }
  if (action.startsWith('content')) return 'file'
  if (action.startsWith('opportunity')) return 'lightbulb'
  if (action.startsWith('site')) return 'building'
  if (action.startsWith('recommendation')) return 'lightbulb'
  return 'activity'
}

const decisionTypeLabels: Record<string, string> = {
  update_meta_title: 'بهبود عنوان صفحه در گوگل',
  update_content: 'بهبود متن صفحه',
  publish_draft: 'انتشار پیش‌نویس',
  update_canonical: 'اصلاح آدرس اصلی صفحه',
  add_internal_link: 'افزودن لینک داخلی',
  remove_duplicate: 'حذف محتوای تکراری',
  execute_recommendation: 'اعمال پیشنهاد تیم',
}
</script>

<template>
  <Head title="خانه | پرتال مشتری" />
  <ClientPortalLayout>
    <!-- بنر وضعیت هوشمند -->
    <section
      :class="['rounded-panel border flex items-start gap-4 p-5 sm:p-6', bannerTone[banner.tone]]"
      role="status"
    >
      <span
        :class="[
          'rounded-ui flex size-11 shrink-0 items-center justify-center',
          banner.tone === 'success'
            ? 'bg-success-600 text-white'
            : banner.tone === 'warning'
              ? 'bg-warning-600 text-white'
              : 'bg-ink-strong text-white',
        ]"
      >
        <VIcon :name="banner.icon" size="lg" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-ink-strong font-display text-lg font-bold">{{ banner.title }}</p>
        <p class="text-ink-muted mt-1 max-w-2xl text-sm leading-6">{{ banner.body }}</p>
      </div>
      <Link
        v-if="banner.action"
        :href="banner.action.href"
        class="transition-ui rounded-ui bg-ink-strong text-white hover:opacity-90 shrink-0 px-4 py-2 text-sm font-semibold"
      >
        {{ banner.action.label }}
      </Link>
    </section>

    <!-- کارت‌های KPI -->
    <section v-stagger class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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

    <!-- چیزهایی که منتظر تصمیم شماست -->
    <section class="mt-10">
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <h2 class="font-display text-ink-strong text-lg font-bold">منتظر تصمیم شما</h2>
          <VGuideTip :text="tips['pending-decisions']" />
        </div>
        <Link
          href="/client/decisions"
          class="text-brand-700 hover:text-brand-800 text-sm font-semibold"
          >مشاهده همه ←</Link
        >
      </div>
      <div v-if="props.pendingDecisions.length" class="mt-4 space-y-3">
        <VCard v-for="decision in props.pendingDecisions" :key="decision.id">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
              <span class="rounded-ui bg-warning-50 text-warning-600 flex size-9 shrink-0 items-center justify-center">
                <VIcon name="user-check" size="sm" />
              </span>
              <div class="min-w-0">
                <p class="text-ink-strong truncate text-sm font-semibold">
                  {{ decisionTypeLabels[decision.type] ?? 'پیشنهاد تیم' }}
                </p>
                <p class="text-ink-muted mt-0.5 text-xs">
                  {{ decision.site_name }} · درخواست از {{ formatJalaliDate(decision.created_at) }}
                </p>
              </div>
            </div>
            <Link
              href="/client/decisions"
              class="transition-ui rounded-ui bg-brand-700 text-white hover:bg-brand-800 px-4 py-2 text-sm font-semibold"
              >بررسی و تصمیم</Link
            >
          </div>
        </VCard>
      </div>
      <VEmptyState
        v-else
        class="mt-4"
        title="همه‌چیز بررسی شده است"
        description="موردی منتظر تأیید شما نیست. اگر پیشنهاد جدیدی آماده شود، همین‌جا نمایش داده می‌شود."
      >
        <template #icon><VIcon name="check" tone="success" size="lg" /></template>
      </VEmptyState>
    </section>

    <!-- چارت رشد -->
    <section class="mt-10 grid gap-5 lg:grid-cols-3">
      <VCard
        class="lg:col-span-2"
        title="رشد من — بازدید از گوگل"
        description="چند بار کاربران در روزهای اخیر از گوگل وارد سایت شما شده‌اند."
      >
        <div class="flex items-center gap-2">
          <VGuideTip :text="tips.clicks" />
        </div>
        <div v-if="growthBars.length" class="mt-6">
          <VBarChart :data="growthBars" :height="190" aria-label="نمودار بازدید روزانه از گوگل" />
        </div>
        <p v-else class="text-ink-muted mt-4 text-sm leading-7">
          پس از اتصال سرچ کنسول، روند بازدید روزانه اینجا نمایش داده می‌شود.
        </p>
      </VCard>

      <div class="space-y-5">
        <VCard title="آخرین گزارش" description="خلاصهٔ دورهٔ اخیر">
          <div v-if="props.latestReport" class="mt-2 space-y-2">
            <p class="text-ink-strong font-semibold">{{ props.latestReport.type }}</p>
            <p class="text-ink-muted text-sm">
              {{ formatJalaliDate(props.latestReport.period_start) }} تا
              {{ formatJalaliDate(props.latestReport.period_end) }}
            </p>
            <div class="flex flex-wrap gap-2 pt-1">
              <VBadge tone="info">فرصت‌ها: {{ reportCount(props.latestReport, 'opportunities') ?? '—' }}</VBadge>
              <VBadge tone="warning">نقاط خطر: {{ reportCount(props.latestReport, 'high_risks') ?? '—' }}</VBadge>
              <VBadge tone="success">پیشنهادها: {{ reportCount(props.latestReport, 'recommendations') ?? '—' }}</VBadge>
            </div>
            <p class="text-ink-muted text-xs">
              منتشرشده در {{ formatJalaliDate(props.latestReport.published_at) }}
            </p>
          </div>
          <p v-else class="text-ink-muted mt-2 text-sm leading-7">
            هنوز گزارشی برای مشاهده منتشر نشده است.
          </p>
        </VCard>

        <VCard title="وضعیت کلی">
          <div class="mt-2 space-y-3">
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="text-ink-muted">صفحات پول‌ساز در اولویت</span>
              <span class="text-ink-strong font-bold">
                {{ faNum(props.growthSummary?.priority_money_pages ?? 0) }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="text-ink-muted">نقاط خطر مهم</span>
              <span class="text-ink-strong font-bold">
                {{ faNum(props.growthSummary?.high_conversion_risks ?? 0) }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="text-ink-muted">پیشنهادهای تیم</span>
              <span class="text-ink-strong font-bold">
                {{ faNum(props.growthSummary?.recommendations ?? 0) }}
              </span>
            </div>
          </div>
          <p class="text-ink-muted mt-4 border-line border-t pt-3 text-xs leading-5">
            <VIcon name="lightbulb" tone="warning" size="sm" class="ms-1 inline" />
            این اعداد با پیشرفت کار تیم به‌روز می‌شوند.
          </p>
        </VCard>
      </div>
    </section>

    <!-- فرصت‌های پیشنهادی -->
    <section class="mt-10">
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <h2 class="font-display text-ink-strong text-lg font-bold">کجا می‌توانیم بهتر شویم؟</h2>
          <VGuideTip :text="tips.opportunities" />
        </div>
        <Link
          href="/client/opportunities"
          class="text-brand-700 hover:text-brand-800 text-sm font-semibold"
          >مشاهده همه ←</Link
        >
      </div>
      <div v-if="props.opportunities.length" class="mt-4 grid gap-4 md:grid-cols-3">
        <VCard v-for="opportunity in props.opportunities" :key="opportunity.id" class="transition-ui hover:-translate-y-0.5">
          <div class="flex items-start justify-between gap-3">
            <span
              :class="[
                'rounded-ui flex size-10 items-center justify-center',
                typeIcons[opportunity.type]?.tone === 'success'
                  ? 'bg-success-50'
                  : typeIcons[opportunity.type]?.tone === 'danger'
                    ? 'bg-danger-50'
                    : typeIcons[opportunity.type]?.tone === 'violet'
                      ? 'bg-violet-50'
                      : 'bg-brand-50',
              ]"
            >
              <VIcon
                :name="typeIcons[opportunity.type]?.icon ?? 'lightbulb'"
                :tone="typeIcons[opportunity.type]?.tone ?? 'brand'"
                size="lg"
              />
            </span>
            <VBadge tone="warning">امتیاز {{ faNum(opportunity.score) }} از ۱۰۰</VBadge>
          </div>
          <p class="text-ink-strong mt-4 text-sm font-bold">
            {{ typeLabels[opportunity.type] ?? opportunity.type }}
          </p>
          <p class="text-ink-muted mt-1.5 text-xs leading-6">{{ opportunity.explanation }}</p>
        </VCard>
      </div>
      <VEmptyState
        v-else
        class="mt-4"
        title="هنوز فرصتی شناسایی نشده است"
        description="به‌محض تحلیل داده‌های سایت، مهم‌ترین اولویت‌ها اینجا نمایش داده می‌شوند."
      />
    </section>

    <!-- فعالیت‌های اخیر -->
    <section class="mt-10">
      <h2 class="font-display text-ink-strong text-lg font-bold">آخرین فعالیت‌ها</h2>
      <VCard v-if="props.recentActivities.length" class="mt-4">
        <ul class="divide-line divide-y">
          <li
            v-for="activity in props.recentActivities"
            :key="`${activity.action}-${activity.occurred_at}`"
            class="flex items-start gap-3 py-3"
          >
            <span class="rounded-ui bg-surface-muted text-ink-muted flex size-9 shrink-0 items-center justify-center">
              <VIcon :name="activityIcon(activity.action)" size="sm" />
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-ink-strong text-sm font-medium">{{ activity.label }}</p>
              <p class="text-ink-muted mt-0.5 text-xs">{{ activity.actor_name ?? 'سیستم' }}</p>
            </div>
            <p class="text-ink-muted shrink-0 text-xs">
              {{ formatJalaliDate(activity.occurred_at) }}
            </p>
          </li>
        </ul>
      </VCard>
      <VEmptyState
        v-else
        class="mt-4"
        title="هنوز فعالیتی ثبت نشده است"
        description="کارهای مهم تیم به‌صورت خلاصه اینجا نمایش داده می‌شوند."
      />
    </section>
  </ClientPortalLayout>
</template>
