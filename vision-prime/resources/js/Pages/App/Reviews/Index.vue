<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import { labelOf, reviewStatusLabels, reviewSubjectLabels } from '@/lib/labels'
import VBadge from '@/shared/ui/VBadge.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Paginated, ReviewItem } from '@/types/review'

interface ReviewRow extends ReviewItem {
  site_name: string
  subject_label: string | null
  created_at: string
}

defineProps<{ items: Paginated<ReviewRow> }>()

const statusTone: Record<string, 'success' | 'danger' | 'warning' | 'info'> = {
  approved: 'success',
  rejected: 'danger',
  pending_review: 'warning',
  pending_approval: 'warning',
  completed: 'info',
}
</script>
<template>
  <Head title="بررسی و تأییدها" /><AppLayout
    ><VPageHeader
      title="بررسی و تأییدها"
      description="خروجی‌های AI و توصیه‌های نیازمند تصمیم انسانی."
    />
    <div v-if="items.data.length" class="mt-8 space-y-3">
      <Link
        v-for="item in items.data"
        :key="item.id"
        :href="`/app/reviews/${item.id}`"
        class="rounded-card border-line bg-surface block border p-5 transition-colors hover:border-brand-300"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-ink-strong text-sm font-semibold">
                {{ labelOf(reviewSubjectLabels, item.subject_type) }}
              </span>
              <span class="text-ink-muted text-xs">سایت: {{ item.site_name }}</span>
            </div>
            <p v-if="item.subject_label" class="text-ink-muted mt-2 text-sm leading-6">
              {{ item.subject_label }}
            </p>
            <p class="text-ink-muted mt-1 text-xs">
              ثبت در {{ formatJalaliDate(item.created_at) }}
            </p>
          </div>
          <VBadge :tone="statusTone[item.status] ?? 'info'">
            {{ labelOf(reviewStatusLabels, item.status) }}
          </VBadge>
        </div>
      </Link>
    </div>
    <VEmptyState
      v-else
      class="mt-8"
      title="موردی برای بازبینی وجود ندارد"
      description="بازبینی‌ها از خروجی تحلیل رشد و پیشنویس‌های هوش مصنوعی ساخته می‌شوند."
    />
  </AppLayout>
</template>
