<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { ConversionRisk, Paginated } from '@/types/seo'

const props = defineProps<{
  risks: Paginated<ConversionRisk>
  filters: { severity: string | null }
}>()

const severityLabels: Record<string, string> = {
  low: 'کم',
  medium: 'متوسط',
  high: 'زیاد',
}

const severityTone: Record<string, 'info' | 'warning' | 'danger'> = {
  low: 'info',
  medium: 'warning',
  high: 'danger',
}

const severityOptions = [
  { label: 'همه', value: '' },
  { label: 'زیاد', value: 'high' },
  { label: 'متوسط', value: 'medium' },
  { label: 'کم', value: 'low' },
]

function applySeverity(value: string): void {
  router.get(
    '/app/conversion-risks',
    value ? { severity: value } : {},
    { preserveState: true, replace: true },
  )
}
</script>
<template>
  <Head title="ریسک‌های تبدیل" /><AppLayout
    ><VPageHeader
      title="ریسک‌های تبدیل"
      description="ریسک‌هایی که مانع تبدیل بازدیدکننده به مشتری می‌شوند."
    />
    <div class="mt-8 flex items-center gap-3">
      <label class="text-ink-strong text-sm font-semibold">فیلتر سطح ریسک:</label>
      <select
        class="border-line rounded-ui min-h-10 w-44 border bg-surface px-3 text-sm"
        :value="props.filters.severity ?? ''"
        @change="applySeverity(($event.target as HTMLSelectElement).value)"
      >
        <option v-for="option in severityOptions" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
    </div>
    <div v-if="risks.data.length" class="mt-6 space-y-3">
      <Link
        v-for="risk in risks.data"
        :key="risk.id"
        :href="risk.audit_id ? `/app/money-pages/${risk.audit_id}` : `/app/url-profiles/${risk.url_profile_id}`"
        class="rounded-card border-line bg-surface block border p-5 transition-colors hover:border-brand-300"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="font-latin text-ink-strong truncate text-sm" dir="ltr">
              {{ risk.canonical_url }}
            </p>
            <p class="text-ink-muted mt-2 text-sm leading-6">{{ risk.explanation }}</p>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <VBadge tone="warning">امتیاز {{ risk.score }}</VBadge>
            <VBadge :tone="severityTone[risk.severity] ?? 'info'">
              {{ severityLabels[risk.severity] ?? risk.severity }}
            </VBadge>
          </div>
        </div>
      </Link>
    </div>
    <VEmptyState
      v-else
      class="mt-8"
      title="ریسک تبدیلی ثبت نشده است"
      description="پس از اجرای تحلیل رشد، ریسک‌های تبدیل صفحات درآمدزا اینجا نمایش داده می‌شوند."
    />
  </AppLayout>
</template>
