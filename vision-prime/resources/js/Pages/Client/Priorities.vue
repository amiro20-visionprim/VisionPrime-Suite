<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import { tips } from '@/lib/tips'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VGuideTip from '@/shared/ui/VGuideTip.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VIcon, { type IconName, type IconTone } from '@/shared/ui/VIcon.vue'

const typeIconMeta = (type: string): { icon: IconName; tone: IconTone } =>
  typeIcons[type] ?? { icon: 'lightbulb', tone: 'brand' }

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
  ctr_gap: 'جذابیت بیشتر در گوگل',
  keyword_opportunity: 'فرصت کلیدواژه',
  content_gap: 'شکاف محتوا',
  cannibalization: 'هم‌خواری کلیدواژه',
  revenue_opportunity: 'فرصت درآمدی',
}

const typeIcons: Record<string, { icon: IconName; tone: IconTone }> = {
  conversion_boost: { icon: 'zap', tone: 'success' },
  ctr_gap: { icon: 'search', tone: 'brand' },
  keyword_opportunity: { icon: 'trend-up', tone: 'violet' },
  content_gap: { icon: 'file', tone: 'neutral' },
  cannibalization: { icon: 'ban', tone: 'danger' },
  revenue_opportunity: { icon: 'shopping-bag', tone: 'success' },
}

const priorityLabels: Record<string, string> = {
  high: 'اولویت بالا',
  medium: 'اولویت متوسط',
  low: 'اولویت پایین',
}

const statusLabels: Record<string, string> = {
  draft: 'در حال آماده‌سازی',
  active: 'در حال انجام',
  completed: 'انجام شد',
  cancelled: 'لغو شده',
}

function scoreTone(score: number): string {
  if (score >= 80) return 'bg-success-500'
  if (score >= 65) return 'bg-warning-500'
  return 'bg-brand-600'
}
</script>

<template>
  <Head title="اولویت‌ها | پرتال مشتری" />
  <ClientPortalLayout>
    <VPageHeader
      title="اولویت‌ها"
      description="کجا می‌توانیم بهتر شویم؟ مهم‌ترین فرصت‌ها به‌ترتیب اولویت، با دلیل ساده."
    />

    <section class="mt-8">
      <div class="flex items-center gap-2">
        <h2 class="text-ink-strong text-lg font-bold">فرصت‌های رشد</h2>
        <VGuideTip :text="tips.opportunities" />
      </div>

      <div v-if="props.opportunities.length" class="mt-4 space-y-3">
        <VCard v-for="(opportunity, index) in props.opportunities" :key="opportunity.id">
          <div class="flex items-start gap-4">
            <div class="flex shrink-0 flex-col items-center gap-1.5">
              <span
                class="font-display text-ink-strong flex size-10 items-center justify-center rounded-full border border-brand-200 bg-brand-50 text-lg font-extrabold"
                >{{ index + 1 }}</span
              >
              <span
                :class="[
                  'rounded-ui flex size-8 items-center justify-center',
                  typeIconMeta(opportunity.type).tone === 'success'
                    ? 'bg-success-50'
                    : typeIconMeta(opportunity.type).tone === 'danger'
                      ? 'bg-danger-50'
                      : typeIconMeta(opportunity.type).tone === 'violet'
                        ? 'bg-violet-50'
                        : 'bg-brand-50',
                ]"
              >
                <VIcon
                  :name="typeIconMeta(opportunity.type).icon"
                  :tone="typeIconMeta(opportunity.type).tone"
                  size="sm"
                />
              </span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-3">
                <p class="text-ink-strong font-semibold">
                  {{ typeLabels[opportunity.type] ?? opportunity.type }}
                </p>
                <VBadge tone="neutral">{{ opportunity.site_name }}</VBadge>
              </div>

              <!-- نوار امتیاز -->
              <div class="mt-3">
                <div class="flex items-center justify-between text-xs">
                  <span class="text-ink-muted">میزان اهمیت</span>
                  <span class="text-ink-strong font-bold"
                    >{{ opportunity.score }} از ۱۰۰</span
                  >
                </div>
                <div class="bg-surface-muted mt-1.5 h-2 w-full overflow-hidden rounded-full">
                  <div
                    class="h-full rounded-full transition-all duration-700"
                    :class="scoreTone(opportunity.score)"
                    :style="{ width: `${opportunity.score}%` }"
                  />
                </div>
              </div>

              <p class="text-ink-muted mt-3 text-sm leading-6">{{ opportunity.explanation }}</p>
              <p class="text-ink-muted mt-2 text-xs">
                اطمینان تیم: {{ Math.round(opportunity.confidence * 100) }}٪ — بر اساس دادهٔ واقعی سرچ
                کنسول
              </p>
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
      <p class="text-ink-muted mt-1 text-sm">
        کارهایی که تیم برای رسیدن به این فرصت‌ها انجام می‌دهد.
      </p>
      <div v-if="props.recommendations.length" class="mt-4 space-y-3">
        <VCard v-for="recommendation in props.recommendations" :key="recommendation.id">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <p class="text-ink-strong font-semibold">{{ recommendation.title }}</p>
                <VBadge
                  :tone="
                    recommendation.priority === 'high'
                      ? 'danger'
                      : recommendation.priority === 'medium'
                        ? 'warning'
                        : 'info'
                  "
                  >{{ priorityLabels[recommendation.priority] ?? recommendation.priority }}</VBadge
                >
                <VBadge tone="neutral">
                  {{ statusLabels[recommendation.status] ?? recommendation.status }}
                </VBadge>
              </div>
              <p class="text-ink-muted mt-2 text-sm leading-6">{{ recommendation.body }}</p>
              <p class="text-ink-muted mt-3 text-xs">
                <VIcon name="building" size="sm" class="ms-0.5 inline" />
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
