<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface PlanRow {
  id: number
  key: string
  name: string
  description: string | null
  price_monthly: number
  price_yearly: number
  limits: Record<string, number>
  features: Record<string, unknown>
  is_active: boolean
  subscriptions_count: number
}

defineProps<{ plans: PlanRow[] }>()

const createOpen = ref(false)

const form = useForm({
  key: '',
  name: '',
  description: '',
  price_monthly: '',
  price_yearly: '',
  max_sites: '',
  max_clients: '',
  max_ai_tokens_monthly: '',
  max_profiles: '',
  trial_days: '',
})

function submit(): void {
  form.post('/platform/plans', {
    onSuccess: () => {
      form.reset()
      createOpen.value = false
    },
  })
}

function toggle(plan: PlanRow): void {
  router.post(`/platform/plans/${plan.id}/toggle`, {}, { preserveScroll: true })
}

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)
</script>

<template>
  <Head title="پلن‌ها" />
  <PlatformLayout>
    <VPageHeader
      title="پلن‌ها"
      description="تعریف و مدیریت پلن‌های اشتراک — قیمت، ظرفیت و ویژگی‌ها."
    >
      <template #actions>
        <VButton size="sm" @click="createOpen = true">+ پلن جدید</VButton>
      </template>
    </VPageHeader>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
      <VCard v-for="plan in plans" :key="plan.id" class="flex flex-col">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h3 class="text-ink-strong font-display text-lg font-bold">{{ plan.name }}</h3>
            <p class="font-latin text-ink-muted text-xs" dir="ltr">{{ plan.key }}</p>
          </div>
          <VBadge :tone="plan.is_active ? 'success' : 'neutral'">
            {{ plan.is_active ? 'فعال' : 'بایگانی' }}
          </VBadge>
        </div>
        <p class="text-ink-muted mt-2 text-sm leading-6">{{ plan.description }}</p>

        <div class="mt-4 flex items-baseline gap-1">
          <span class="text-ink-strong font-display text-2xl font-bold">
            {{ faNum(plan.price_monthly) }}
          </span>
          <span class="text-ink-muted text-sm">تومان/ماه</span>
          <span class="text-ink-muted mx-2">·</span>
          <span class="text-ink-muted text-sm">سالانه: {{ faNum(plan.price_yearly) }}</span>
        </div>

        <ul class="text-ink-muted mt-4 space-y-1.5 text-sm">
          <li>حداکثر سایت: {{ faNum(plan.limits.max_sites ?? 0) }}</li>
          <li>حداکثر مشتری: {{ faNum(plan.limits.max_clients ?? 0) }}</li>
          <li>توکن AI ماهانه: {{ faNum(plan.limits.max_ai_tokens_monthly ?? 0) }}</li>
          <li>انتشار خودکار: {{ plan.features.auto_publish ? '✅' : '—' }}</li>
          <li>دورهٔ آزمایشی: {{ (plan.features.trial_days as number) ?? 0 }} روز</li>
        </ul>

        <div class="mt-5 flex items-center justify-between border-t pt-4">
          <span class="text-ink-muted text-xs">{{ plan.subscriptions_count }} اشتراک</span>
          <VButton size="sm" variant="ghost" @click="toggle(plan)">
            {{ plan.is_active ? 'بایگانی' : 'فعال‌سازی' }}
          </VButton>
        </div>
      </VCard>
    </div>

    <!-- مودال ساخت پلن -->
    <div
      v-if="createOpen"
      class="bg-ink-900/40 fixed inset-0 z-50 flex items-center justify-center p-4"
      @click.self="createOpen = false"
    >
      <div class="bg-surface rounded-2xl max-h-[90vh] w-full max-w-lg overflow-y-auto p-6 shadow-2xl">
        <h3 class="text-ink-strong font-display text-lg font-bold">پلن جدید</h3>
        <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
          <VInput v-model="form.key" label="کلید (انگلیسی)" dir="ltr" :error="form.errors.key" />
          <VInput v-model="form.name" label="نام" :error="form.errors.name" />
          <div class="sm:col-span-2">
            <VInput v-model="form.description" label="توضیح" :error="form.errors.description" />
          </div>
          <VInput v-model="form.price_monthly" label="قیمت ماهانه (تومان)" type="number" :error="form.errors.price_monthly" />
          <VInput v-model="form.price_yearly" label="قیمت سالانه (تومان)" type="number" :error="form.errors.price_yearly" />
          <VInput v-model="form.max_sites" label="حداکثر سایت" type="number" />
          <VInput v-model="form.max_clients" label="حداکثر مشتری" type="number" />
          <VInput v-model="form.max_ai_tokens_monthly" label="توکن AI ماهانه" type="number" />
          <VInput v-model="form.max_profiles" label="حداکثر پروفایل" type="number" />
          <VInput v-model="form.trial_days" label="دورهٔ آزمایشی (روز)" type="number" />
          <div class="sm:col-span-2 flex justify-end gap-3">
            <VButton type="button" variant="ghost" size="sm" @click="createOpen = false">انصراف</VButton>
            <VButton type="submit" size="sm" :loading="form.processing">ساخت پلن</VButton>
          </div>
        </form>
      </div>
    </div>
  </PlatformLayout>
</template>
