<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import MetricFilters from '@/Pages/App/Gsc/MetricFilters.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { GscPageMetric, Paginated } from '@/types/gsc'

defineProps<{
  metrics: Paginated<GscPageMetric>
  sites: { id: number; name: string }[]
  filters: { site_id: string | null; date_from: string | null; date_to: string | null }
}>()
</script>
<template>
  <Head title="عملکرد صفحات" /><AppLayout
    ><VPageHeader title="عملکرد صفحات" description="کلیک، نمایش، CTR و رتبه صفحات در سرچ کنسول." />
    <MetricFilters class="mt-8" base-url="/app/gsc/pages" :sites="sites" :filters="filters" />
    <div class="mt-6 overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr>
            <th>URL</th>
            <th>کلیک</th>
            <th>نمایش</th>
            <th>نرخ کلیک</th>
            <th>جایگاه</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="metric in metrics.data" :key="metric.id">
            <td class="font-latin py-3" dir="ltr">{{ metric.page_url }}</td>
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
