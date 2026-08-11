<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

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

function contactLabel(lead: MarketingLead): string {
  return lead.contact ?? lead.email ?? '—'
}

const maxCampaignCount = Math.max(1, ...props.stats.topCampaigns.map((c) => c.count))
const maxSourceCount = Math.max(1, ...props.stats.topSources.map((s) => s.count))
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

    <!-- Campaign / source breakdown -->
    <div class="mt-5 grid gap-5 lg:grid-cols-2">
      <div class="rounded-panel border-line bg-surface border p-5">
        <h2 class="text-ink-strong text-sm font-bold">کمپین‌های برتر</h2>
        <div v-if="stats.topCampaigns.length" class="mt-4 space-y-3">
          <div v-for="item in stats.topCampaigns" :key="item.campaign">
            <div class="flex items-center justify-between text-sm">
              <span class="text-ink font-semibold">{{ item.campaign }}</span>
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
        <label class="block">
          <span class="text-ink-muted block text-xs font-bold">کمپین</span>
          <input
            v-model="draft.campaign"
            type="text"
            class="border-line bg-surface text-ink-strong mt-1 w-full rounded-ui border px-3 py-2 text-sm outline-none"
            placeholder="launch_1405"
          />
        </label>
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
            <td class="text-ink px-4 py-3.5 text-center">{{ lead.utmCampaign ?? '—' }}</td>
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
