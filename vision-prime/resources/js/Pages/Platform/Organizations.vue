<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface OrganizationRow {
  id: number
  name: string
  slug: string
  status: string
  created_at: string
  clients_count: number
  members_count: number
  sites_count: number
  plan_name: string
  subscription_status: string | null
}

defineProps<{ organizations: OrganizationRow[] }>()

const statusTone = (status: string): 'success' | 'warning' | 'danger' | 'neutral' | 'info' =>
  status === 'active' ? 'success' : status === 'suspended' ? 'danger' : 'warning'

const subTone = (status: string | null): 'success' | 'warning' | 'danger' | 'neutral' | 'info' =>
  status === 'active' ? 'success' : status === 'trialing' ? 'info' : status === 'past_due' ? 'danger' : 'neutral'
</script>

<template>
  <Head title="سازمان‌ها" />
  <PlatformLayout>
    <VPageHeader
      title="سازمان‌ها"
      description="همهٔ آژانس‌ها و مشتریان پلتفرم — رصد وضعیت، پلن و فعالیت هر کدام."
    />

    <VCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-line text-ink-muted border-b text-start text-xs">
              <th class="px-4 py-3 text-start font-semibold">سازمان</th>
              <th class="px-4 py-3 text-start font-semibold">وضعیت</th>
              <th class="px-4 py-3 text-start font-semibold">پلن</th>
              <th class="px-4 py-3 text-start font-semibold">مشتریان</th>
              <th class="px-4 py-3 text-start font-semibold">سایت‌ها</th>
              <th class="px-4 py-3 text-start font-semibold">اعضا</th>
              <th class="px-4 py-3 text-start font-semibold">تاریخ ایجاد</th>
            </tr>
          </thead>
          <tbody class="divide-line divide-y">
            <tr v-for="org in organizations" :key="org.id" class="hover:bg-surface-muted/50 transition-colors">
              <td class="px-4 py-3">
                <Link :href="`/platform/organizations/${org.id}`" class="text-brand-600 font-semibold hover:underline">
                  {{ org.name }}
                </Link>
                <p class="text-ink-muted text-xs" dir="ltr">{{ org.slug }}</p>
              </td>
              <td class="px-4 py-3"><VBadge :tone="statusTone(org.status)">{{ org.status }}</VBadge></td>
              <td class="px-4 py-3">
                <p class="text-ink-strong font-medium">{{ org.plan_name }}</p>
                <VBadge v-if="org.subscription_status" :tone="subTone(org.subscription_status)" size="sm">
                  {{ org.subscription_status }}
                </VBadge>
              </td>
              <td class="px-4 py-3">{{ org.clients_count }}</td>
              <td class="px-4 py-3">{{ org.sites_count }}</td>
              <td class="px-4 py-3">{{ org.members_count }}</td>
              <td class="text-ink-muted px-4 py-3 text-xs" dir="ltr">{{ org.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </VCard>
  </PlatformLayout>
</template>
