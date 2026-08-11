<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
defineProps<{
  reports: {
    id: number
    type: string
    period_start: string
    period_end: string
    content: Record<string, unknown>
    published_at: string
  }[]
}>()
</script>
<template>
  <Head title="گزارش‌ها" /><ClientPortalLayout
    ><VPageHeader title="گزارش‌ها" description="خلاصه روند رشد، اولویت‌ها و گام‌های بعدی." />
    <div v-if="reports.length" class="mt-8 space-y-4">
      <VCard v-for="report in reports" :key="report.id" :title="report.type"
        ><p>{{ report.period_start }} تا {{ report.period_end }}</p>
        <p class="text-ink-muted mt-3 text-sm">فرصت‌های باز: {{ report.content.opportunities }}</p>
        <p class="text-ink-muted text-sm">ریسک‌های مهم: {{ report.content.high_risks }}</p>
        <p class="text-ink-muted text-sm">
          اقدام‌های پیشنهادی: {{ report.content.recommendations }}
        </p>
        <p class="text-ink-muted text-sm">تغییرات اجراشده: {{ report.content.impact_events ?? 0 }}</p></VCard
      >
    </div>
    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز گزارشی منتشر نشده است"
      description="وقتی تیم گزارش دوره‌ای را آماده و منتشر کند، در اینجا قابل مشاهده خواهد بود."
  /></ClientPortalLayout>
</template>
