<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VBarChart from '@/shared/ui/VBarChart.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VStatCard from '@/shared/ui/VStatCard.vue'
import type { GscAccount, GscImportRun, GscProperty } from '@/types/gsc'

const props = defineProps<{
  accounts: GscAccount[]
  properties: GscProperty[]
  runs: GscImportRun[]
  flash?: { status?: string }
  kpis?: {
    totalClicks: number
    totalImpressions: number
    avgCtr: number
    avgPosition: number
    clicksDelta: number | null
    impressionsDelta: number | null
  }
  topPages?: { url: string; clicks: number; impressions: number; ctr: number; position: number }[]
  topQueries?: { query: string; clicks: number; impressions: number; ctr: number; position: number }[]
  trend?: { date: string; clicks: number; impressions: number }[]
}>()

const propertyId = ref(props.properties[0] ? String(props.properties[0].id) : '')
const days = ref('28')
const importing = ref(false)
const analyzing = ref(false)

const hasProperties = computed(() => props.properties.length > 0)
const hasData = computed(() => (props.kpis?.totalClicks ?? 0) > 0)

const dateRange = computed(() => {
  const end = new Date()
  const start = new Date()
  start.setDate(end.getDate() - Number(days.value))
  const fmt = (d: Date) => d.toISOString().slice(0, 10)
  return { start: fmt(start), end: fmt(end) }
})

const trendBars = computed(() =>
  (props.trend ?? []).map((point, index) => ({
    label: index % 5 === 0 || index === (props.trend?.length ?? 0) - 1 ? point.date.slice(5) : '',
    value: point.clicks,
  })),
)

function startImport() {
  if (!propertyId.value) return
  importing.value = true
  router.post(
    '/app/gsc/import',
    {
      gsc_property_id: Number(propertyId.value),
      date_start: dateRange.value.start,
      date_end: dateRange.value.end,
    },
    { preserveScroll: true, onFinish: () => { importing.value = false } },
  )
}

function startAnalysis() {
  if (!propertyId.value) return
  analyzing.value = true
  router.post(
    '/app/gsc/analyze',
    { gsc_property_id: Number(propertyId.value) },
    { preserveScroll: true, onFinish: () => { analyzing.value = false } },
  )
}

const faNum = (n: number) => new Intl.NumberFormat('fa-IR').format(n)
const sign = (n: number) => (n > 0 ? `+${n}` : String(n))
const formatUrl = (url: string) => { try { return new URL(url).pathname } catch { return url } }
</script>

