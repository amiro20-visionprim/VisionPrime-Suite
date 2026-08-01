<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { GscQueryMetric, Paginated } from '@/types/gsc'
defineProps<{ metrics: Paginated<GscQueryMetric> }>()
</script>
<template>
  <Head title="GSC Queries" /><AppLayout
    ><VPageHeader
      title="عملکرد Queryها"
      description="جستجوهای کاربران و عملکرد آن‌ها در سرچ کنسول."
    />
    <div class="mt-8 overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr>
            <th>Query</th>
            <th>Clicks</th>
            <th>Impressions</th>
            <th>CTR</th>
            <th>Position</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="metric in metrics.data" :key="metric.id">
            <td class="font-latin py-3" dir="auto">{{ metric.query }}</td>
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
