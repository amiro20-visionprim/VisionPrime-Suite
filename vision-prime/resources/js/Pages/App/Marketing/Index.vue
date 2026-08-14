<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import type {
  LeadSource,
  LeadStatus,
  MarketingFilters,
  MarketingLead,
  MarketingStats,
} from '@/types/marketing'

const props = defineProps<{
  leads: MarketingLead[]
  stats: MarketingStats
  filters: MarketingFilters
  statusLabels: Record<LeadStatus, string>
  canManage: boolean
}>()

const statusTone: Record<LeadStatus, 'success' | 'warning' | 'info' | 'danger'> = {
  new: 'info',
  contacted: 'warning',
  qualified: 'success',
  unqualified: 'danger',
}

const sourceLabels: Record<LeadSource, string> = {
  demo: 'درخواست دمو',
  support: 'پشتیبانی',
}

/** برچسب فارسی برای کمپین‌های لندینگ اختصاصی — سایر کمپین‌ها با مقدار خام نمایش داده می‌شوند. */
const LANDING_CAMPAIGN_LABELS: Record<string, string> = {
  landing_agencies: 'لندینگ آژانس‌ها',
  landing_ecommerce: 'لندینگ فروشگاه اینترنتی',
  landing_clinics: 'لندینگ کلینیک',
  landing_education: 'لندینگ مرکز آموزشی',
  landing_hospitality: 'لندینگ سفر و هتلداری',
}

const campaignOptions = computed(() => {
  const seen = new Set<string>()
  const options: { value: string; label: string }[] = []
  const sources = [
    ...Object.keys(LANDING_CAMPAIGN_LABELS),
    ...props.stats.topCampaigns.map((c) => c.campaign),
  ]
  for (const value of sources) {
    if (value === '' || seen.has(value)) continue
    seen.add(value)
    options.push({ value, label: LANDING_CAMPAIGN_LABELS[value] ?? value })
  }
  return options
})

const draft = ref<MarketingFilters>({ ...props.filters })

function applyFilters(): void {
  const query: Record<string, string> = {}
  for (const [key, value] of Object.entries(draft.value)) {
    if (value !== '') {
      query[key] = value
    }
  }
  router.get('/app/marketing', query, { preserveState: true })
}

function clearFilters(): void {
  draft.value = { status: '', source: '', campaign: '', q: '', from: '', to: '', sort: '' }
  router.get('/app/marketing', {}, { preserveState: true })
}

function toggleSort(): void {
  const sort = draft.value.sort === 'score' ? '' : 'score'
  draft.value.sort = sort
  router.get('/app/marketing', { sort }, { preserveState: true, preserveScroll: true })
}

function scoreTone(score: number | null): 'success' | 'info' | 'warning' | 'neutral' {
  if (score === null) return 'neutral'
  if (score >= 70) return 'success'
  if (score >= 45) return 'info'
  if (score >= 25) return 'warning'
  return 'neutral'
}

function changeStatus(lead: MarketingLead, status: string): void {
  if (status === lead.status) {
    return
  }
  router.put(`/app/marketing/leads/${lead.id}/status`, { status }, { preserveScroll: true })
}

const hasActiveFilters = computed(() => {
  return (
    props.filters.status !== '' ||
    props.filters.source !== '' ||
    props.filters.campaign !== '' ||
    props.filters.q !== '' ||
    props.filters.from !== '' ||
    props.filters.to !== ''
  )
})

const funnelToShow = computed(() => (hasActiveFilters.value ? props.stats.filteredFunnel : props.stats.funnel))

function funnelBarWidth(prev: number, current: number): number {
  if (prev <= 0) return 0
  return Math.max(8, Math.round((current / prev) * 100))
}

function campaignLabel(value: string): string {
  return LANDING_CAMPAIGN_LABELS[value] ?? value
}

function contactLabel(lead: MarketingLead): string {
  return lead.contact ?? lead.email ?? '—'
}

const maxCampaignCount = Math.max(1, ...props.stats.topCampaigns.map((c) => c.count))
const maxSourceCount = Math.max(1, ...props.stats.topSources.map((s) => s.count))

const maxFunnelTotal = Math.max(1, ...props.stats.campaignFunnel.map((f) => f.total))

function pct(value: number | null): string {
  if (value === null) return '—'
  return `${value}٪`
}

function funnelWidth(count: number): string {
  // نسبت به بزرگ‌ترین کمپین برای مقایسهٔ نسبی بین کمپین‌ها
  return `${Math.max(4, Math.round((count / maxFunnelTotal) * 100))}%`
}
</script>

