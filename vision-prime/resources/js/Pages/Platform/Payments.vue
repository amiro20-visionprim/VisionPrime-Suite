<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface PaymentRow {
  id: number
  organization_name: string
  organization_id: number
  amount: number
  method: string
  status: string
  status_label: string
  reference: string
  paid_at: string
}

interface OrgOption {
  id: number
  name: string
}

defineProps<{
  payments: PaymentRow[]
  organizations: OrgOption[]
}>()

const createOpen = ref(false)
const form = useForm({ organization_id: '', amount: '', method: 'manual' })

function submit(): void {
  form.post('/platform/payments', {
    onSuccess: () => {
      form.reset()
      createOpen.value = false
    },
  })
}

function action(payment: PaymentRow): void {
  router.post(`/platform/payments/${payment.id}/action`, {}, { preserveScroll: true })
}

const statusTone = (status: string): 'success' | 'warning' | 'danger' | 'neutral' | 'info' =>
  status === 'paid' ? 'success' : status === 'pending' ? 'info' : status === 'failed' ? 'danger' : 'neutral'

const methodLabels: Record<string, string> = {
  zarinpal: 'زرین‌پال',
  idpay: 'آی‌دی‌پی',
  manual: 'دستی',
  bank: 'انتقال بانکی',
}

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)
</script>

<template>
  <Head title="پرداخت‌ها" />
  <PlatformLayout>
    <VPageHeader
      title="پرداخت‌ها"
      description="درآمد و وضعیت پرداخت‌های همهٔ سازمان‌ها."
    >
      <template #actions>
        <VButton size="sm" @click="createOpen = true">+ ثبت پرداخت</VButton>
      </template>
    </VPageHeader>

    <VCard class="mt-6">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-line text-ink-muted border-b text-start text-xs">
              <th class="px-4 py-3 text-start font-semibold">سازمان</th>
              <th class="px-4 py-3 text-start font-semibold">مبلغ</th>
              <th class="px-4 py-3 text-start font-semibold">روش</th>
              <th class="px-4 py-3 text-start font-semibold">وضعیت</th>
              <th class="px-4 py-3 text-start font-semibold">مرجع</th>
              <th class="px-4 py-3 text-start font-semibold">تاریخ</th>
              <th class="px-4 py-3 text-start font-semibold"></th>
            </tr>
          </thead>
          <tbody class="divide-line divide-y">
            <tr v-for="payment in payments" :key="payment.id" class="hover:bg-surface-muted/50 transition-colors">
              <td class="px-4 py-3 font-semibold">{{ payment.organization_name }}</td>
              <td class="px-4 py-3 font-semibold">{{ faNum(payment.amount) }} تومان</td>
              <td class="px-4 py-3">{{ methodLabels[payment.method] ?? payment.method }}</td>
              <td class="px-4 py-3"><VBadge :tone="statusTone(payment.status)">{{ payment.status_label }}</VBadge></td>
              <td class="font-latin text-ink-muted px-4 py-3 text-xs" dir="ltr">{{ payment.reference }}</td>
              <td class="text-ink-muted px-4 py-3 text-xs" dir="ltr">{{ payment.paid_at }}</td>
              <td class="px-4 py-3">
                <VButton v-if="payment.status === 'pending'" size="sm" variant="ghost" @click="action(payment)">تأیید</VButton>
                <VButton v-else-if="payment.status === 'paid'" size="sm" variant="danger" @click="action(payment)">بازگشت</VButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </VCard>

    <div
      v-if="createOpen"
      class="bg-ink-900/40 fixed inset-0 z-50 flex items-center justify-center p-4"
      @click.self="createOpen = false"
    >
      <div class="bg-surface rounded-2xl w-full max-w-md p-6 shadow-2xl">
        <h3 class="text-ink-strong font-display text-lg font-bold">ثبت پرداخت</h3>
        <form class="mt-4 grid gap-3" @submit.prevent="submit">
          <VSelect
            v-model="form.organization_id"
            label="سازمان"
            :options="organizations.map((o) => ({ label: o.name, value: String(o.id) }))"
            :error="form.errors.organization_id"
          />
          <VInput v-model="form.amount" label="مبلغ (تومان)" type="number" :error="form.errors.amount" />
          <VSelect
            v-model="form.method"
            label="روش"
            :options="[
              { label: 'دستی', value: 'manual' },
              { label: 'انتقال بانکی', value: 'bank' },
              { label: 'زرین‌پال', value: 'zarinpal' },
              { label: 'آی‌دی‌پی', value: 'idpay' },
            ]"
          />
          <div class="flex justify-end gap-3">
            <VButton type="button" variant="ghost" size="sm" @click="createOpen = false">انصراف</VButton>
            <VButton type="submit" size="sm" :loading="form.processing">ثبت پرداخت</VButton>
          </div>
        </form>
      </div>
    </div>
  </PlatformLayout>
</template>
