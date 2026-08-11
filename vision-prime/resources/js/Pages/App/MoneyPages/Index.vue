<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { MoneyPageAudit, Paginated } from '@/types/seo'

defineProps<{ audits: Paginated<MoneyPageAudit> }>()

function scoreTone(score: number): 'success' | 'warning' | 'danger' | 'info' {
  if (score >= 80) return 'success'
  if (score >= 60) return 'warning'
  return 'danger'
}
</script>
<template>
  <Head title="صفحات درآمدزا" /><AppLayout
    ><VPageHeader
      title="صفحات درآمدزا"
      description="صفحه‌های تجاری با بیشترین ظرفیت اثر SEO و Conversion."
    />
    <div v-if="audits.data.length" class="mt-8 space-y-3">
      <Link
        v-for="audit in audits.data"
        :key="audit.id"
        :href="`/app/money-pages/${audit.id}`"
        class="rounded-card border-line bg-surface block border p-5 transition-colors hover:border-brand-300"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="font-latin text-ink-strong truncate text-sm" dir="ltr">
              {{ audit.canonical_url }}
            </p>
            <p class="text-ink-muted mt-1 text-sm">
              {{ audit.site_name ?? '' }} · بازبینی در
              {{ audit.audited_at ? formatJalaliDate(audit.audited_at) : '—' }}
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <VBadge v-if="audit.issues_count" tone="warning">
              {{ audit.issues_count }} مشکل
            </VBadge>
            <VBadge :tone="scoreTone(audit.score)">امتیاز {{ audit.score }}</VBadge>
          </div>
        </div>
      </Link>
    </div>
    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز صفحهٔ درآمدزایی ممیزی نشده است"
      description="پس از اتصال سرچ کنسول و اجرای تحلیل رشد، صفحات درآمدزا با امتیاز و مشکلات اینجا نمایش داده می‌شوند."
    />
  </AppLayout>
</template>
