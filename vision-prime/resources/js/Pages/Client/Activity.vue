<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface ActivityItem {
  id: number
  action: string
  label: string
  actor_name: string | null
  occurred_at: string
}

defineProps<{
  activities: ActivityItem[]
}>()
</script>

<template>
  <Head title="فعالیت‌ها" />
  <ClientPortalLayout>
    <VPageHeader
      title="فعالیت‌ها"
      description="خلاصه‌ای از کارهای مهم و پیشرفت‌های اخیر در حساب شما."
    />

    <div v-if="activities.length" class="mt-8">
      <ol class="relative space-y-6 border-s border-line ms-2 ps-6">
        <li v-for="activity in activities" :key="activity.id" class="relative">
          <span
            class="bg-brand-700 absolute -start-[31px] top-1.5 size-3 rounded-full ring-4 ring-white"
          />
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-ink-strong font-medium">{{ activity.label }}</p>
            <p class="text-ink-muted text-xs">{{ formatJalaliDate(activity.occurred_at) }}</p>
          </div>
          <p class="text-ink-muted mt-1 text-sm">
            {{ activity.actor_name ?? 'سیستم' }}
          </p>
        </li>
      </ol>
    </div>

    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز فعالیتی ثبت نشده است"
      description="کارهای مهم تیم روی حساب شما، به‌زبان ساده و به‌ترتیب زمان در اینجا نمایش داده می‌شوند."
    />
  </ClientPortalLayout>
</template>
