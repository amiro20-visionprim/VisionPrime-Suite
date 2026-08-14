<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VTable, { type TableColumn, type TableRow } from '@/shared/ui/VTable.vue'

interface Kpis {
  totalAuto: number
  autoExecuted: number
  autoRolledBack: number
  autoFailed: number
  successRate: number | null
  systemApprovals: number
  humanApproved: number
  humanRejected: number
  rollbacks: number
  estimatedHoursSaved: number
}

interface LearningRow {
  commandType: string
  total: number
  successful: number
  successRate: number
  updatedAt: string | null
}

interface ReviewSample {
  id: number
  type: string
  riskTier: string
  confidence: number | null
  status: string
  url: string | null
  approvedAt: string | null
}

const props = defineProps<{
  site: { id: number; name: string }
  kpis: Kpis
  learning: LearningRow[]
  reviewSample: ReviewSample[]
}>()

const learningColumns: TableColumn[] = [
  { key: 'commandType', label: 'نوع تغییر' },
  { key: 'total', label: 'اجرا', align: 'center' },
  { key: 'successful', label: 'موفق', align: 'center' },
  { key: 'successRate', label: 'نرخ موفقیت', align: 'center' },
  { key: 'updatedAt', label: 'آخرین محاسبه', align: 'end' },
]

const sampleColumns: TableColumn[] = [
  { key: 'type', label: 'نوع' },
  { key: 'riskTier', label: 'ریسک', align: 'center' },
  { key: 'confidence', label: 'اطمینان', align: 'center' },
  { key: 'status', label: 'وضعیت', align: 'center' },
  { key: 'url', label: 'صفحه', align: 'end' },
  { key: 'approvedAt', label: 'زمان تأیید خودکار', align: 'end' },
]

function stat(label: string, value: string | number, hint: string): { label: string; value: string | number; hint: string } {
  return { label, value, hint }
}

const stats = (): Array<{ label: string; value: string | number; hint: string }> => [
  stat('نرخ موفقیت انتشار خودکار', props.kpis.successRate === null ? '—' : `${props.kpis.successRate}٪`, 'executed ÷ انتشار خودکار'),
  stat('انتشار خودکار', `${props.kpis.autoExecuted}`, 'موفق: ' + props.kpis.autoExecuted),
  stat('بازگشت خودکار (rollback)', props.kpis.rollbacks, 'بازگشت دادهشده به حالت قبل'),
  stat('تأیید سیستم', props.kpis.systemApprovals, 'reviewer=system'),
  stat('تأیید انسانی', props.kpis.humanApproved, 'ردشده: ' + props.kpis.humanRejected),
  stat('زمان صرفهجوییشده (تخمینی)', `${props.kpis.estimatedHoursSaved} ساعت`, '≈ ۱۵ دقیقه بهازای هر انتشار خودکار'),
]
</script>

<template>
  <Head title="اعتماد به سیستم" />
  <AppLayout>
    <VPageHeader title="اعتماد به سیستم" :description="`خودکارسازی سایت ${site.name}`" />

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <VCard v-for="item in stats()" :key="item.label" class="p-5">
        <p class="text-ink-muted text-sm">{{ item.label }}</p>
        <p class="text-ink-strong mt-2 text-3xl font-bold">{{ item.value }}</p>
        <p class="text-ink-muted mt-1 text-xs">{{ item.hint }}</p>
      </VCard>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <VCard title="نرخ موفقیت هر نوع تغییر (حلقهٔ یادگیری)">
        <VTable :columns="learningColumns" :rows="learning as unknown as TableRow[]" row-key="commandType" />
        <p v-if="learning.length === 0" class="text-ink-muted mt-4 text-sm">
          هنوز داده‌ای از حلقهٔ یادگیری ثبت نشده — بعد از اولین اجرای خودکار ظاهر می‌شود.
        </p>
      </VCard>

      <VCard title="نمونهٔ انتشارهای خودکار (بازبینی)">
        <VTable :columns="sampleColumns" :rows="reviewSample as unknown as TableRow[]" row-key="id">
          <template #cell-confidence="{ row }">
            <span v-if="row.confidence != null" class="font-latin">{{ row.confidence }}</span>
            <span v-else class="text-ink-muted">—</span>
          </template>
          <template #cell-status="{ row }">
            <VBadge :tone="row.status === 'executed' ? 'success' : row.status === 'rolled_back' ? 'warning' : row.status === 'failed' ? 'danger' : 'neutral'">
              {{ row.status }}
            </VBadge>
          </template>
          <template #cell-url="{ row }">
            <span class="font-latin text-xs" dir="ltr">{{ row.url ?? '—' }}</span>
          </template>
        </VTable>
        <p v-if="reviewSample.length === 0" class="text-ink-muted mt-4 text-sm">هنوز انتشار خودکاری ثبت نشده است.</p>
      </VCard>
    </div>
  </AppLayout>
</template>
