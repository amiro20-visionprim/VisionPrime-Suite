<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VStatCard from '@/shared/ui/VStatCard.vue'
import VAreaChart, { type AreaPoint } from '@/shared/ui/VAreaChart.vue'
import VBarChart from '@/shared/ui/VBarChart.vue'

interface Kpis {
  orgs_active: number
  orgs_trialing: number
  clients_total: number
  sites_total: number
  sites_connected: number
  revenue_month: number
  tokens_month: number
  reviews_pending: number
  plans_count: number
}

interface TrendPoint {
  date: string
  orgs_active: number
  clients_total: number
  sites_connected: number
  commands_executed: number
  tokens_in: number
  tokens_out: number
}

interface PlatformEvent {
  action: string
  organization_id: number | null
  actor_id: number | null
  occurred_at: string
}

interface PendingDecision {
  id: number
  type: string
  severity: string
  payload: Record<string, unknown>
  organization_id: number | null
  created_at: string
}

interface TriageSummary {
  source: 'ai' | 'rule' | 'none'
  summary: string
  priority: { type: string; severity: string; organization_id: number | null; created_at: string }[]
}

defineProps<{
  kpis: Kpis
  trend: TrendPoint[]
  recentEvents: PlatformEvent[]
  eventLabels: Record<string, string>
  pendingDecisions: PendingDecision[]
  triageSummary: TriageSummary
}>()

const decisionLabels: Record<string, string> = {
  'payment.failed': 'پرداخت ناموفق',
  'review.awaiting': 'پیش‌نویس در انتظار بررسی',
  'command.awaiting': 'دستور در انتظار تأیید',
  'subscription.expiring': 'اشتراک در حال انقضا',
  'subscription.past_due': 'پرداخت معوق',
  'site.disconnected': 'سایت بدون اتصال',
  'ai.cost_spike': 'مصرف AI نزدیک سقف',
  'job.failure': 'خطای job',
}

const severityTone = (severity: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' =>
  severity === 'critical' ? 'danger' : severity === 'warning' ? 'warning' : 'info'

const orgAreaPoints = (trend: TrendPoint[]): AreaPoint[] =>
  trend.map((t) => ({ label: t.date.slice(5), value: t.orgs_active }))

const revenueBars = (trend: TrendPoint[]): { label: string; value: number }[] =>
  trend.slice(-14).map((t) => ({ label: t.date.slice(5), value: t.sites_connected }))

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)
</script>

