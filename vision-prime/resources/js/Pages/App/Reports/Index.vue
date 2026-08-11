<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Paginated, Report } from '@/types/reporting'

defineProps<{ reports: Paginated<Report>; sites: { id: number; name: string }[] }>()

const reportTypes: { value: string; label: string }[] = [
  { value: 'executive_seo_summary', label: 'خلاصه اجرایی سئو' },
  { value: 'growth_report', label: 'گزارش رشد' },
  { value: 'priorities_report', label: 'گزارش اقدامات اولویت‌دار' },
]

const reportTypeLabels: Record<string, string> = Object.fromEntries(
  reportTypes.map((t) => [t.value, t.label]),
)

const reportStatusLabels: Record<string, string> = {
  draft: 'پیش‌نویس',
  generating: 'در حال تولید',
  ready: 'آماده',
  published: 'منتشر شده',
}

const form = useForm({
  site_id: '',
  type: 'executive_seo_summary',
  period_start: '',
  period_end: '',
})

function generate() {
  form.post('/app/reports')
}
</script>
<template>
  <Head title="گزارش‌ها" /><AppLayout
    ><VPageHeader
      title="گزارش‌ها"
      description="گزارش‌های مدیریتی، رشد و اقدامات اولویت‌دار."
    /><VCard class="mt-8" title="ایجاد گزارش"
      ><form class="grid gap-4 sm:grid-cols-2" @submit.prevent="generate">
        <label class="text-ink-strong block text-sm font-semibold">
          سایت
          <select v-model="form.site_id" class="mt-1 w-full" required>
            <option value="" disabled>یک سایت انتخاب کنید</option>
            <option v-for="site in sites" :key="site.id" :value="String(site.id)">
              {{ site.name }}
            </option>
          </select>
        </label>
        <label class="text-ink-strong block text-sm font-semibold">
          نوع گزارش
          <select v-model="form.type" class="mt-1 w-full">
            <option v-for="type in reportTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </label>
        <VInput v-model="form.period_start" label="شروع دوره" type="date" />
        <VInput v-model="form.period_end" label="پایان دوره" type="date" />
        <VButton type="submit" :loading="form.processing">ایجاد گزارش</VButton>
      </form></VCard
    ><VCard class="mt-6" title="گزارش‌های اخیر"
      ><div
        v-for="report in reports.data"
        :key="report.id"
        class="border-line flex flex-wrap items-center justify-between gap-2 border-b py-3"
      >
        <span class="text-ink-strong text-sm">
          {{ reportTypeLabels[report.type] ?? report.type }}
        </span>
        <span class="text-ink-muted text-sm">
          {{ reportStatusLabels[report.status] ?? report.status }}
        </span>
      </div></VCard
    ></AppLayout
  >
</template>
