<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface ClientOpportunity {
  id: number
  type: string
  score: number
  confidence: number
  explanation: string
  site_name: string
}

interface ClientRecommendation {
  id: number
  title: string
  body: string
  priority: string
  status: string
  due_at: string | null
  created_at: string
  site_name: string
  owner_name: string | null
}

const props = defineProps<{
  opportunities: ClientOpportunity[]
  recommendations: ClientRecommendation[]
}>()

const typeLabels: Record<string, string> = {
  conversion_boost: 'بهبود تبدیل',
  ctr_gap: 'شکاف نرخ کلیک',
  keyword_opportunity: 'فرصت کلیدواژه',
  content_gap: 'شکاف محتوا',
  cannibalization: 'هم‌خواری کلیدواژه',
  revenue_opportunity: 'فرصت درآمدی',
}

const priorityTones: Record<string, 'danger' | 'warning' | 'info' | 'neutral'> = {
  high: 'danger',
  medium: 'warning',
  low: 'info',
}
</script>

<template>
  <Head title="اولویت‌ها" />
  <ClientPortalLayout>
    <VPageHeader
      title="اولویت‌های این دوره"
      description="مهم‌ترین فرصت‌ها و اقدام‌های در دست انجام، به‌همراه مهلت و مسئول هر اقدام."
    />

    <section class="mt-8">
      <h2 class="text-ink-strong text-lg font-bold">فرصت‌های رشد</h2>
      <div v-if="props.opportunities.length" class="mt-4 space-y-4">
        <VCard v-for="opportunity in props.opportunities" :key="opportunity.id">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <p class="text-ink-strong font-semibold">
                  {{ typeLabels[opportunity.type] ?? opportunity.type }}
                </p>
                <VBadge tone="neutral">{{ opportunity.site_name }}</VBadge>
              </div>
              <p class="text-ink-muted mt-2 text-sm leading-6">{{ opportunity.explanation }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <VBadge tone="warning">امتیاز {{ opportunity.score }}</VBadge>
              <VBadge tone="info">اطمینان {{ Math.round(opportunity.confidence * 100) }}٪</VBadge>
            </div>
          </div>
        </VCard>
      </div>
      <VEmptyState
        v-else
        class="mt-4"
        title="فرصت جدیدی شناسایی نشده است"
        description="به محض تحلیل داده‌های سایت، مهم‌ترین فرصت‌های رشد در اینجا نمایش داده می‌شوند."
      />
    </section>

    <section class="mt-10">
      <h2 class="text-ink-strong text-lg font-bold">اقدام‌های در دست انجام</h2>
      <div v-if="props.recommendations.length" class="mt-4 space-y-4">
        <VCard v-for="recommendation in props.recommendations" :key="recommendation.id">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <p class="text-ink-strong font-semibold">{{ recommendation.title }}</p>
                <VBadge :tone="priorityTones[recommendation.priority] ?? 'neutral'">{{
                  recommendation.priority === 'high'
                    ? 'اولویت بالا'
                    : recommendation.priority === 'medium'
                      ? 'اولویت متوسط'
                      : 'اولویت پایین'
                }}</VBadge>
              </div>
              <p class="text-ink-muted mt-2 text-sm leading-6">{{ recommendation.body }}</p>
              <p class="text-ink-muted mt-3 text-xs">
                {{ recommendation.site_name }}
                <template v-if="recommendation.owner_name"> · {{ recommendation.owner_name }}</template>
                <template v-if="recommendation.due_at">
                  · مهلت: {{ formatJalaliDate(recommendation.due_at) }}
                </template>
              </p>
            </div>
          </div>
        </VCard>
      </div>
      <VEmptyState
        v-else
        class="mt-4"
        title="اقدام فعالی وجود ندارد"
        description="پیشنهادهای فعال و مهلت‌دار برای بهبود رشد سایت در اینجا نمایش داده می‌شوند."
      />
    </section>
  </ClientPortalLayout>
</template>
