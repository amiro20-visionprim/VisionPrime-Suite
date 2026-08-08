<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatLocalizedDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VPagination from '@/shared/ui/VPagination.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import VTable, { type TableColumn, type TableRow } from '@/shared/ui/VTable.vue'

interface AuditRow extends TableRow {
  id: number
  action: string
  actionLabel: string
  actorName: string | null
  actorEmail: string | null
  subjectType: string | null
  subjectId: number | null
  source: string
  occurredAt: string | null
}

defineProps<{
  logs: { data: AuditRow[]; current_page: number; last_page: number }
  actionOptions: { value: string; label: string }[]
  selectedAction: string
}>()

function changeAction(action: string): void {
  router.get('/app/settings/audit-log', action ? { action } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const columns: TableColumn[] = [
  { key: 'occurredAt', label: 'زمان' },
  { key: 'actionLabel', label: 'رویداد' },
  { key: 'actorName', label: 'بازیگر' },
  { key: 'source', label: 'منبع' },
]

const sourceLabels: Record<string, string> = {
  web: 'وب',
  api: 'API',
  connector: 'وردپرس',
  cli: 'دستور',
  system: 'سیستم',
}
</script>

<template>
  <Head title="گزارش ممیزی" />
  <AppLayout>
    <VPageHeader
      title="گزارش ممیزی"
      description="تاریخچهٔ رویدادها و عملیات حساس سازمان؛ این گزارش تغییرناپذیر است."
    />

    <div class="mt-8 space-y-5">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="w-full max-w-xs">
          <VSelect
            :model-value="selectedAction"
            label="فیلتر رویداد"
            :options="[{ value: '', label: 'همهٔ رویدادها' }, ...actionOptions]"
            @update:model-value="changeAction"
          />
        </div>
        <p class="text-ink-muted text-sm">
          {{ logs.data.length }} رویداد در این صفحه
        </p>
      </div>

      <div v-if="logs.data.length">
        <VTable :columns="columns" :rows="logs.data" row-key="id" mobile-mode="cards">
          <template #cell-occurredAt="{ value }">
            <span v-if="value" class="text-ink text-sm whitespace-nowrap">{{
              formatLocalizedDate(value, 'fa')
            }}</span>
            <span v-else>—</span>
          </template>
          <template #cell-actionLabel="{ row }">
            <span class="text-ink-strong font-medium">{{ row.actionLabel }}</span>
            <span v-if="row.subjectType" class="text-ink-muted block text-xs" dir="ltr">
              {{ row.subjectType }}{{ row.subjectId ? ` #${row.subjectId}` : '' }}
            </span>
          </template>
          <template #cell-actorName="{ row }">
            <span v-if="row.actorName" class="text-ink-strong text-sm font-medium">{{
              row.actorName
            }}</span>
            <span v-if="row.actorEmail" class="text-ink-muted block text-xs" dir="ltr">{{
              row.actorEmail
            }}</span>
            <span v-if="!row.actorName && !row.actorEmail" class="text-ink-muted text-sm"
              >سیستم</span
            >
          </template>
          <template #cell-source="{ value }">
            <VBadge>{{ sourceLabels[value as string] ?? value }}</VBadge>
          </template>
        </VTable>
        <div class="mt-5">
          <VPagination
            :model-value="logs.current_page"
            :total-pages="logs.last_page"
            @update:model-value="
              (page) => router.get('/app/settings/audit-log', { page, action: selectedAction || undefined }, { preserveState: true, replace: true })
            "
          />
        </div>
      </div>
      <VEmptyState
        v-else
        title="رویدادی یافت نشد"
        description="با انتخاب فیلتر دیگری یا حذف فیلتر، رویدادهای ثبت‌شده را ببینید."
      />
    </div>
  </AppLayout>
</template>