<template>
  <Head title="داشبورد فرماندهی" />
  <PlatformLayout>
    <VPageHeader
      title="داشبورد فرماندهی پلتفرم"
      description="رصد یکپارچهٔ کل اکوسیستم: سازمان‌ها، مشتریان، سایت‌ها، درآمد و هوش مصنوعی — فقط تصمیم‌های واقعی جلوی شما."
    />

    <!-- خلاصهٔ هوشمند Triage — F-03 -->
    <div v-if="triageSummary.source !== 'none'" class="mt-6 flex items-start gap-3 rounded-2xl border border-indigo-200/60 bg-indigo-50/60 p-5">
      <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600/15 text-lg">🧠</span>
      <div>
        <h2 class="text-ink-strong font-display text-sm font-bold">
          خلاصهٔ هوشمند تصمیم‌ها
          <span class="text-ink-muted font-sans text-xs font-normal">
            {{ triageSummary.source === 'ai' ? '· توسط هوش مصنوعی' : '· اولویت‌بندی خودکار' }}
          </span>
        </h2>
        <p class="text-ink-strong mt-1 text-sm leading-relaxed">{{ triageSummary.summary }}</p>
      </div>
    </div>

    <!-- صف تصمیم — قلب استثنامحور -->
    <div
      v-if="pendingDecisions.length"
      class="border-danger-200/60 bg-danger-50/60 mt-6 rounded-2xl border p-5"
    >
      <div class="flex items-center justify-between gap-3">
        <div>
          <h2 class="text-ink-strong font-display text-lg font-bold">
            🔴 {{ pendingDecisions.length }} تصمیم در انتظار شما
          </h2>
          <p class="text-ink-muted mt-1 text-sm">
            این‌ها چیزهایی هستند که فقط شما می‌توانید تصمیم بگیرید؛ بقیه‌اش خودکار انجام شده است.
          </p>
        </div>
      </div>
      <ul class="mt-4 grid gap-3 lg:grid-cols-2">
        <li
          v-for="decision in pendingDecisions"
          :key="decision.id"
          class="border-line bg-surface rounded-xl border p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <p class="text-ink-strong text-sm font-bold">
              {{ decisionLabels[decision.type] ?? decision.type }}
            </p>
            <VBadge :tone="severityTone(decision.severity)">{{ decision.severity }}</VBadge>
          </div>
          <p class="text-ink-muted mt-1 text-xs" dir="ltr">{{ decision.created_at }}</p>
        </li>
      </ul>
    </div>

    <!-- KPI -->
    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <VStatCard
        label="سازمان‌های فعال"
        :value="kpis.orgs_active"
        icon="building"
        icon-tone="brand"
        hint="سازمان‌های دارای اشتراک فعال یا آزمایشی"
      />
      <VStatCard
        label="مشتریان کل"
        :value="kpis.clients_total"
        icon="users"
        icon-tone="violet"
        hint="تمام مشتریان تمام سازمان‌ها"
      />
      <VStatCard
        label="سایت‌های متصل"
        :value="kpis.sites_connected"
        icon="activity"
        icon-tone="success"
        :hint="`از ${faNum(kpis.sites_total)} سایت ثبت‌شده`"
      />
      <VStatCard
        label="درآمد این ماه"
        :value="kpis.revenue_month"
        icon="chart-bar"
        icon-tone="success"
        hint="تومان — پرداخت‌های موفق ماه جاری"
      />
      <VStatCard
        label="در دورهٔ آزمایشی"
        :value="kpis.orgs_trialing"
        icon="clock"
        icon-tone="warning"
        hint="سازمان‌هایی که هنوز پرداخت نکرده‌اند"
      />
      <VStatCard
        label="مصرف AI این ماه"
        :value="kpis.tokens_month"
        icon="sparkles"
        icon-tone="brand"
        hint="توکن خروجی — تولید محتوا"
      />
      <VStatCard
        label="در انتظار بررسی"
        :value="kpis.reviews_pending"
        icon="user-check"
        icon-tone="warning"
        hint="پیش‌نویس‌ها و بازبینی‌های نیازمند تصمیم"
      />
      <VStatCard
        label="پلن‌های فعال"
        :value="kpis.plans_count"
        icon="shopping-bag"
        icon-tone="neutral"
        hint="تعریف‌شده در کاتالوگ پلن"
      />
    </div>

    <!-- چارت‌ها -->
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <VCard title="سازمان‌های فعال — ۳۰ روز اخیر" description="روند رشد اکوسیستم">
        <VAreaChart :points="orgAreaPoints(trend)" :height="220" />
      </VCard>
      <VCard title="سایت‌های متصل — ۱۴ روز اخیر" description="سلامت اتصال‌ها">
        <VBarChart :data="revenueBars(trend)" :height="220" />
      </VCard>
    </div>

    <!-- رویدادهای اخیر -->
    <VCard class="mt-6" title="رویدادهای اخیر پلتفرم" description="آخرین اکشن‌های مهم کل اکوسیستم">
      <ul v-if="recentEvents.length" class="divide-line divide-y">
        <li
          v-for="(event, index) in recentEvents"
          :key="index"
          class="flex items-center justify-between gap-3 py-3"
        >
          <div>
            <p class="text-ink-strong text-sm font-semibold">
              {{ eventLabels[event.action] ?? event.action }}
            </p>
            <p class="text-ink-muted mt-0.5 text-xs" dir="ltr">{{ event.occurred_at }}</p>
          </div>
          <VBadge tone="info">{{ event.organization_id ? `سازمان #${event.organization_id}` : 'سراسری' }}</VBadge>
        </li>
      </ul>
      <p v-else class="text-ink-muted py-4 text-sm">هنوز رویدادی ثبت نشده است.</p>
    </VCard>
  </PlatformLayout>
</template>
