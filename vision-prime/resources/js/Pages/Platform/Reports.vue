<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VStatCard from '@/shared/ui/VStatCard.vue'
import VBarChart from '@/shared/ui/VBarChart.vue'

interface BarPoint {
  label: string
  value: number
}

defineProps<{
  revenueByMonth: BarPoint[]
  newOrgsPerWeek: BarPoint[]
  summary: {
    revenue_year: number
    orgs_active: number
    clients_total: number
    sites_connected: number
    sites_total: number
  }
}>()

function exportCsv(): void {
  router.get('/platform/reports/export', {}, { preserveState: true })
}

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)
</script>

<template>
  <Head title="گزارش‌ها" />
  <PlatformLayout>
    <VPageHeader
      title="گزارش‌های پلتفرم"
      description="درآمد، رشد و سلامت کل اکوسیستم — به‌همراه خروجی CSV."
    >
      <template #actions>
        <VButton size="sm" variant="ghost" @click="exportCsv">خروجی CSV پرداخت‌ها</VButton>
      </template>
    </VPageHeader>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <VStatCard
        label="درآمد ۱۲ ماه"
        :value="summary.revenue_year"
        icon="chart-bar"
        icon-tone="success"
        hint="تومان"
      />
      <VStatCard
        label="سازمان‌های فعال"
        :value="summary.orgs_active"
        icon="building"
        icon-tone="brand"
      />
      <VStatCard
        label="مشتریان کل"
        :value="summary.clients_total"
        icon="users"
        icon-tone="violet"
      />
      <VStatCard
        label="سایت‌های متصل"
        :value="summary.sites_connected"
        icon="activity"
        icon-tone="success"
        :hint="`از ${faNum(summary.sites_total)} سایت`"
      />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <VCard title="درآمد ماهانه — ۱۲ ماه اخیر" description="پرداخت‌های موفق به تومان">
        <VBarChart :data="revenueByMonth" :height="240" />
      </VCard>
      <VCard title="سازمان‌های جدید در هفته — ۳ ماه اخیر" description="رشد اکوسیستم">
        <VBarChart :data="newOrgsPerWeek" :height="240" />
      </VCard>
    </div>
  </PlatformLayout>
</template>
