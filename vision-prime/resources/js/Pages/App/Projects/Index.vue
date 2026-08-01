<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VTable, { type TableColumn, type TableRow } from '@/shared/ui/VTable.vue'
interface Row extends TableRow {
  id: number
  name: string
  clientName: string
  sitesCount: number
  status: string
}
defineProps<{ projects: { data: Row[] } }>()
const columns: TableColumn[] = [
  { key: 'name', label: 'پروژه' },
  { key: 'clientName', label: 'مشتری' },
  { key: 'sitesCount', label: 'سایت‌ها', align: 'center' },
  { key: 'status', label: 'وضعیت' },
]
</script>
<template>
  <Head title="پروژه‌ها" /><AppLayout
    ><VPageHeader
      title="پروژه‌ها"
      description="هدف‌ها، سایت‌ها و عملیات هر مشتری را در پروژه‌های مستقل مدیریت کنید."
      ><template #actions
        ><VButton href="/app/projects/create">افزودن پروژه</VButton></template
      ></VPageHeader
    >
    <div v-if="projects.data.length" class="mt-8">
      <VTable :columns="columns" :rows="projects.data" row-key="id" mobile-mode="cards"
        ><template #cell-name="{ row }"
          ><Link :href="`/app/projects/${row.id}`" class="text-brand-700 font-semibold">{{
            row.name
          }}</Link></template
        ><template #cell-status="{ value }"
          ><VBadge tone="success">{{ value === 'active' ? 'فعال' : value }}</VBadge></template
        ></VTable
      >
    </div>
    <div v-else class="mt-8">
      <VEmptyState
        title="هنوز پروژه‌ای ندارید"
        description="پس از ایجاد مشتری، برای هر هدف یا وب‌سایت یک پروژه بسازید."
        action-label="افزودن پروژه"
        @action="$inertia.visit('/app/projects/create')"
      /></div
  ></AppLayout>
</template>
