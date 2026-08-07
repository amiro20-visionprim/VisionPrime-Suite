<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface Opportunity {
  id: number
  type: string
  score: number
  confidence: number
  status: string
  explanation: string
  created_at: string
  site_id: number
  canonical_url: string | null
  query_normalized: string | null
  site_name: string | null
}

interface OpportunityFactor {
  id: number
  key: string
  weight: number
  explanation: string
}

const props = defineProps<{ opportunity: Opportunity; factors: OpportunityFactor[] }>()

const form = useForm({})

const typeLabels: Record<string, string> = {
  conversion_boost: 'بهبود تبدیل',
  ctr_gap: 'شکاف نرخ کلیک',
  keyword_opportunity: 'فرصت کلیدواژه',
  content_gap: 'شکاف محتوا',
  cannibalization: 'هم‌خواری کلیدواژه',
}

const statusLabels: Record<string, { label: string; tone: 'info' | 'success' | 'neutral' }> = {
  open: { label: 'باز', tone: 'info' },
  recommended: { label: 'تبدیل به پیشنهاد', tone: 'success' },
  closed: { label: 'بسته', tone: 'neutral' },
}

const factorLabels: Record<string, string> = {
  ctr_gap: 'شکاف نرخ کلیک (CTR)',
  page_quality: 'کیفیت صفحه',
  intent_match: 'هم‌خوانی با قصد جستجو',
  search_volume: 'حجم جستجو',
  keyword_relevance: 'ارتباط کلیدواژه',
  competitiveness: 'سختی رقابت',
  business_value: 'ارزش کسب‌وکاری',
  current_rank: 'رتبه فعلی',
  content_depth: 'عمق محتوا',
  authority: 'اعتبار صفحه',
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    dateStyle: 'medium',
    timeZone: 'Asia/Tehran',
  }).format(new Date(value))
}

function convertToRecommendation(): void {
  form.post(`/app/opportunities/${props.opportunity.id}/recommendation`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="جزئیات فرصت رشد" />
  <AppLayout>
    <VPageHeader
      title="جزئیات فرصت رشد"
      :description="opportunity.explanation"
      :breadcrumbs="[
        { label: 'فرصت‌های رشد', href: '/app/opportunities' },
        { label: 'جزئیات' },
      ]"
    >
      <template #actions>
        <VButton :loading="form.processing" @click="convertToRecommendation"
          >تبدیل به پیشنهاد</VButton
        >
      </template>
    </VPageHeader>

    <div class="mt-8 grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
      <div class="space-y-5">
        <VCard title="اطلاعات فرصت">
          <dl class="space-y-4 text-sm">
            <div class="flex items-start justify-between gap-4">
              <dt class="text-ink-muted shrink-0">نوع فرصت</dt>
              <dd class="text-ink-strong text-end font-medium">
                {{ typeLabels[opportunity.type] ?? opportunity.type }}
              </dd>
            </div>
            <div class="flex items-start justify-between gap-4">
              <dt class="text-ink-muted shrink-0">وضعیت</dt>
              <dd>
                <VBadge :tone="statusLabels[opportunity.status]?.tone ?? 'neutral'">{{
                  statusLabels[opportunity.status]?.label ?? opportunity.status
                }}</VBadge>
              </dd>
            </div>
            <div v-if="opportunity.site_name" class="flex items-start justify-between gap-4">
              <dt class="text-ink-muted shrink-0">سایت</dt>
              <dd class="text-ink-strong text-end font-medium">{{ opportunity.site_name }}</dd>
            </div>
            <div v-if="opportunity.canonical_url" class="flex items-start justify-between gap-4">
              <dt class="text-ink-muted shrink-0">صفحه</dt>
              <dd class="font-latin text-ink-strong text-end text-xs font-medium" dir="ltr">
                {{ opportunity.canonical_url }}
              </dd>
            </div>
            <div
              v-if="opportunity.query_normalized"
              class="flex items-start justify-between gap-4"
            >
              <dt class="text-ink-muted shrink-0">کلیدواژه</dt>
              <dd class="text-ink-strong text-end font-medium">
                {{ opportunity.query_normalized }}
              </dd>
            </div>
            <div class="flex items-start justify-between gap-4">
              <dt class="text-ink-muted shrink-0">تاریخ شناسایی</dt>
              <dd class="text-ink-strong text-end font-medium">
                {{ formatDate(opportunity.created_at) }}
              </dd>
            </div>
          </dl>
        </VCard>

        <VCard title="پتانسیل رشد">
          <div class="grid grid-cols-2 gap-4">
            <div class="rounded-card bg-brand-50 p-4">
              <p class="text-ink-muted text-xs">امتیاز فرصت</p>
              <p class="text-brand-900 mt-2 text-3xl font-bold">{{ opportunity.score }}</p>
            </div>
            <div class="rounded-card bg-surface-muted p-4">
              <p class="text-ink-muted text-xs">سطح اطمینان</p>
              <p class="text-ink-strong mt-2 text-3xl font-bold">
                {{ Math.round(opportunity.confidence * 100) }}٪
              </p>
            </div>
          </div>
          <p class="text-ink-muted mt-4 text-sm leading-7">
            این امتیاز از ترکیب دادهٔ سرچ کنسول، وضعیت صفحه و ارزش کسب‌وکاری محاسبه شده است.
          </p>
        </VCard>
      </div>

      <VCard title="تحلیل عوامل مؤثر">
        <p class="text-ink-muted mb-4 text-sm leading-7">
          سهم هر عامل در امتیاز نهایی به‌همراه توضیح محاسبه:
        </p>
        <div v-if="factors.length" class="space-y-5">
          <div v-for="factor in factors" :key="factor.id" class="border-line border-b pb-4">
            <div class="flex items-center justify-between gap-3">
              <span class="text-ink-strong text-sm font-semibold">{{
                factorLabels[factor.key] ?? factor.key
              }}</span>
              <span class="text-ink-muted text-sm">وزن: {{ factor.weight }}</span>
            </div>
            <p class="text-ink-muted mt-2 text-sm leading-6">{{ factor.explanation }}</p>
          </div>
        </div>
        <p v-else class="text-ink-muted text-sm leading-7">
          برای این فرصت جزئیات عامل جداگانه‌ای ثبت نشده است.
        </p>
      </VCard>
    </div>
  </AppLayout>
</template>
