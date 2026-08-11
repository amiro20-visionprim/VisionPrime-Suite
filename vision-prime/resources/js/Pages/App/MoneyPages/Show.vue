<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { MoneyPageAuditDetail } from '@/types/seo'

defineProps<{ audit: MoneyPageAuditDetail }>()

const severityLabels: Record<string, string> = {
  low: 'کم',
  medium: 'متوسط',
  high: 'زیاد',
  critical: 'بحرانی',
}

const severityTone: Record<string, 'info' | 'warning' | 'danger'> = {
  low: 'info',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
}

const opportunityLabels: Record<string, string> = {
  revenue_opportunity: 'فرصت درآمدی',
  ctr_gap: 'شکاف نرخ کلیک',
  keyword_opportunity: 'فرصت کلیدواژه',
  conversion_boost: 'بهبود تبدیل',
  content_gap: 'شکاف محتوا',
  cannibalization: 'رقابت داخلی کلمات',
}

function percent(value: number): string {
  return `${(value * 100).toFixed(1)}٪`
}
</script>
<template>
  <Head title="جزئیات صفحهٔ درآمدزا" /><AppLayout
    ><VPageHeader
      title="جزئیات صفحهٔ درآمدزا"
      :description="audit.siteName"
    >
      <template #actions>
        <VButton v-if="audit.reviewItemId" variant="secondary" :href="`/app/reviews/${audit.reviewItemId}`">
          باز کردن بازبینی
        </VButton>
      </template>
    </VPageHeader>

    <div class="mt-8 space-y-6">
      <VCard title="صفحه">
        <div class="flex flex-wrap items-center gap-3">
          <VBadge :tone="audit.score >= 80 ? 'success' : audit.score >= 60 ? 'warning' : 'danger'">
            امتیاز {{ audit.score }}
          </VBadge>
          <span class="text-ink-muted text-sm">
            بازبینی در {{ formatJalaliDate(audit.auditedAt) }}
          </span>
        </div>
        <p class="font-latin text-ink-strong mt-4 text-sm break-all" dir="ltr">
          {{ audit.canonicalUrl }}
        </p>
      </VCard>

      <VCard v-if="audit.gsc" title="دادهٔ جستجو (Search Console)">
        <div class="grid gap-4 sm:grid-cols-4">
          <div>
            <p class="text-ink-muted text-sm">کلیک‌ها</p>
            <p class="text-ink-strong mt-1 text-lg font-semibold">{{ audit.gsc.clicks }}</p>
          </div>
          <div>
            <p class="text-ink-muted text-sm">نمایش‌ها</p>
            <p class="text-ink-strong mt-1 text-lg font-semibold">{{ audit.gsc.impressions }}</p>
          </div>
          <div>
            <p class="text-ink-muted text-sm">نرخ کلیک</p>
            <p class="text-ink-strong mt-1 text-lg font-semibold">{{ percent(audit.gsc.ctr) }}</p>
          </div>
          <div>
            <p class="text-ink-muted text-sm">میانگین جایگاه</p>
            <p class="text-ink-strong mt-1 text-lg font-semibold">{{ audit.gsc.position }}</p>
          </div>
        </div>
      </VCard>

      <VCard title="مشکلات شناسایی‌شده">
        <div v-if="audit.issues.length" class="space-y-2">
          <div
            v-for="issue in audit.issues"
            :key="issue.key"
            class="border-line flex items-start gap-3 rounded-ui border p-3"
          >
            <VBadge :tone="severityTone[issue.severity] ?? 'info'">
              {{ severityLabels[issue.severity] ?? issue.severity }}
            </VBadge>
            <p class="text-ink-strong text-sm">{{ issue.explanation }}</p>
          </div>
        </div>
        <p v-else class="text-ink-muted text-sm">مشکلی ثبت نشده است.</p>
      </VCard>

      <VCard title="فرصت‌های مرتبط">
        <div v-if="audit.opportunities.length" class="space-y-2">
          <Link
            v-for="opportunity in audit.opportunities"
            :key="opportunity.id"
            :href="`/app/opportunities/${opportunity.id}`"
            class="border-line flex items-center justify-between gap-3 rounded-ui border p-3 transition-colors hover:border-brand-300"
          >
            <div class="min-w-0">
              <p class="text-ink-strong text-sm font-semibold">
                {{ opportunityLabels[opportunity.type] ?? opportunity.type }}
              </p>
              <p class="text-ink-muted mt-1 text-sm leading-6">{{ opportunity.explanation }}</p>
            </div>
            <VBadge tone="warning">{{ opportunity.score }}</VBadge>
          </Link>
        </div>
        <p v-else class="text-ink-muted text-sm">فرصت مرتبطی ثبت نشده است.</p>
      </VCard>
    </div></AppLayout
  >
</template>
