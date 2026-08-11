<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import MetricFilters from '@/Pages/App/Gsc/MetricFilters.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { GscQueryMetric, Paginated } from '@/types/gsc'

defineProps<{
  metrics: Paginated<GscQueryMetric>
  sites: { id: number; name: string }[]
  filters: { site_id: string | null; date_from: string | null; date_to: string | null }
}>()
</script>
<template>
  <Head title="جستارها" /><AppLayout
    ><VPageHeader
      title="عملکرد جستارها"
      description="جستجوهای کاربران و عملکرد آن‌ها در سرچ کنسول."
    />
    <MetricFilters
      class="mt-8"
      base-url="/app/gsc/queries"
      :sites="sites"
      :filters="filters"
    />
    <div class="mt-6 overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr>
            <th>جستار</th>
            <th>کلیک</th>
            <th>نمایش</th>
            <th>نرخ کلیک</th>
            <th>جایگاه</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="metric in metrics.data" :key="metric.id">
            <td class="font-latin py-3" dir="auto">{{ metric.query }}</td>
            <td>{{ metric.clicks }}</td>
            <td>{{ metric.impressions }}</td>
            <td>{{ (metric.ctr * 100).toFixed(1) }}٪</td>
            <td>{{ metric.position?.toFixed(1) ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div></AppLayout
  >
</template>
