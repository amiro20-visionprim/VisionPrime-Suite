<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatLocalizedDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface GscAccount {
  email: string
  status: string
  expiresAt: string | null
}

interface SiteConnection {
  id: number
  name: string
  status: string
  lastSeenAt: string | null
}

interface AiProvider {
  provider: string
  model: string
  updatedAt: string | null
}

defineProps<{
  gsc: {
    connected: number
    accounts: GscAccount[]
    propertiesCount: number
  }
  wordpress: {
    totalSites: number
    pairedSites: number
    sites: SiteConnection[]
  }
  ai: {
    providers: AiProvider[]
    isConfigured: boolean
  }
}>()

const providerOptions = [
  { label: 'OpenAI', value: 'openai' },
  { label: 'OpenRouter', value: 'openrouter' },
  { label: 'Anthropic (Claude)', value: 'anthropic' },
]

const providerLabels: Record<string, string> = {
  openai: 'OpenAI',
  openrouter: 'OpenRouter',
  anthropic: 'Anthropic',
}

const aiForm = useForm({
  provider: 'openai',
  api_key: '',
  model: '',
})

function saveAiProvider(): void {
  aiForm.post('/app/settings/ai-provider', {
    preserveScroll: true,
    onSuccess: () => {
      aiForm.reset('api_key')
    },
  })
}

function removeAiProvider(provider: string): void {
  if (!window.confirm(`پیکربندی ${providerLabels[provider] ?? provider} حذف شود؟`)) return
  aiForm.delete(`/app/settings/ai-provider/${provider}`, { preserveScroll: true })
}
</script>

