<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VIcon from '@/shared/ui/VIcon.vue'

interface ClientReport {
  id: number
  type: string
  period_start: string
  period_end: string
  content: Record<string, unknown>
  published_at: string
}

defineProps<{
  reports: ClientReport[]
}>()

function reportCount(report: ClientReport, key: string): number | null {
  const value = report.content[key]
  return typeof value === 'number' ? value : null
}
</script>

<template>
  <Head title="گزارش‌ها | پرتال مشتری" />
  <ClientPortalLayout>
    <VPageHeader
      title="گزارش‌ها"
      description="خلاصهٔ دوره‌ای وضعیت سایت شما، اولویت‌ها و کارهایی که انجام شده — به زبان ساده."
    />

    <div v-if="reports.length" class="mt-8 grid gap-4 md:grid-cols-2">
      <VCard v-for="report in reports" :key="report.id">
        <div class="flex items-start gap-4">
          <span class="rounded-ui bg-brand-50 text-brand-700 flex size-11 shrink-0 items-center justify-center">
            <VIcon name="file" size="lg" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-ink-strong font-semibold">{{ report.type }}</p>
            <p class="text-ink-muted mt-1 text-sm">
              دوره: {{ formatJalaliDate(report.period_start) }} تا
              {{ formatJalaliDate(report.period_end) }}
            </p>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <VBadge tone="info">فرصت‌ها: {{ reportCount(report, 'opportunities') ?? '—' }}</VBadge>
          <VBadge tone="warning">نقاط خطر: {{ reportCount(report, 'high_risks') ?? '—' }}</VBadge>
          <VBadge tone="success">پیشنهادها: {{ reportCount(report, 'recommendations') ?? '—' }}</VBadge>
        </div>

        <div class="border-line mt-4 flex items-center justify-between border-t pt-3">
          <p class="text-ink-muted text-xs">منتشرشده در {{ formatJalaliDate(report.published_at) }}</p>
          <Link
            href="/client/growth"
            class="text-brand-700 hover:text-brand-800 text-xs font-semibold"
            >مشاهده رشد من ←</Link
          >
        </div>
      </VCard>
    </div>

    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز گزارشی منتشر نشده است"
      description="وقتی تیم گزارش دوره‌ای را آماده و منتشر کند، در اینجا قابل مشاهده خواهد بود."
    />
  </ClientPortalLayout>
</template>
