<script setup lang="ts">
import { ref } from 'vue'
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

interface ProviderInfo {
  name: string
  category: string
  endpoint: string
  default_model: string
  free_models: string[]
  models: string[]
  notes: string
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
    masked?: boolean
  }
  allProviders: Record<string, ProviderInfo>
  freeModelsCount: number
  isSuperAdmin: boolean
}>()

const providerOptions = [
  { label: 'DeepSeek (ارزان و سریع)', value: 'deepseek' },
  { label: 'OpenRouter (14+ مدل رایگان)', value: 'openrouter' },
  { label: 'OpenAI (GPT-4)', value: 'openai' },
  { label: 'Anthropic (Claude)', value: 'anthropic' },
  { label: 'Google Gemini', value: 'google' },
  { label: 'Groq (سریع‌ترین)', value: 'groq' },
  { label: 'Together AI', value: 'together' },
  { label: 'Fireworks AI', value: 'fireworks' },
  { label: 'Mistral AI', value: 'mistral' },
  { label: 'Cohere', value: 'cohere' },
  { label: 'DeepInfra', value: 'deepinfra' },
  { label: 'Novita AI', value: 'novita' },
  { label: 'سمانی (Samani)', value: 'samani' },
  { label: 'پارستک (ParsTech)', value: 'parstech' },
  { label: 'آیز (Ayez)', value: 'ayez' },
  { label: 'فال (Fal.ai)', value: 'fal' },
]

const providerLabels: Record<string, string> = {
  openai: 'OpenAI',
  openrouter: 'OpenRouter',
  deepseek: 'DeepSeek',
  anthropic: 'Anthropic',
}

const freeModels = [
  { id: 'nvidia/nemotron-3-ultra-550b-a55b:free', name: 'Nemotron 3 Ultra (550B)', quality: 'بالا', ctx: '1M' },
  { id: 'nvidia/nemotron-3-super-120b-a12b:free', name: 'Nemotron 3 Super (120B)', quality: 'بالا', ctx: '262K' },
  { id: 'z-ai/glm-5.2:free', name: 'GLM 5.2', quality: 'متوسط', ctx: '256K' },
  { id: 'google/gemma-4-31b-it:free', name: 'Gemma 4 31B', quality: 'متوسط', ctx: '262K' },
  { id: 'google/gemma-4-26b-a4b-it:free', name: 'Gemma 4 26B', quality: 'متوسط', ctx: '262K' },
  { id: 'dots-studio/dots-3-note-preview:free', name: 'Dots3 Note Preview', quality: 'متوسط', ctx: '512K' },
  { id: 'nvidia/nemotron-3.5-lightning:free', name: 'Nemotron 3.5 Lightning', quality: 'سریع', ctx: '1M' },
  { id: 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free', name: 'Nemotron 3 Nano Omni', quality: 'متوسط', ctx: '256K' },
  { id: 'poolside/laguna-s-2.1:free', name: 'Laguna S 2.1', quality: 'متوسط', ctx: '262K' },
  { id: 'cohere/north-mini-code:free', name: 'North Mini Code', quality: 'متوسط', ctx: '256K' },
  { id: 'meta-llama/llama-3.3-8b-instruct:free', name: 'Llama 3.3 8B', quality: 'بالا', ctx: '128K' },
  { id: 'qwen/qwen3-235b-a22b:free', name: 'Qwen3 235B', quality: 'بالا', ctx: '128K' },
  { id: 'deepseek/deepseek-r1-0528:free', name: 'DeepSeek R1', quality: 'بالا', ctx: '128K' },
  { id: 'mistralai/mistral-small-3.2-24b-instruct:free', name: 'Mistral Small 3.2', quality: 'بالا', ctx: '128K' },
]

const aiForm = useForm({
  provider: 'deepseek',
  api_key: '',
  model: '',
})

const testing = ref(false)
const testResult = ref<{ success: boolean; message: string } | null>(null)
const detectingModels = ref(false)
const detectedModels = ref<Array<{id: string; name: string; status: string; context_window: number | null; max_output: number | null}>>([])
const providerUsage = ref<{total_tokens: number | null; limit: number | null; reset_at: string | null} | null>(null)
const showModelDetails = ref(false)

async function detectModels(): Promise<void> {
  if (!aiForm.provider || !aiForm.api_key) return
  detectingModels.value = true
  detectedModels.value = []
  providerUsage.value = null
  try {
    const res = await fetch('/api/content/detect-models', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ provider: aiForm.provider, api_key: aiForm.api_key }),
    })
    const data = await res.json()
    if (data.success) {
      detectedModels.value = data.models
      showModelDetails.value = true
      // Also fetch usage
      const usageRes = await fetch('/api/content/provider-usage', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ provider: aiForm.provider, api_key: aiForm.api_key }),
      })
      const usageData = await usageRes.json()
      if (usageData.success) providerUsage.value = usageData.usage
    } else {
      testResult.value = { success: false, message: `خطا در تشخیص مدل‌ها: ${data.error}` }
    }
  } catch (e: unknown) {
    testResult.value = { success: false, message: 'خطا در تشخیص مدل‌ها' }
  }
  detectingModels.value = false
}