<template>
  <Head title="یکپارچه‌سازی‌ها" />
  <AppLayout>
    <VPageHeader
      title="یکپارچه‌سازی‌ها"
      description="وضعیت اتصال سرویس‌های خارجی: سرچ کنسول، وردپرس و سرویس‌های هوش مصنوعی."
    />

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
      <VCard
        title="سرچ کنسول گوگل (GSC)"
        description="دادهٔ جستجو، کوئری‌ها و صفحات برای تحلیل رشد از این اتصال می‌آید."
      >
        <div class="flex items-center gap-3">
          <VBadge :tone="gsc.connected ? 'success' : 'neutral'">
            {{ gsc.connected ? `${gsc.connected} حساب متصل` : 'اتصال برقرار نشده' }}
          </VBadge>
          <VBadge v-if="gsc.propertiesCount" tone="info">
            {{ gsc.propertiesCount }} ملک انتخاب‌شده
          </VBadge>
        </div>

        <ul v-if="gsc.accounts.length" class="mt-5 space-y-3">
          <li
            v-for="account in gsc.accounts"
            :key="account.email"
            class="border-line flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
          >
            <div class="min-w-0">
              <p class="text-ink-strong truncate text-sm font-semibold" dir="ltr">
                {{ account.email }}
              </p>
              <p v-if="account.expiresAt" class="text-ink-muted text-xs">
                انقضای توکن: {{ formatLocalizedDate(account.expiresAt, 'fa') }}
              </p>
            </div>
            <VBadge :tone="account.status === 'connected' ? 'success' : 'warning'">{{
              account.status === 'connected' ? 'متصل' : account.status
            }}</VBadge>
          </li>
        </ul>
        <p v-else class="text-ink-muted mt-4 text-sm leading-6">
          برای فعال‌سازی هوش رشد، حساب گوگل را به سرچ کنسول متصل کنید.
        </p>

        <div class="border-line mt-5 flex gap-3 border-t pt-5">
          <VButton :href="gsc.connected ? '/app/gsc' : '/app/gsc/connect'">
            {{ gsc.connected ? 'مدیریت سرچ کنسول' : 'اتصال حساب گوگل' }}
          </VButton>
        </div>
      </VCard>

      <VCard
        title="اتصال وردپرس"
        description="ارسال تغییرات اجرایی و همگام‌سازی محتوا از طریق پلاگین سوئیت انجام می‌شود."
      >
        <div class="flex items-center gap-3">
          <VBadge :tone="wordpress.pairedSites ? 'success' : 'neutral'">
            {{ wordpress.pairedSites }} از {{ wordpress.totalSites }} سایت متصل
          </VBadge>
        </div>

        <ul v-if="wordpress.sites.length" class="mt-5 space-y-3">
          <li
            v-for="site in wordpress.sites"
            :key="site.id"
            class="border-line flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
          >
            <div class="min-w-0">
              <p class="text-ink-strong truncate text-sm font-semibold">{{ site.name }}</p>
              <p v-if="site.lastSeenAt" class="text-ink-muted text-xs">
                آخرین حضور: {{ formatLocalizedDate(site.lastSeenAt, 'fa') }}
              </p>
            </div>
            <VBadge :tone="site.status === 'paired' ? 'success' : 'neutral'">{{
              site.status === 'paired' ? 'متصل' : 'بدون اتصال'
            }}</VBadge>
          </li>
        </ul>
        <p v-else class="text-ink-muted mt-4 text-sm leading-6">
          هنوز سایتی برای اتصال ثبت نشده است.
        </p>

        <div class="border-line mt-5 flex gap-3 border-t pt-5">
          <VButton :href="wordpress.totalSites ? '/app/sites' : '/app/sites/create'" variant="secondary">
            {{ wordpress.totalSites ? 'مدیریت سایت‌ها' : 'افزودن سایت' }}
          </VButton>
        </div>
      </VCard>

      <VCard
        class="lg:col-span-2"
        title="هوش مصنوعی"
        description="تولید پیشنهادها، نسخه‌های پیشنویس و تحلیل‌های خودکار از سرویس‌های هوش مصنوعی استفاده می‌کند."
      >
        <div class="flex flex-wrap items-center gap-3">
          <VBadge :tone="ai.isConfigured ? 'success' : 'warning'">
            {{ ai.isConfigured ? 'پیکربندی شده' : 'پیکربندی نشده' }}
          </VBadge>
        </div>

        <div v-if="ai.providers.length" class="mt-4 space-y-2">
          <div
            v-for="provider in ai.providers"
            :key="provider.provider"
            class="border-line flex items-center justify-between gap-3 rounded-ui border px-4 py-3"
          >
            <div>
              <p class="text-ink-strong text-sm font-semibold" dir="ltr">{{ provider.provider }}</p>
              <p class="text-ink-muted text-xs" dir="ltr">{{ provider.model || 'مدل پیش‌فرض' }}</p>
            </div>
            <VButton size="sm" variant="danger" @click="removeAiProvider(provider.provider)">حذف</VButton>
          </div>
        </div>

        <p class="text-ink-muted mt-4 max-w-2xl text-sm leading-6">
          {{
            ai.isConfigured
              ? 'سرویس هوش مصنوعی برای این سازمان فعال است و در تولید پیشنویس‌ها استفاده می‌شود.'
              : 'کلید سرویس را وارد کنید؛ تا آن زمان پیشنویس‌ها با موتور داخلی (بدون نیاز به کلید) تولید می‌شوند.'
          }}
        </p>

        <form class="border-line mt-5 grid gap-4 border-t pt-5 sm:grid-cols-3" @submit.prevent="saveAiProvider">
          <VSelect
            v-model="aiForm.provider"
            label="سرویس"
            :options="providerOptions"
            :error="aiForm.errors.provider"
          />
          <VInput
            v-model="aiForm.api_key"
            label="کلید API"
            type="password"
            dir="ltr"
            placeholder="sk-..."
            hint="رمزنگاری‌شده ذخیره می‌شود."
            :error="aiForm.errors.api_key"
          />
          <VInput
            v-model="aiForm.model"
            label="مدل (اختیاری)"
            type="text"
            dir="ltr"
            placeholder="gpt-4o-mini"
            :error="aiForm.errors.model"
          />
          <div class="sm:col-span-3">
            <VButton type="submit" :loading="aiForm.processing">ذخیره پیکربندی هوش مصنوعی</VButton>
          </div>
        </form>
      </VCard>
    </div>
  </AppLayout>
</template>
