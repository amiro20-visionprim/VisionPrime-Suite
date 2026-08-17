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

interface SubscriptionRow {
  id: number
  organization_name: string
  organization_id: number
  plan_name: string
  status: string
  status_label: string
  current_period_end: string
  auto_renew: boolean
  cancel_at_period_end: boolean
}

interface OrgOption {
  id: number
  name: string
}

interface PlanOption {
  id: number
  name: string
}

defineProps<{
  subscriptions: SubscriptionRow[]
  organizations: OrgOption[]
  plans: PlanOption[]
}>()

const createOpen = ref(false)
const form = useForm({ organization_id: '', plan_id: '', trial_days: '' })

function submit(): void {
  form.post('/platform/subscriptions', {
    onSuccess: () => {
      form.reset()
      createOpen.value = false
    },
  })
}

function action(subscription: SubscriptionRow, action: string): void {
  router.post(`/platform/subscriptions/${subscription.id}/action`, { action }, { preserveScroll: true })
}

const statusTone = (status: string): 'success' | 'warning' | 'danger' | 'neutral' | 'info' =>
  status === 'active' ? 'success' : status === 'trialing' ? 'info' : status === 'past_due' ? 'danger' : status === 'suspended' ? 'warning' : 'neutral'
</script>

<template>
  <Head title="اشتراک‌ها" />
  <PlatformLayout>
    <VPageHeader
      title="اشتراک‌ها"
      description="چرخهٔ حیات اشتراک هر سازمان — ثبت، تمدید، لغو و تعلیق."
    >
      <template #actions>
        <VButton size="sm" @click="createOpen = true">+ ثبت اشتراک</VButton>
      </template>
    </VPageHeader>

    <VCard class="mt-6">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-line text-ink-muted border-b text-start text-xs">
              <th class="px-4 py-3 text-start font-semibold">سازمان</th>
              <th class="px-4 py-3 text-start font-semibold">پلن</th>
              <th class="px-4 py-3 text-start font-semibold">وضعیت</th>
              <th class="px-4 py-3 text-start font-semibold">پایان دوره</th>
              <th class="px-4 py-3 text-start font-semibold">تمدید خودکار</th>
              <th class="px-4 py-3 text-start font-semibold">اکشن‌ها</th>
            </tr>
          </thead>
          <tbody class="divide-line divide-y">
            <tr v-for="subscription in subscriptions" :key="subscription.id" class="hover:bg-surface-muted/50 transition-colors">
              <td class="px-4 py-3 font-semibold">{{ subscription.organization_name }}</td>
              <td class="px-4 py-3">{{ subscription.plan_name }}</td>
              <td class="px-4 py-3">
                <VBadge :tone="statusTone(subscription.status)">{{ subscription.status_label }}</VBadge>
              </td>
              <td class="px-4 py-3 text-xs" dir="ltr">{{ subscription.current_period_end }}</td>
              <td class="px-4 py-3">{{ subscription.auto_renew ? 'فعال' : 'غیرفعال' }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <VButton size="sm" variant="ghost" @click="action(subscription, 'renew')">تمدید</VButton>
                  <VButton v-if="subscription.status === 'active' || subscription.status === 'trialing'" size="sm" variant="ghost" @click="action(subscription, 'cancel')">
                    {{ subscription.cancel_at_period_end ? 'لغو شده (پایان دوره)' : 'لغو' }}
                  </VButton>
                  <VButton v-if="subscription.status === 'suspended'" size="sm" variant="ghost" @click="action(subscription, 'reactivate')">فعال‌سازی</VButton>
                  <VButton v-if="subscription.status === 'active' || subscription.status === 'trialing'" size="sm" variant="danger" @click="action(subscription, 'suspend')">تعلیق</VButton>
                </div>
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
        <h3 class="text-ink-strong font-display text-lg font-bold">ثبت اشتراک</h3>
        <form class="mt-4 grid gap-3" @submit.prevent="submit">
          <VSelect
            v-model="form.organization_id"
            label="سازمان"
            :options="organizations.map((o) => ({ label: o.name, value: String(o.id) }))"
            :error="form.errors.organization_id"
          />
          <VSelect
            v-model="form.plan_id"
            label="پلن"
            :options="plans.map((p) => ({ label: p.name, value: String(p.id) }))"
            :error="form.errors.plan_id"
          />
          <VInput v-model="form.trial_days" label="دورهٔ آزمایشی (روز — اختیاری)" type="number" />
          <div class="flex justify-end gap-3">
            <VButton type="button" variant="ghost" size="sm" @click="createOpen = false">انصراف</VButton>
            <VButton type="submit" size="sm" :loading="form.processing">ثبت اشتراک</VButton>
          </div>
        </form>
      </div>
    </div>
  </PlatformLayout>
</template>