<template>
  <Head title="بازاریابی و لیدها" />
  <AppLayout>
    <VPageHeader
      title="بازاریابی و لیدها"
      description="دادهٔ کامل درخواست‌ها و پیام‌های پشتیبانی با انتساب کمپین — برای تصمیم‌گیری، پیشنهاد و بازخورد تیم تبلیغات."
    />

    <!-- KPIs -->
    <div class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-5">
      <div class="rounded-card border-line bg-surface border p-4">
        <p class="text-ink-muted text-xs">کل لیدها</p>
        <p class="text-ink-strong mt-2 text-2xl font-bold">{{ stats.total }}</p>
      </div>
      <div class="rounded-card border-line bg-surface border p-4">
        <p class="text-ink-muted text-xs">۷ روز اخیر</p>
        <p class="text-ink-strong mt-2 text-2xl font-bold">{{ stats.thisWeek }}</p>
      </div>
      <div class="rounded-card border-line bg-surface border p-4">
        <p class="text-ink-muted text-xs">جدید</p>
        <p class="text-ink-strong mt-2 text-2xl font-bold">{{ stats.byStatus.new }}</p>
      </div>
      <div class="rounded-card border-line bg-surface border p-4">
        <p class="text-ink-muted text-xs">تماس‌گرفته‌شده</p>
        <p class="text-ink-strong mt-2 text-2xl font-bold">{{ stats.byStatus.contacted }}</p>
      </div>
      <div class="rounded-card bg-success-50 border-success-200 border p-4">
        <p class="text-ink-muted text-xs">واجد شرایط</p>
        <p class="text-success-700 mt-2 text-2xl font-bold">{{ stats.byStatus.qualified }}</p>
      </div>
    </div>

    <!-- Conversion funnel -->
    <div class="mt-6 grid gap-5 lg:grid-cols-2">
      <!-- Overall funnel -->
      <div class="rounded-panel border-line bg-surface border p-5">
        <div class="flex items-center justify-between">
          <h2 class="text-ink-strong text-sm font-bold">قیف تبدیل</h2>
          <span v-if="hasActiveFilters" class="text-ink-muted text-xs">بر اساس فیلترهای فعلی</span>
        </div>
        <div class="mt-5 space-y-2">
          <div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-ink font-semibold">لید</span>
              <span class="text-ink-muted text-xs">{{ funnelToShow.total }} لید</span>
            </div>
            <div class="bg-brand-600 mt-1.5 h-9 rounded-lg opacity-90" :style="{ width: '100%' }" />
          </div>
          <div class="text-brand-700 text-xs font-bold">↓ {{ pct(funnelToShow.leadToContactedRate) }} تماس گرفته شد</div>
          <div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-ink font-semibold">تماس گرفته‌شده</span>
              <span class="text-ink-muted text-xs">{{ funnelToShow.contacted }} لید</span>
            </div>
            <div class="bg-brand-500 mt-1.5 h-9 rounded-lg" :style="{ width: `${funnelBarWidth(funnelToShow.total, funnelToShow.contacted)}%` }" />
          </div>
          <div class="text-brand-700 text-xs font-bold">↓ {{ pct(funnelToShow.contactedToQualifiedRate) }} واجد شرایط شد</div>
          <div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-ink font-semibold">واجد شرایط</span>
              <span class="text-ink-muted text-xs">{{ funnelToShow.qualified }} لید</span>
            </div>
            <div class="bg-success-500 mt-1.5 h-9 rounded-lg" :style="{ width: `${funnelBarWidth(funnelToShow.contacted, funnelToShow.qualified)}%` }" />
          </div>
        </div>
        <p class="text-ink-muted mt-4 border-t border-dashed pt-3 text-xs">
          نرخ تبدیل نهایی: <span class="text-success-700 font-bold">{{ pct(funnelToShow.qualifiedRate) }}</span> از کل لیدها به مشتریِ واجد شرایط رسیدند.
        </p>
      </div>

      <!-- Per-campaign funnel -->
      <div class="rounded-panel border-line bg-surface border p-5">
        <h2 class="text-ink-strong text-sm font-bold">قیف تبدیل هر کمپین</h2>
        <p class="text-ink-muted mt-1 text-xs">لید → تماس → واجد شرایط — کدام کمپین واقعاً مشتری می‌سازد؟</p>
        <div v-if="stats.campaignFunnel.length" class="mt-4 space-y-4">
          <div v-for="f in stats.campaignFunnel" :key="f.campaign">
            <div class="flex items-center justify-between text-sm">
              <span class="text-ink font-semibold">{{ campaignLabel(f.campaign) }}</span>
              <span class="text-ink-muted text-xs">{{ f.total }} لید</span>
            </div>
            <div class="mt-1.5 space-y-1">
              <div class="bg-surface-muted h-2.5 overflow-hidden rounded-full">
                <div class="bg-brand-600 h-full rounded-full" :style="{ width: funnelWidth(f.total) }" />
              </div>
              <div class="bg-surface-muted h-2.5 overflow-hidden rounded-full">
                <div class="bg-brand-400 h-full rounded-full" :style="{ width: funnelWidth(f.contacted) }" />
              </div>
              <div class="bg-surface-muted h-2.5 overflow-hidden rounded-full">
                <div class="bg-success-500 h-full rounded-full" :style="{ width: funnelWidth(f.qualified) }" />
              </div>
            </div>
            <div class="text-ink-muted mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
              <span class="inline-flex items-center gap-1">
                <span class="bg-brand-600 size-2 rounded-full" />
                {{ f.contacted }} تماس ({{ pct(f.leadToContactedRate) }})
              </span>
              <span class="inline-flex items-center gap-1">
                <span class="bg-success-500 size-2 rounded-full" />
                {{ f.qualified }} واجد شرایط ({{ pct(f.contactedToQualifiedRate) }} از تماس‌ها)
              </span>
            </div>
          </div>
        </div>
        <p v-else class="text-ink-muted mt-4 text-sm">هنوز دادهٔ کمپینی ثبت نشده است.</p>
      </div>
    </div>

    <!-- Campaign / source breakdown -->
    <div class="mt-5 grid gap-5 lg:grid-cols-2">
      <div class="rounded-panel border-line bg-surface border p-5">
        <h2 class="text-ink-strong text-sm font-bold">کمپین‌های برتر</h2>
        <div v-if="stats.topCampaigns.length" class="mt-4 space-y-3">
          <div v-for="item in stats.topCampaigns" :key="item.campaign">
            <div class="flex items-center justify-between text-sm">
              <span class="text-ink font-semibold">{{ campaignLabel(item.campaign) }}</span>
              <span class="text-ink-muted text-xs">{{ item.count }} لید</span>
            </div>
            <div class="bg-surface-muted mt-1.5 h-2 overflow-hidden rounded-full">
              <div class="bg-brand-600 h-full rounded-full" :style="{ width: `${(item.count / maxCampaignCount) * 100}%` }" />
            </div>
          </div>
        </div>
        <p v-else class="text-ink-muted mt-4 text-sm">هنوز دادهٔ کمپینی ثبت نشده است.</p>
      </div>
      <div class="rounded-panel border-line bg-surface border p-5">
        <h2 class="text-ink-strong text-sm font-bold">کانال‌های ورود (UTM Source)</h2>
        <div v-if="stats.topSources.length" class="mt-4 space-y-3">
          <div v-for="item in stats.topSources" :key="item.source">
            <div class="flex items-center justify-between text-sm">
              <span class="text-ink font-semibold">{{ item.source }}</span>
              <span class="text-ink-muted text-xs">{{ item.count }} لید</span>
            </div>
            <div class="bg-surface-muted mt-1.5 h-2 overflow-hidden rounded-full">
              <div class="bg-success-500 h-full rounded-full" :style="{ width: `${(item.count / maxSourceCount) * 100}%` }" />
            </div>
          </div>
        </div>
        <p v-else class="text-ink-muted mt-4 text-sm">هنوز دادهٔ کانالی ثبت نشده است.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-panel border-line bg-surface mt-6 border p-4">
      <div class="grid gap-3 md:grid-cols-3 lg:grid-cols-6">
        <VSelect
          v-model="draft.status"
          label="وضعیت"
          placeholder="همه"
          :options="Object.entries(statusLabels).map(([value, label]) => ({ value, label }))"
        />
        <VSelect
          v-model="draft.source"
          label="منبع"
          placeholder="همه"
          :options="[
            { value: 'demo', label: 'درخواست دمو' },
            { value: 'support', label: 'پشتیبانی' },
          ]"
        />
        <VSelect
          v-model="draft.campaign"
          label="کمپین لندینگ"
          placeholder="همه"
          :options="campaignOptions"
        />
        <label class="block">
          <span class="text-ink-muted block text-xs font-bold">جستجو</span>
          <input
            v-model="draft.q"
            type="text"
            class="border-line bg-surface text-ink-strong mt-1 w-full rounded-ui border px-3 py-2 text-sm outline-none"
            placeholder="نام، ایمیل یا شرکت"
          />
        </label>
        <label class="block">
          <span class="text-ink-muted block text-xs font-bold">از تاریخ</span>
          <input
            v-model="draft.from"
            type="date"
            class="border-line bg-surface text-ink-strong mt-1 w-full rounded-ui border px-3 py-2 text-sm outline-none"
          />
        </label>
        <label class="block">
          <span class="text-ink-muted block text-xs font-bold">تا تاریخ</span>
          <input
            v-model="draft.to"
            type="date"
            class="border-line bg-surface text-ink-strong mt-1 w-full rounded-ui border px-3 py-2 text-sm outline-none"
          />
        </label>
      </div>
      <div class="mt-4 flex flex-wrap items-center gap-2">
        <VButton size="sm" @click="applyFilters">اعمال فیلتر</VButton>
        <VButton size="sm" variant="ghost" @click="clearFilters">پاک‌کردن</VButton>
        <VButton size="sm" variant="secondary" @click="toggleSort">
          {{ draft.sort === 'score' ? 'مرتب‌سازی: تاریخ ⏱' : 'مرتب‌سازی: امتیاز ⭐' }}
        </VButton>
        <span class="text-ink-muted ms-auto text-xs">{{ leads.length }} لید نمایش داده شده</span>
      </div>
    </div>

    <!-- Leads table -->
    <div class="rounded-panel border-line mt-5 overflow-x-auto border bg-white">
      <table class="w-full min-w-[860px] border-collapse text-sm">
        <thead>
          <tr class="border-line bg-surface-muted/60 border-b">
            <th class="text-ink-strong px-4 py-3 text-start font-bold">نام / شرکت</th>
            <th class="text-ink-muted px-4 py-3 text-start font-medium">تماس</th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">منبع</th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">کمپین</th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">کانال</th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">امتیاز</th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">وضعیت</th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">تاریخ</th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">اقدام</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="lead in leads" :key="lead.id" class="border-line border-b last:border-0 hover:bg-surface-muted/40">
            <td class="px-4 py-3.5">
              <Link :href="`/app/marketing/leads/${lead.id}`" class="text-brand-700 font-bold hover:underline">
                {{ lead.name }}
              </Link>
              <p class="text-ink-muted mt-0.5 text-xs">{{ lead.company ?? '—' }}</p>
            </td>
            <td class="text-ink px-4 py-3.5" dir="ltr">{{ contactLabel(lead) }}</td>
            <td class="px-4 py-3.5 text-center">
              <VBadge :tone="lead.source === 'demo' ? 'info' : 'warning'">{{ sourceLabels[lead.source] }}</VBadge>
            </td>
            <td class="text-ink px-4 py-3.5 text-center">{{ lead.utmCampaign ? campaignLabel(lead.utmCampaign) : '—' }}</td>
            <td class="text-ink px-4 py-3.5 text-center">{{ lead.utmSource ?? (lead.referrer ? 'referrer' : 'direct') }}</td>
            <td class="px-4 py-3.5 text-center">
              <VBadge v-if="lead.score !== null" :tone="scoreTone(lead.score)">{{ lead.score }}/۱۰۰</VBadge>
              <span v-else class="text-ink-muted text-xs">—</span>
            </td>
            <td class="px-4 py-3.5 text-center">
              <div class="flex items-center justify-center gap-2">
                <VBadge :tone="statusTone[lead.status]">{{ statusLabels[lead.status] }}</VBadge>
                <select
                  v-if="canManage"
                  :value="lead.status"
                  class="border-line text-ink-muted rounded-ui border bg-transparent px-1.5 py-1 text-xs outline-none"
                  aria-label="تغییر وضعیت"
                  @change="changeStatus(lead, ($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
            </td>
            <td class="text-ink-muted px-4 py-3.5 text-center">{{ formatJalaliDate(lead.createdAt) }}</td>
            <td class="px-4 py-3.5 text-center">
              <Link :href="`/app/marketing/leads/${lead.id}`" class="text-brand-700 text-xs font-bold hover:underline">جزئیات</Link>
            </td>
          </tr>
        </tbody>
      </table>
      <VEmptyState
        v-if="leads.length === 0"
        title="لیدی یافت نشد"
        description="فیلترها را تغییر دهید یا صبر کنید تا درخواست‌های جدید ثبت شوند."
      />
    </div>
  </AppLayout>
</template>
