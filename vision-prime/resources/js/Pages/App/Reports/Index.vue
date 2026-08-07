<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Paginated, Report } from '@/types/reporting'
defineProps<{ reports: Paginated<Report> }>()
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
        <VInput v-model="form.site_id" label="شناسه سایت" /><VInput
          v-model="form.type"
          label="نوع گزارش"
        /><VInput v-model="form.period_start" label="شروع دوره" type="date" /><VInput
          v-model="form.period_end"
          label="پایان دوره"
          type="date"
        /><VButton type="submit">ایجاد گزارش</VButton>
      </form></VCard
    ><VCard class="mt-6" title="گزارش‌های اخیر"
      ><div v-for="report in reports.data" :key="report.id" class="border-line border-b py-3">
        {{ report.type }} — {{ report.status }}
      </div></VCard
    ></AppLayout
  >
</template>
