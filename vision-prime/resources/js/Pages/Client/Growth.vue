<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface GrowthSummary {
  priority_money_pages: number
  high_conversion_risks: number
  recommendations: number
}

interface ClientOpportunity {
  id: number
  type: string
  score: number
  explanation: string
  site_name: string
}

const props = defineProps<{
  growthSummary: GrowthSummary | null
  opportunities: ClientOpportunity[]
}>()

const typeLabels: Record<string, string> = {
  conversion_boost: 'بهبود تبدیل',
  ctr_gap: 'شکاف نرخ کلیک',
  keyword_opportunity: 'فرصت کلیدواژه',
  content_gap: 'شکاف محتوا',
  cannibalization: 'هم‌خواری کلیدواژه',
}
</script>

<template>
  <Head title="رشد و فرصت‌ها" />
  <ClientPortalLayout>
    <VPageHeader
      title="رشد و فرصت‌ها"
      description="روند رشد و فرصت‌های کلیدی سایت شما در یک نمای ساده و مدیریتی."
    />

    <div v-if="growthSummary" class="mt-8 grid gap-4 sm:grid-cols-3">
      <VCard title="صفحات درآمدزا نیازمند توجه"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ growthSummary.priority_money_pages }}</p>
        <p class="text-ink-muted mt-1 text-sm">صفحه‌هایی که با کمی بهبود می‌توانند عملکرد بهتری داشته باشند</p></VCard
      >
      <VCard title="ریسک‌های بالای تبدیل"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ growthSummary.high_conversion_risks }}</p>
        <p class="text-ink-muted mt-1 text-sm">موردهایی که می‌توانند مانع تبدیل بازدیدکننده به مشتری شوند</p></VCard
      >
      <VCard title="پیشنهادهای فعال"
        ><p class="text-ink-strong mt-2 text-2xl font-bold">{{ growthSummary.recommendations }}</p>
        <p class="text-ink-muted mt-1 text-sm">اقدام‌هایی که در حال انجام هستند</p></VCard
      >
    </div>

    <VCard class="mt-8" title="فرصت‌های مهم این بازه">
      <div v-if="props.opportunities.length" class="space-y-4">
        <div
          v-for="opportunity in props.opportunities"
          :key="opportunity.id"
          class="border-line border-b pb-4"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-ink-strong font-semibold">
                {{ typeLabels[opportunity.type] ?? opportunity.type }}
              </p>
              <p class="text-ink-muted mt-1 text-sm leading-6">{{ opportunity.explanation }}</p>
            </div>
            <VBadge tone="warning">امتیاز {{ opportunity.score }}</VBadge>
          </div>
        </div>
      </div>
      <p v-else class="text-ink-muted text-sm leading-7">
        در حال حاضر فرصت جدیدی برای این بازه ثبت نشده است.
      </p>
    </VCard>

    <VEmptyState
      v-if="!growthSummary"
      class="mt-8"
      title="داده‌ای برای نمایش نیست"
      description="پس از اتصال منابع داده، خلاصه رشد و فرصت‌ها اینجا نمایش داده می‌شود."
    />
  </ClientPortalLayout>
</template>
