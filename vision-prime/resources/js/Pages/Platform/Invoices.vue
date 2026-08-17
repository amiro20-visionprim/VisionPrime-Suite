<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface InvoiceRow {
  id: number
  organization_name: string
  plan_name: string
  number: string
  amount: number
  tax: number
  total: number
  status: string
  status_label: string
  due_at: string
}

interface SubscriptionOption {
  id: number
  label: string
}

defineProps<{
  invoices: InvoiceRow[]
  subscriptions: SubscriptionOption[]
}>()

const createOpen = ref(false)
const form = useForm({ subscription_id: '' })

function submit(): void {
  form.post('/platform/invoices', {
    onSuccess: () => {
      form.reset()
      createOpen.value = false
    },
  })
}

function overdueCheck(): void {
  router.post('/platform/invoices/overdue-check', {}, { preserveScroll: true })
}

const statusTone = (status: string): 'success' | 'warning' | 'danger' | 'neutral' | 'info' =>
  status === 'paid' ? 'success' : status === 'overdue' ? 'danger' : status === 'issued' ? 'info' : status === 'canceled' ? 'neutral' : 'warning'

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)
</script>

<template>
  <Head title="فاکتورها" />
  <PlatformLayout>
    <VPageHeader
      title="فاکتورها"
      description="صدور، پیگیری و تشخیص فاکتورهای معوق."
    >
      <template #actions>
        <VButton size="sm" variant="ghost" @click="overdueCheck">اسکن فاکتورهای معوق</VButton>
        <VButton size="sm" @click="createOpen = true">+ صدور فاکتور</VButton>
      </template>
    </VPageHeader>

    <VCard class="mt-6">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-line text-ink-muted border-b text-start text-xs">
              <th class="px-4 py-3 text-start font-semibold">شماره</th>
              <th class="px-4 py-3 text-start font-semibold">سازمان</th>
              <th class="px-4 py-3 text-start font-semibold">پلن</th>
              <th class="px-4 py-3 text-start font-semibold">مبلغ</th>
              <th class="px-4 py-3 text-start font-semibold">مالیات (۹٪)</th>
              <th class="px-4 py-3 text-start font-semibold">جمع</th>
              <th class="px-4 py-3 text-start font-semibold">وضعیت</th>
              <th class="px-4 py-3 text-start font-semibold">سررسید</th>
            </tr>
          </thead>
          <tbody class="divide-line divide-y">
            <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-surface-muted/50 transition-colors">
              <td class="font-latin px-4 py-3 text-xs" dir="ltr">{{ invoice.number }}</td>
              <td class="px-4 py-3 font-semibold">{{ invoice.organization_name }}</td>
              <td class="px-4 py-3">{{ invoice.plan_name || '—' }}</td>
              <td class="px-4 py-3">{{ faNum(invoice.amount) }}</td>
              <td class="px-4 py-3">{{ faNum(invoice.tax) }}</td>
              <td class="px-4 py-3 font-bold">{{ faNum(invoice.total) }}</td>
              <td class="px-4 py-3"><VBadge :tone="statusTone(invoice.status)">{{ invoice.status_label }}</VBadge></td>
              <td class="text-ink-muted px-4 py-3 text-xs" dir="ltr">{{ invoice.due_at }}</td>
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
        <h3 class="text-ink-strong font-display text-lg font-bold">صدور فاکتور</h3>
        <form class="mt-4 grid gap-3" @submit.prevent="submit">
          <VSelect
            v-model="form.subscription_id"
            label="اشتراک"
            :options="subscriptions.map((s) => ({ label: s.label, value: String(s.id) }))"
            :error="form.errors.subscription_id"
          />
          <div class="flex justify-end gap-3">
            <VButton type="button" variant="ghost" size="sm" @click="createOpen = false">انصراف</VButton>
            <VButton type="submit" size="sm" :loading="form.processing">صدور فاکتور</VButton>
          </div>
        </form>
      </div>
    </div>
  </PlatformLayout>
</template>