function selectDetectedModel(modelId: string): void {
  aiForm.model = modelId
  showModelDetails.value = false
}

function saveAiProvider(): void {
  aiForm.post('/app/settings/ai-provider', {
    preserveScroll: true,
    onSuccess: () => { aiForm.reset('api_key') },
  })
}

function removeAiProvider(provider: string): void {
  if (!window.confirm(`پیکربندی ${providerLabels[provider] ?? provider} حذف شود؟`)) return
  aiForm.delete(`/app/settings/ai-provider/${provider}`, { preserveScroll: true })
}

async function testConnection(): Promise<void> {
  if (!aiForm.provider || !aiForm.api_key) return
  testing.value = true
  testResult.value = null
  try {
    const res = await fetch('/api/content/test-provider', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ provider: aiForm.provider, api_key: aiForm.api_key, model: aiForm.model || undefined }),
    })
    const data = await res.json()
    testResult.value = { success: data.success, message: data.success ? `اتصال موفق — مدل: ${data.model}` : `خطا: ${data.error}` }
  } catch (e: unknown) {
    testResult.value = { success: false, message: 'خطا در تست اتصال' }
  }
  testing.value = false
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
      <!-- GSC -->
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

      <!-- WordPress -->
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

      <!-- AI Gateway — فقط سوپر ادمین -->
      <template v-if="isSuperAdmin">
      <VCard
        class="lg:col-span-2"
        title=" هوش مصنوعی — Gateway"
        description="تولید محتوا، پیشنهادات هوشمند و تحلیل‌ها. با سیستم Failover خودکار بین ۱۰+ مدل."
      >
        <div class="flex flex-wrap items-center gap-3">
          <VBadge :tone="ai.isConfigured ? 'success' : 'warning'">
            {{ ai.isConfigured ? 'پیکربندی شده' : 'فقط RuleBased فعال' }}
          </VBadge>
        </div>

        <!-- Current providers -->
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
          {{ ai.isConfigured
            ? 'سرویس هوش مصنوعی فعال است. اگر کلید شما به لیمیت بخورد، سیستم خودکار به مدل‌های رایگان سوئیچ می‌کند.'
            : 'بدون کلید API، فقط از موتور داخلی (RuleBased) استفاده می‌شود. برای تولید واقعی، کلید وارد کنید.'
          }}
        </p>

        <!-- Config form -->
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
            :placeholder="aiForm.provider === 'openrouter' ? 'sk-or-...' : aiForm.provider === 'deepseek' ? 'sk-...' : ''"
            hint="رمزنگاری‌شده ذخیره می‌شود."
            :error="aiForm.errors.api_key"
          />
          <VInput
            v-model="aiForm.model"
            label="مدل (اختیاری)"
            type="text"
            dir="ltr"
            :placeholder="aiForm.provider === 'deepseek' ? 'deepseek-chat' : aiForm.provider === 'openrouter' ? 'auto' : 'gpt-4o-mini'"
            :error="aiForm.errors.model"
          />
          <div class="flex items-center gap-3 sm:col-span-3">
            <VButton type="submit" :loading="aiForm.processing">ذخیره پیکربندی</VButton>
            <VButton type="button" variant="secondary" :loading="testing" @click="testConnection">تست اتصال</VButton>
            <VButton type="button" variant="secondary" :loading="detectingModels" @click="detectModels">
              🔍 تشخیص خودکار مدل‌ها
            </VButton>
          </div>
        </form>

        <!-- Test result -->
        <div v-if="testResult" class="mt-3 rounded-xl p-3 text-sm" :class="testResult.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
          {{ testResult.message }}
        </div>

        <!-- Detected models -->
        <div v-if="showModelDetails && detectedModels.length" class="border-line mt-5 border-t pt-5">
          <div class="flex items-center justify-between">
            <h3 class="text-ink-strong text-sm font-bold">مدل‌های تشخیص‌داده‌شده ({{ detectedModels.length }})</h3>
            <button type="button" class="text-ink-muted text-xs" @click="showModelDetails = false">بستن</button>
          </div>

          <!-- Usage info -->
          <div v-if="providerUsage" class="bg-surface-muted mt-3 rounded-lg p-3">
            <p class="text-ink-strong text-xs font-bold">مصرف و محدودیت</p>
            <div class="mt-2 flex flex-wrap gap-4 text-xs">
              <span v-if="providerUsage.total_tokens !== null">
                <span class="text-ink-muted">مصرف توکن:</span> <span class="font-bold">{{ new Intl.NumberFormat('fa-IR').format(providerUsage.total_tokens) }}</span>
              </span>
              <span v-if="providerUsage.limit !== null">
                <span class="text-ink-muted">محدودیت:</span> <span class="font-bold">{{ new Intl.NumberFormat('fa-IR').format(providerUsage.limit) }}</span>
              </span>
              <span v-if="providerUsage.reset_at">
                <span class="text-ink-muted">بازنشانی:</span> <span class="font-bold">{{ providerUsage.reset_at }}</span>
              </span>
            </div>
          </div>

          <!-- Models list -->
          <div class="mt-3 space-y-1">
            <div
              v-for="model in detectedModels"
              :key="model.id"
              class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 hover:bg-surface-muted cursor-pointer"
              @click="selectDetectedModel(model.id)"
            >
              <div class="min-w-0 flex-1">
                <p class="text-ink-strong text-sm font-semibold" dir="ltr">{{ model.id }}</p>
                <div class="flex items-center gap-2 text-xs">
                  <span v-if="model.context_window" class="text-ink-muted">{{ Math.round(model.context_window / 1000) }}K context</span>
                  <span v-if="model.max_output" class="text-ink-muted">{{ model.max_output }} output</span>
                </div>
              </div>
              <VBadge :tone="model.status === 'active' ? 'success' : 'danger'">
                {{ model.status === 'active' ? 'فعال' : 'غیرفعال' }}
              </VBadge>
            </div>
          </div>
          <p class="text-ink-muted mt-2 text-xs">روی هر مدل کلیک کنید تا در فیلد مدل انتخاب شود.</p>
        </div>

        <!-- Free models section -->
        <div class="border-line mt-6 border-t pt-5">
          <h3 class="text-ink-strong mb-3 text-sm font-semibold">مدل‌های رایگان OpenRouter (بدون نیاز به کلید)</h3>
          <p class="text-ink-muted mb-3 text-xs">
            سیستم به صورت خودکار از این مدل‌ها استفاده می‌کند — حتی اگر کلید API نداشته باشید. اگر کلید شما به لیمیت بخورد، بلافاصله به مدل بعدی سوئیچ می‌شود.
          </p>
          <div class="grid gap-2 sm:grid-cols-2">
            <div v-for="model in freeModels" :key="model.id" class="border-line flex items-center justify-between rounded-lg border px-3 py-2">
              <div>
                <p class="text-ink-strong text-xs font-medium">{{ model.name }}</p>
                <p class="text-ink-muted text-xs" dir="ltr">{{ model.id }}</p>
              </div>
              <div class="flex items-center gap-2">
                <VBadge :tone="model.quality === 'بالا' ? 'success' : model.quality === 'سریع' ? 'info' : 'neutral'" size="sm">{{ model.quality }}</VBadge>
                <VBadge tone="neutral" size="sm">{{ model.ctx }}</VBadge>
              </div>
            </div>
          </div>
        </div>

        <!-- How it works -->
        <div class="border-line mt-6 border-t pt-5">
          <h3 class="text-ink-strong mb-3 text-sm font-semibold">چطور کار می‌کند؟</h3>
          <ol class="text-ink-muted space-y-2 text-xs leading-6">
            <li><strong>۱.</strong> اول کلید شما بررسی می‌شود (DeepSeek/OpenAI/Anthropic/Groq/...)</li>
            <li><strong>۲.</strong> اگر لیمیت خورد → خودکار به مدل‌های رایگان (۱۴+ مدل) سوئیچ</li>
            <li><strong>۳.</strong> اگر همه مدل‌ها لیمیت باشن → موتور داخلی (RuleBased)</li>
            <li><strong>۴.</strong> اگر در حین تولید محتوا لیمیت بخورد → تولید مجدد با کلید جدید (حداکثر ۳ بار)</li>
          </ol>
          <p class="text-ink-muted mt-3 text-xs">💡 <strong>۱۶+ سرویس AI</strong> داخلی و خارجی پشتیبانی می‌شود — از DeepSeek و سمانی تا OpenAI و Anthropic.</p>
        </div>
      </VCard>
      </template>
    </div>
  </AppLayout>
</template>
