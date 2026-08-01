<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatLocalizedDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VPagination from '@/shared/ui/VPagination.vue'
import VTable, { type TableColumn, type TableRow } from '@/shared/ui/VTable.vue'

interface ClientRow extends TableRow {
  id: number
  name: string
  status: string
  projectsCount: number
  portalUsersCount: number
  updatedAt: string | null
}

defineProps<{
  clients: { data: ClientRow[]; current_page: number; last_page: number }
}>()

const columns: TableColumn[] = [
  { key: 'name', label: 'مشتری' },
  { key: 'projectsCount', label: 'پروژه‌ها', align: 'center' },
  { key: 'portalUsersCount', label: 'کاربران پرتال', align: 'center' },
  { key: 'status', label: 'وضعیت' },
  { key: 'updatedAt', label: 'آخرین بروزرسانی', align: 'end' },
]
</script>

<template>
  <Head title="مشتریان" />
  <AppLayout
    ><VPageHeader
      title="مشتریان"
      description="کسب‌وکارهای طرف قرارداد، پروژه‌ها و اعضای پرتال مشتری را از اینجا مدیریت کنید."
      ><template #actions
        ><VButton href="/app/clients/create">افزودن مشتری</VButton></template
      ></VPageHeader
    >
    <div v-if="clients.data.length" class="mt-8">
      <VTable :columns="columns" :rows="clients.data" row-key="id" mobile-mode="cards"
        ><template #cell-name="{ row }"
          ><Link
            :href="`/app/clients/${row.id}`"
            class="text-brand-700 hover:text-brand-900 font-semibold"
            >{{ row.name }}</Link
          ></template
        ><template #cell-status="{ value }"
          ><VBadge :tone="value === 'active' ? 'success' : 'neutral'">{{
            value === 'active' ? 'فعال' : value
          }}</VBadge></template
        ><template #cell-updatedAt="{ value }"
          ><span v-if="value" class="text-ink-muted text-sm">{{
            formatLocalizedDate(value, 'fa')
          }}</span
          ><span v-else>—</span></template
        ></VTable
      >
      <div class="mt-5">
        <VPagination
          :model-value="clients.current_page"
          :total-pages="clients.last_page"
          @update:model-value="(page) => $inertia.get('/app/clients', { page })"
        />
      </div>
    </div>
    <div v-else class="mt-8">
      <VEmptyState
        title="هنوز مشتری‌ای اضافه نشده است"
        description="با ایجاد اولین مشتری، می‌توانید پروژه‌ها، سایت‌ها و دسترسی پرتال او را در یک جریان واحد مدیریت کنید."
        action-label="افزودن مشتری"
        @action="$inertia.visit('/app/clients/create')"
      /></div
  ></AppLayout>
</template>
