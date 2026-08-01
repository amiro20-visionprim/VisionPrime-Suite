<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { GscPageMetric, Paginated } from '@/types/gsc'
defineProps<{ metrics: Paginated<GscPageMetric> }>()
</script>
<template>
  <Head title="GSC Pages" /><AppLayout
    ><VPageHeader title="عملکرد صفحات" description="کلیک، نمایش، CTR و رتبه صفحات در سرچ کنسول." />
    <div class="mt-8 overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr>
            <th>URL</th>
            <th>Clicks</th>
            <th>Impressions</th>
            <th>CTR</th>
            <th>Position</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="metric in metrics.data" :key="metric.id">
            <td class="font-latin py-3" dir="ltr">{{ metric.page_url }}</td>
            <td>{{ metric.clicks }}</td>
            <td>{{ metric.impressions }}</td>
            <td>{{ metric.ctr }}</td>
            <td>{{ metric.position }}</td>
          </tr>
        </tbody>
      </table>
    </div></AppLayout
  >
</template>