<template>
  <Head title="سرچ کنسول | سوئیت" />
  <AppLayout>
    <VPageHeader
      title="سرچ کنسول گوگل"
      description="داده‌های جستجو، عملکرد صفحات و فرصت‌های رشد — همه در یک جا."
    >
      <template #actions>
        <VButton href="/app/gsc/connect">اتصال حساب Google</VButton>
      </template>
    </VPageHeader>

    <VAlert v-if="props.flash?.status" class="mb-5" tone="success">{{ props.flash.status }}</VAlert>

    <!-- وضعیت اتصال -->
    <div v-if="!accounts.length" class="mt-8">
      <VEmptyState
        title="حساب Google متصل نیست"
        description="برای مشاهده داده‌های جستجو، ابتدا حساب Google خود را به سرچ کنسول متصل کنید."
        action-label="اتصال حساب Google"
        @action="$inertia.visit('/app/gsc/connect')"
      />
    </div>

    <!-- KPI‌ها -->
    <section v-if="hasData" v-stagger class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <VStatCard
        label="کلیک (۲۸ روز)"
        :value="kpis?.totalClicks ?? 0"
        icon="cursor-click"
        icon-tone="brand"
        hint="تعداد کلیک کاربران روی نتایج جستجو"
        :trend="kpis?.clicksDelta !== null ? (kpis!.clicksDelta! >= 0 ? 'up' : 'down') : 'flat'"
        :trend-label="kpis?.clicksDelta !== null ? `${sign(kpis!.clicksDelta!)} نسبت به دوره قبل` : 'بدون داده'"
      />
      <VStatCard
        label="نمایش (۲۸ روز)"
        :value="kpis?.totalImpressions ?? 0"
        icon="eye"
        icon-tone="violet"
        hint="تعداد نمایش سایت در نتایج جستجو"
        :trend="kpis?.impressionsDelta !== null ? (kpis!.impressionsDelta! >= 0 ? 'up' : 'down') : 'flat'"
        :trend-label="kpis?.impressionsDelta !== null ? `${sign(kpis!.impressionsDelta!)} نسبت به دوره قبل` : 'بدون داده'"
      />
      <VStatCard
        label="CTR متوسط"
        :value="`${(kpis?.avgCtr ?? 0).toFixed(1)}%`"
        icon="chart-bar"
        icon-tone="success"
        hint="نرخ کلیک به نمایش — هرچه بالاتر بهتر"
      />
      <VStatCard
        label="جایگاه متوسط"
        :value="(kpis?.avgPosition ?? 0).toFixed(1)"
        icon="trending-up"
        icon-tone="warning"
        hint="جایگاه متوسط در نتایج جستجو — هرچه کمتر بهتر"
      />
    </section>

    <!-- نمودار روند -->
    <VCard
      v-if="hasData && trendBars.length"
      class="mt-8"
      title="روند کلیک روزانه"
      :description="`دادهٔ ${days} روز اخیر — مجموع کلیک همهٔ صفحات`"
    >
      <VBarChart :data="trendBars" :height="180" aria-label="نمودار روند کلیک روزانه" />
    </VCard>

    <!-- کنترل ایمپورت -->
    <VCard class="mt-8" title="همگام‌سازی داده" description="ایمپورت خودکار داده‌های سرچ کنسول برای تحلیل و گزارش‌دهی.">
      <div class="flex flex-wrap items-end gap-4">
        <div class="w-64">
          <select
            v-model="propertyId"
            class="rounded-ui border-line bg-surface text-ink-strong w-full border px-3 py-2.5 text-sm"
          >
            <option value="" disabled>انتخاب سایت...</option>
            <option v-for="p in properties" :key="p.id" :value="String(p.id)">
              {{ p.site_name }} — {{ p.property_uri }}
            </option>
          </select>
        </div>
        <VButton :disabled="!hasProperties || importing" :loading="importing" @click="startImport">
          🔄 ایمپورت خودکار
        </VButton>
        <VButton
          variant="secondary"
          :disabled="!hasProperties || analyzing"
          :loading="analyzing"
          @click="startAnalysis"
        >
          📊 تحلیل رشد
        </VButton>
      </div>
      <p v-if="!hasProperties" class="text-ink-muted mt-3 text-sm">
        ⚠️ ابتدا یک ملک سرچ کنسول انتخاب کنید.
      </p>
      <p class="text-ink-muted mt-3 text-xs leading-6">
        💡 ایمپورت خودکار هر ۲۴ ساعت اجرا می‌شود. پس از هر ایمپورت، تحلیل رشد نیز خودکار اجرا می‌شود.
      </p>
    </VCard>

    <!-- صفحات و کوئری‌های برتر -->
    <div v-if="hasData" class="mt-8 grid gap-5 lg:grid-cols-2">
      <VCard title="🏆 ۱۰ صفحهٔ برتر" description="بیشترین کلیک در ۲۸ روز اخیر">
        <div v-if="topPages?.length" class="space-y-2">
          <div v-for="(page, i) in topPages" :key="page.url" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 hover:bg-surface-muted">
            <div class="min-w-0 flex-1">
              <p class="text-ink-strong text-sm font-semibold">
                <span class="text-ink-muted ms-1">{{ i + 1 }}.</span>
                {{ formatUrl(page.url) }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-3 text-xs">
              <span class="text-brand-700 font-bold">{{ faNum(page.clicks) }} کلیک</span>
              <span class="text-ink-muted">{{ faNum(page.impressions) }} نمایش</span>
              <span class="text-ink-muted">رتبه {{ page.position.toFixed(1) }}</span>
            </div>
          </div>
        </div>
        <p v-else class="text-ink-muted text-sm">هنوز داده‌ای موجود نیست.</p>
      </VCard>

      <VCard title="🔍 ۱۰ کوئری برتر" description="بیشترین جستجوی کاربران">
        <div v-if="topQueries?.length" class="space-y-2">
          <div v-for="(q, i) in topQueries" :key="q.query" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 hover:bg-surface-muted">
            <div class="min-w-0 flex-1">
              <p class="text-ink-strong text-sm font-semibold">
                <span class="text-ink-muted ms-1">{{ i + 1 }}.</span>
                {{ q.query }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-3 text-xs">
              <span class="text-brand-700 font-bold">{{ faNum(q.clicks) }} کلیک</span>
              <span class="text-ink-muted">{{ faNum(q.impressions) }} نمایش</span>
              <span class="text-ink-muted">رتبه {{ q.position.toFixed(1) }}</span>
            </div>
          </div>
        </div>
        <p v-else class="text-ink-muted text-sm">هنوز داده‌ای موجود نیست.</p>
      </VCard>
    </div>

    <!-- حساب‌های متصل و ملک‌ها -->
    <div class="mt-8 grid gap-5 lg:grid-cols-2">
      <VCard title="حساب‌های متصل">
        <div v-if="accounts.length" class="space-y-3">
          <div v-for="account in accounts" :key="account.id" class="flex items-center justify-between rounded-lg px-3 py-2">
            <div>
              <p class="font-latin text-ink-strong text-sm font-semibold" dir="ltr">{{ account.email }}</p>
              <p v-if="account.expiresAt" class="text-ink-muted text-xs">انقضا: {{ account.expiresAt }}</p>
            </div>
            <VBadge tone="success">متصل</VBadge>
          </div>
        </div>
        <VEmptyState
          v-else
          title="حساب Google متصل نیست"
          description="برای دریافت داده، ابتدا حساب Google را متصل کنید."
          action-label="اتصال حساب"
          @action="$inertia.visit('/app/gsc/connect')"
        />
      </VCard>

      <VCard title="ملک‌های انتخاب‌شده">
        <div v-if="properties.length" class="space-y-3">
          <div v-for="property in properties" :key="property.id" class="rounded-lg px-3 py-2">
            <p class="text-ink-strong text-sm font-bold">{{ property.site_name }}</p>
            <p class="font-latin text-ink-muted text-xs" dir="ltr">{{ property.property_uri }}</p>
          </div>
        </div>
        <VEmptyState
          v-else
          title="ملک انتخاب نشده"
          description="پس از اتصال حساب، ملک مناسب هر سایت را انتخاب کنید."
          action-label="انتخاب ملک"
          @action="$inertia.visit('/app/gsc/properties')"
        />
      </VCard>
    </div>

    <!-- آخرین ایمپورت‌ها -->
    <VCard class="mt-8" title="تاریخچهٔ همگام‌سازی">
      <div v-if="runs.length" class="divide-line divide-y">
        <div v-for="run in runs" :key="run.id" class="flex items-center justify-between gap-3 py-3">
          <div class="flex items-center gap-3">
            <span
              :class="[
                'rounded-ui flex size-8 shrink-0 items-center justify-center',
                run.status === 'completed' ? 'bg-success-50 text-success-600' : run.status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-warning-50 text-warning-600',
              ]"
            >
              <VIcon :name="run.status === 'completed' ? 'check' : run.status === 'failed' ? 'x' : 'clock'" size="sm" />
            </span>
            <div>
              <p class="text-ink-strong text-sm font-semibold">{{ run.site_name }}</p>
              <p class="text-ink-muted text-xs">{{ run.date_start }} تا {{ run.date_end }}</p>
            </div>
          </div>
          <VBadge
            :tone="run.status === 'completed' ? 'success' : run.status === 'failed' ? 'danger' : 'warning'"
          >
            {{ run.status === 'completed' ? 'تکمیل' : run.status === 'failed' ? 'ناموفق' : 'در صف' }}
          </VBadge>
        </div>
      </div>
      <p v-else class="text-ink-muted text-sm leading-7">
        هنوز همگام‌سازی داده‌ای اجرا نشده است. روی «ایمپورت خودکار» کلیک کنید تا شروع شود.
      </p>
    </VCard>
  </AppLayout>
</template>
