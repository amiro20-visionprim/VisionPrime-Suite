<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VStatCard from '@/shared/ui/VStatCard.vue'

interface OrgDetail {
  id: number
  name: string
  slug: string
  status: string
  created_at: string
}

interface SubscriptionInfo {
  id: number
  plan_name: string
  status: string
  current_period_end: string
  auto_renew: boolean
}

interface AiProvider {
  provider: string
  model: string
  has_key: boolean
  key_last4: string | null
}

interface SiteRow {
  id: number
  name: string
  url: string
  status: string
}

interface MemberRow {
  id: number
  name: string
  email: string
  status: string
}

const props = defineProps<{
  organization: OrgDetail
  subscription: SubscriptionInfo | null
  aiProviders: AiProvider[]
  tokensMonth: number
  sites: SiteRow[]
  members: MemberRow[]
}>()

const suspendReason = ref('')
const suspendOpen = ref(false)

function suspend(): void {
  router.post(`/platform/organizations/${props.organization.id}/suspend`, { reason: suspendReason.value }, { preserveScroll: true })
}

function activate(): void {
  if (!window.confirm(`سازمان «${props.organization.name}» دوباره فعال شود؟`)) return
  router.post(`/platform/organizations/${props.organization.id}/activate`, {}, { preserveScroll: true })
}

function impersonate(memberId: number, memberName: string): void {
  if (!window.confirm(`وارد حساب «${memberName}» شوید؟ (اکشن‌های حساس غیرفعال است و همه‌چیز ثبت می‌شود)`)) return
  router.post(`/platform/organizations/${props.organization.id}/impersonate/${memberId}`, {}, { preserveScroll: false })
}
</script>

<template>
  <Head :title="organization.name" />
  <PlatformLayout>
    <VPageHeader
      :title="organization.name"
      :description="`رصد کامل سازمان — اعضا، سایت‌ها، هوش مصنوعی و اشتراک.`"
    >
      <template #actions>
        <div class="flex items-center gap-3">
          <template v-if="organization.status === 'active'">
            <VButton size="sm" variant="danger" @click="suspendOpen = true">تعلیق سازمان</VButton>
          </template>
          <VButton v-else size="sm" variant="gradient" @click="activate">فعال‌سازی مجدد</VButton>
          <Link href="/platform/organizations" class="text-ink-muted text-sm hover:underline"
            >→ بازگشت به فهرست</Link
          >
        </div>
      </template>
    </VPageHeader>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <VStatCard label="سایت‌های سازمان" :value="sites.length" icon="activity" icon-tone="brand" />
      <VStatCard label="اعضای سازمان" :value="members.length" icon="users" icon-tone="violet" />
      <VStatCard label="کلیدهای AI" :value="aiProviders.length" icon="sparkles" icon-tone="brand" />
      <VStatCard label="توکن AI این ماه" :value="tokensMonth" icon="zap" icon-tone="warning" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <VCard title="اشتراک و پلن" description="وضعیت مالی سازمان">
        <template v-if="subscription">
          <div class="flex items-center gap-3">
            <p class="text-ink-strong text-lg font-bold">{{ subscription.plan_name }}</p>
            <VBadge :tone="subscription.status === 'active' ? 'success' : subscription.status === 'trialing' ? 'info' : 'warning'">
              {{ subscription.status }}
            </VBadge>
          </div>
          <p class="text-ink-muted mt-3 text-sm">
            پایان دوره: <span dir="ltr">{{ subscription.current_period_end }}</span>
          </p>
          <p class="text-ink-muted mt-1 text-sm">
            تمدید خودکار: {{ subscription.auto_renew ? 'فعال' : 'غیرفعال' }}
          </p>
        </template>
        <p v-else class="text-ink-muted py-3 text-sm">این سازمان هنوز اشتراک فعالی ندارد.</p>
      </VCard>

      <VCard title="کلیدهای هوش مصنوعی" description="کلید هرگز کامل نمایش داده نمی‌شود">
        <ul v-if="aiProviders.length" class="space-y-2">
          <li
            v-for="provider in aiProviders"
            :key="provider.provider"
            class="border-line flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
          >
            <div>
              <p class="text-ink-strong text-sm font-semibold" dir="ltr">{{ provider.provider }}</p>
              <p class="text-ink-muted text-xs" dir="ltr">{{ provider.model || 'مدل پیش‌فرض' }}</p>
            </div>
            <VBadge :tone="provider.has_key ? 'success' : 'neutral'">
              {{ provider.has_key ? `کلید ...${provider.key_last4}` : 'بدون کلید' }}
            </VBadge>
          </li>
        </ul>
        <p v-else class="text-ink-muted py-3 text-sm">کلیدی پیکربندی نشده است.</p>
      </VCard>

      <VCard title="سایت‌ها" description="وضعیت اتصال و همگام‌سازی">
        <ul v-if="sites.length" class="space-y-2">
          <li
            v-for="site in sites"
            :key="site.id"
            class="border-line flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
          >
            <div class="min-w-0">
              <p class="text-ink-strong truncate text-sm font-semibold">{{ site.name }}</p>
              <p class="text-ink-muted truncate text-xs" dir="ltr">{{ site.url }}</p>
            </div>
            <VBadge :tone="site.status === 'active' ? 'success' : 'warning'">{{ site.status }}</VBadge>
          </li>
        </ul>
        <p v-else class="text-ink-muted py-3 text-sm">سایتی ثبت نشده است.</p>
      </VCard>

      <VCard title="اعضا" description="کاربران سازمان — ورود به‌جای هر عضو (با ثبت کامل)">
        <ul v-if="members.length" class="space-y-2">
          <li
            v-for="member in members"
            :key="member.email"
            class="border-line flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
          >
            <div class="min-w-0">
              <p class="text-ink-strong truncate text-sm font-semibold">{{ member.name }}</p>
              <p class="text-ink-muted truncate text-xs" dir="ltr">{{ member.email }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <VBadge :tone="member.status === 'active' ? 'success' : 'neutral'">{{ member.status }}</VBadge>
              <VButton v-if="member.status === 'active'" size="sm" variant="ghost" @click="impersonate(member.id, member.name)">
                ورود به‌جای او
              </VButton>
            </div>
          </li>
        </ul>
        <p v-else class="text-ink-muted py-3 text-sm">عضوی ثبت نشده است.</p>
      </VCard>
    </div>

    <!-- مودال تعلیق با دلیل -->
    <div
      v-if="suspendOpen"
      class="bg-ink-900/40 fixed inset-0 z-50 flex items-center justify-center p-4"
      @click.self="suspendOpen = false"
    >
      <div class="bg-surface rounded-2xl w-full max-w-md p-6 shadow-2xl">
        <h3 class="text-ink-strong font-display text-lg font-bold">تعلیق سازمان</h3>
        <p class="text-ink-muted mt-1 text-sm">
          دلیل تعلیق ثبت و در گزارش ممیزی ذخیره می‌شود.
        </p>
        <textarea
          v-model="suspendReason"
          rows="3"
          class="border-line bg-surface-muted/60 text-ink-strong focus:border-brand-500 mt-4 w-full rounded-xl border px-3 py-2 text-sm outline-none"
          placeholder="دلیل تعلیق (الزامی)…"
        />
        <div class="mt-5 flex justify-end gap-3">
          <VButton variant="ghost" size="sm" @click="suspendOpen = false">انصراف</VButton>
          <VButton variant="danger" size="sm" :disabled="!suspendReason.trim()" @click="suspend">
            تعلیق سازمان
          </VButton>
        </div>
      </div>
    </div>
  </PlatformLayout>
</template>
