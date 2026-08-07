<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface SiteHealthSummary {
  total_sites: number
  connected_sites: number
  needs_attention: number
}

interface SiteHealthItem {
  id: number
  name: string
  canonical_url: string
  connected: boolean
  last_seen_at: string | null
  audit_score: number | null
  issue_count: number
  high_risk_count: number
  url_count: number
}

const props = defineProps<{
  summary: SiteHealthSummary | null
  sites: SiteHealthItem[]
}>()

function scoreTone(score: number | null): 'success' | 'warning' | 'danger' | 'neutral' {
  if (score === null) return 'neutral'
  if (score >= 80) return 'success'
  if (score >= 70) return 'warning'
  return 'danger'
}
</script>

<template>
  <Head title="سلامت سایت" />
  <ClientPortalLayout>
    <VPageHeader
      title="سلامت سایت"
      description="خلاصه‌ای ساده از وضعیت اتصال و سلامت صفحات سایت شما، بدون جزئیات فنی پیچیده."
    />

    <div v-if="summary" class="mt-8 grid gap-4 sm:grid-cols-3">
      <VCard title="کل سایت‌ها"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ summary.total_sites }}</p>
        <p class="text-ink-muted mt-1 text-sm">سایت‌های فعال در حساب شما</p></VCard
      >
      <VCard title="سایت‌های متصل"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ summary.connected_sites }}</p>
        <p class="text-ink-muted mt-1 text-sm">سایت‌هایی که به وردپرس متصل هستند</p></VCard
      >
      <VCard title="نیازمند توجه"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ summary.needs_attention }}</p>
        <p class="text-ink-muted mt-1 text-sm">سایت‌هایی که موردی برای بهبود در آن‌ها وجود دارد</p></VCard
      >
    </div>

    <div v-if="props.sites.length" class="mt-8 space-y-4">
      <VCard v-for="site in props.sites" :key="site.id">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-ink-strong font-semibold">{{ site.name }}</h2>
              <VBadge :tone="site.connected ? 'success' : 'neutral'">{{
                site.connected ? 'متصل' : 'اتصال برقرار نشده'
              }}</VBadge>
            </div>
            <p class="text-ink-muted mt-1 truncate text-sm" dir="ltr">{{ site.canonical_url }}</p>
          </div>
          <div class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm sm:flex sm:flex-wrap">
            <div>
              <p class="text-ink-muted">امتیاز سلامت</p>
              <VBadge :tone="scoreTone(site.audit_score)" class="mt-1">
                {{ site.audit_score === null ? '—' : site.audit_score }}
              </VBadge>
            </div>
            <div>
              <p class="text-ink-muted">مشکلات شناسایی‌شده</p>
              <p class="text-ink-strong mt-1 font-bold">{{ site.issue_count }}</p>
            </div>
            <div>
              <p class="text-ink-muted">ریسک‌های بالای تبدیل</p>
              <p class="text-ink-strong mt-1 font-bold">{{ site.high_risk_count }}</p>
            </div>
            <div>
              <p class="text-ink-muted">صفحات بررسی‌شده</p>
              <p class="text-ink-strong mt-1 font-bold">{{ site.url_count }}</p>
            </div>
          </div>
        </div>
        <p v-if="site.last_seen_at" class="text-ink-muted mt-4 text-xs">
          آخرین ارتباط: {{ formatJalaliDate(site.last_seen_at) }}
        </p>
      </VCard>
    </div>

    <VEmptyState
      v-if="!props.sites.length"
      class="mt-8"
      title="سایتی برای نمایش وجود ندارد"
      description="وقتی سایتی به حساب شما اضافه شود، وضعیت سلامت آن در اینجا نمایش داده می‌شود."
    />
  </ClientPortalLayout>
</template>
