<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'

import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import VTable, { type TableColumn, type TableRow } from '@/shared/ui/VTable.vue'

interface Profile {
  id: number
  name: string
  slug: string
  kind: string
  automationLevel: number
  aiPolicy: string
  confidenceThreshold: number
  highRiskThreshold: number
  riskTierMax: string
  enabledContentTypes: string[]
  dailyCommandLimit: number
  dailyMutationLimit: number
  autoRollback: boolean
  version: number
}

interface RouteRow {
  contentType: string
  profileId: number
}

interface Execution {
  id: number
  type: string
  riskTier: string
  confidence: number | null
  decisionSource: string | null
  status: string
  publishedAt: string | null
  policyVersion: number
  createdAt: string
}

const props = defineProps<{
  site: { id: number; name: string }
  policy: {
    level: number
    aiPolicy: string
    confidenceThreshold: number
    highRiskThreshold: number
    riskTierMax: string
    enabledContentTypes: string[]
    dailyCommandLimit: number
    dailyMutationLimit: number
    autoRollback: boolean
    activeProfileId: number | null
    emergencyStoppedAt: string | null
    notificationPolicy: {
      enabled: boolean
      channels: string[]
      webhooks: { telegram: string | null; whatsapp: string | null }
    }
  }
  profiles: Profile[]
  routes: RouteRow[]
  executions: Execution[]
}>()

const page = usePage<{ flash?: { status?: string; error?: string } }>()
const saving = ref(false)
const stopping = ref(false)
const savingRoutes = ref(false)
const copyingProfile = ref<number | null>(null)

const routeMap = reactive<Record<string, string>>({
  meta: '',
  article: '',
  product: '',
})
for (const route of props.routes) {
  routeMap[route.contentType] = String(route.profileId)
}

const CONTENT_TYPES = [
  { key: 'meta', label: 'متا (title/description)' },
  { key: 'article', label: 'محتوا و مقالات' },
  { key: 'product', label: 'محصولات ووکامرس' },
]

function saveRoutes(): void {
  savingRoutes.value = true
  const routes = CONTENT_TYPES
    .filter((type) => routeMap[type.key] !== '')
    .map((type) => ({ content_type: type.key, profile_id: Number(routeMap[type.key]) }))
  router.post(`/app/sites/${props.site.id}/automation/routes`, { routes }, { onFinish: () => (savingRoutes.value = false) })
}

function copyProfile(profileId: number): void {
  if (!window.confirm('یک کپی قابل شخصی‌سازی از این پروفایل ساخته شود؟')) return
  copyingProfile.value = profileId
  router.post(`/app/sites/${props.site.id}/automation/profiles/copy`, { profile_id: profileId }, { onFinish: () => (copyingProfile.value = null) })
}

const LEVELS = [
  { value: '0', label: 'L0 — فقط مشاهده', hint: 'بدون توصیهٔ اجرایی یا تغییر' },
  { value: '1', label: 'L1 — پیشنهاد با تأیید کامل', hint: 'پیشنویس ساخته می‌شود؛ هر مورد تأیید انسانی' },
  { value: '2', label: 'L2 — اجرای کنترل‌شده', hint: 'تغییرات کم‌ریسک از‌پیش‌مجاز خودکار' },
  { value: '3', label: 'L3 — خودکارسازی نظارت‌شده', hint: 'اجرای قاعده‌مند + نمونه‌برداری بازبینی' },
  { value: '4', label: 'L4 — Autopilot محدود', hint: 'خودکار در budget و دامنهٔ مجاز؛ R3 همیشه تأیید' },
]

const AI_POLICIES = [
  { value: 'disabled', label: 'غیرفعال' },
  { value: 'draft_only', label: 'فقط پیشنویس' },
  { value: 'approved_templates', label: 'قالب‌های تأییدشده' },
  { value: 'bounded_auto', label: 'خودکار محدود با اجازه‌نامه' },
]

const RISK_TIERS = ['R0', 'R1', 'R2', 'R3'].map((t) => ({ value: t, label: t }))

const activeProfileId = ref<string>(props.policy.activeProfileId ? String(props.policy.activeProfileId) : '')

const form = reactive({
  automation_level: String(props.policy.level),
  ai_policy: props.policy.aiPolicy,
  confidence_threshold: props.policy.confidenceThreshold,
  high_risk_threshold: props.policy.highRiskThreshold,
  risk_tier_max: props.policy.riskTierMax,
  enabled_content_types: [...props.policy.enabledContentTypes],
  daily_command_limit: props.policy.dailyCommandLimit,
  daily_mutation_limit: props.policy.dailyMutationLimit,
  auto_rollback: props.policy.autoRollback,
  notification_enabled: props.policy.notificationPolicy.enabled,
  notification_channels: [...props.policy.notificationPolicy.channels],
  webhook_telegram: props.policy.notificationPolicy.webhooks.telegram ?? '',
  webhook_whatsapp: props.policy.notificationPolicy.webhooks.whatsapp ?? '',
})

const profileOptions = computed(() =>
  props.profiles.map((p) => ({
    value: String(p.id),
    label: `${p.name} (L${p.automationLevel} — ${p.aiPolicy})`,
  })),
)

function fillFromProfile(profileId: string): void {
  const profile = props.profiles.find((p) => String(p.id) === profileId)
  if (!profile) return
  form.automation_level = String(profile.automationLevel)
  form.ai_policy = profile.aiPolicy
  form.confidence_threshold = profile.confidenceThreshold
  form.high_risk_threshold = profile.highRiskThreshold
  form.risk_tier_max = profile.riskTierMax
  form.enabled_content_types = [...profile.enabledContentTypes]
  form.daily_command_limit = profile.dailyCommandLimit
  form.daily_mutation_limit = profile.dailyMutationLimit
  form.auto_rollback = profile.autoRollback
}

watch(activeProfileId, (value) => fillFromProfile(value))

function toggleContentType(type: string): void {
  const index = form.enabled_content_types.indexOf(type)
  if (index >= 0) form.enabled_content_types.splice(index, 1)
  else form.enabled_content_types.push(type)
}

const NOTIFICATION_CHANNELS = [
  { key: 'database', label: 'اعلان درون‌برنامه‌ای' },
  { key: 'mail', label: 'ایمیل' },
  { key: 'telegram', label: 'تلگرام (webhook)' },
  { key: 'whatsapp', label: 'واتساپ (webhook)' },
]

function toggleChannel(channel: string): void {
  const index = form.notification_channels.indexOf(channel)
  if (index >= 0) form.notification_channels.splice(index, 1)
  else form.notification_channels.push(channel)
}

function save(): void {
  saving.value = true
  router.put(`/app/sites/${props.site.id}/automation`, {
    active_profile_id: activeProfileId.value || null,
    overrides: {
      automation_level: Number(form.automation_level),
      ai_policy: form.ai_policy,
      confidence_threshold: Number(form.confidence_threshold),
      high_risk_threshold: Number(form.high_risk_threshold),
      risk_tier_max: form.risk_tier_max,
      enabled_content_types: form.enabled_content_types,
      daily_command_limit: Number(form.daily_command_limit),
      daily_mutation_limit: Number(form.daily_mutation_limit),
      auto_rollback: form.auto_rollback,
      notification_policy: {
        enabled: form.notification_enabled,
        channels: form.notification_channels,
        webhooks: {
          telegram: form.webhook_telegram || null,
          whatsapp: form.webhook_whatsapp || null,
        },
      },
    },
  }, { onFinish: () => (saving.value = false) })
}

function emergencyStop(): void {
  if (!window.confirm('توقف اضطراری خودکارسازی؟ دستورهای در صف لغو می‌شوند و هیچ تغییری تا رفع توقف اجرا نخواهد شد.')) return
  stopping.value = true
  router.post(`/app/sites/${props.site.id}/automation/emergency-stop`, {}, { onFinish: () => (stopping.value = false) })
}

function resume(): void {
  router.post(`/app/sites/${props.site.id}/automation/resume`)
}

const currentLevel = computed(() => LEVELS.find((l) => l.value === form.automation_level)?.label ?? `L${form.automation_level}`)

const executionsColumns: TableColumn[] = [
  { key: 'type', label: 'نوع تغییر' },
  { key: 'riskTier', label: 'ریسک', align: 'center' },
  { key: 'confidence', label: 'اطمینان', align: 'center' },
  { key: 'decisionSource', label: 'منبع تصمیم', align: 'center' },
  { key: 'status', label: 'وضعیت', align: 'center' },
  { key: 'publishedAt', label: 'انتشار', align: 'end' },
  { key: 'policyVersion', label: 'نسخهٔ سیاست', align: 'end' },
]

const statusTone = (status: string): 'success' | 'danger' | 'warning' | 'neutral' => {
  if (status === 'executed') return 'success'
  if (status === 'rolled_back' || status === 'failed' || status === 'cancelled') return 'danger'
  if (status === 'dispatched' || status === 'queued') return 'warning'
  return 'neutral'
}
</script>

<template>
  <Head title="خودکارسازی" />
  <AppLayout>
    <VPageHeader title="خودکارسازی سایت" :description="site.name" />
    <div v-if="page.props.flash?.status" class="mt-6">
      <VAlert tone="success">{{ page.props.flash.status }}</VAlert>
    </div>
    <div v-if="page.props.flash?.error" class="mt-6">
      <VAlert tone="danger">{{ page.props.flash.error }}</VAlert>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
      <VCard class="lg:col-span-2" title="سیاست خودکارسازی">
        <template #action>
          <VBadge :tone="policy.emergencyStoppedAt ? 'danger' : 'success'">
            {{ policy.emergencyStoppedAt ? 'متوقف شده' : 'فعال' }}
          </VBadge>
        </template>

        <div class="space-y-6">
          <div class="rounded-ui border border-line-strong bg-surface-muted p-4">
            <p class="text-sm font-semibold">سطح فعلی: {{ currentLevel }}</p>
            <p class="text-ink-muted mt-1 text-sm">
              {{ LEVELS.find((l) => l.value === form.automation_level)?.hint }}
            </p>
          </div>

          <VSelect v-model="activeProfileId" label="پروفایل پایه" :options="profileOptions" hint="پروفایل آماده را انتخاب کن؛ مقادیر پایین قابل شخصی‌سازی per-site است." />

          <div class="grid gap-5 sm:grid-cols-2">
            <VSelect v-model="form.automation_level" label="سطح خودکارسازی (L0–L4)" :options="LEVELS" />
            <VSelect v-model="form.ai_policy" label="سیاست هوش مصنوعی" :options="AI_POLICIES" />
            <VSelect v-model="form.risk_tier_max" label="حداکثر ریسک مجاز خودکار" :options="RISK_TIERS" />
            <VInput v-model.number="form.confidence_threshold" type="number" min="50" max="100" label="آستانهٔ اطمینان (R0/R1) ٪" />
            <VInput v-model.number="form.high_risk_threshold" type="number" min="50" max="100" label="آستانهٔ اطمینان (R2) ٪" />
            <VInput v-model.number="form.daily_command_limit" type="number" min="0" label="سقف روزانهٔ دستورها" />
            <VInput v-model.number="form.daily_mutation_limit" type="number" min="0" label="سقف روزانهٔ تغییرات" />
          </div>

          <div>
            <p class="text-ink-strong mb-2 text-sm font-medium">انواع محتوای مجاز برای انتشار خودکار</p>
            <div class="flex flex-wrap gap-3">
              <label v-for="type in ['meta', 'article', 'product']" :key="type" class="flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" class="h-4 w-4 rounded border-line-strong" :checked="form.enabled_content_types.includes(type)" @change="toggleContentType(type)" />
                {{ type === 'meta' ? 'متا (title/description)' : type === 'article' ? 'محتوا و مقالات' : 'محصولات ووکامرس' }}
              </label>
            </div>
          </div>

          <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input v-model="form.auto_rollback" type="checkbox" class="h-4 w-4 rounded border-line-strong" />
            بازگشت خودکار وقتی بازدید/CTR زیر baseline افتاد (فقط R3)
          </label>

          <div class="border-t border-line-strong pt-5">
            <p class="text-ink-strong mb-1 text-sm font-medium">اعلان‌ها (هشدار افت R1 و رویدادهای خودکار)</p>
            <label class="flex cursor-pointer items-center gap-2 text-sm">
              <input v-model="form.notification_enabled" type="checkbox" class="h-4 w-4 rounded border-line-strong" />
              فعال بودن اعلان‌ها
            </label>
            <div class="mt-3 flex flex-wrap gap-3">
              <label v-for="channel in NOTIFICATION_CHANNELS" :key="channel.key" class="flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" class="h-4 w-4 rounded border-line-strong" :checked="form.notification_channels.includes(channel.key)" @change="toggleChannel(channel.key)" />
                {{ channel.label }}
              </label>
            </div>
            <div v-if="form.notification_channels.includes('telegram')" class="mt-4">
              <VInput v-model="form.webhook_telegram" type="url" label="Webhook تلگرام" placeholder="https://api.telegram.org/bot…/sendMessage" />
            </div>
            <div v-if="form.notification_channels.includes('whatsapp')" class="mt-4">
              <VInput v-model="form.webhook_whatsapp" type="url" label="Webhook واتساپ" placeholder="https://…" />
            </div>
          </div>

          <VButton variant="gradient" :loading="saving" @click="save">ذخیرهٔ سیاست</VButton>
        </div>
      </VCard>

      <div class="space-y-6">
        <VCard title="توقف اضطراری">
          <p class="text-ink-muted text-sm">
            در صورت توقف، هیچ دستوری (حتی با تأیید انسانی) به وردپرس ارسال نمی‌شود و دستورهای در صف لغو می‌شوند.
          </p>
          <div class="mt-4">
            <VButton v-if="policy.emergencyStoppedAt" variant="secondary" :loading="stopping" @click="resume">
              رفع توقف و از سرگیری
            </VButton>
            <VButton v-else variant="danger" :loading="stopping" @click="emergencyStop">
              توقف فوری خودکارسازی
            </VButton>
          </div>
        </VCard>

        <VCard title="مسیریابی بر اساس نوع محتوا">
          <p class="text-ink-muted text-sm">
            هر نوع محتوا می‌تواند پروفایل مخصوص خودش را داشته باشد (مثلاً مقالات L3 با آستانهٔ ۹۰٪ کنار متا L2 با ۸۰٪).
            خالی = پروفایل پایهٔ سایت.
          </p>
          <div class="mt-4 space-y-4">
            <div v-for="type in CONTENT_TYPES" :key="type.key">
              <VSelect v-model="routeMap[type.key]" :label="type.label" :options="[{ value: '', label: 'پیش‌فرض سایت' }, ...profileOptions]" />
            </div>
            <VButton variant="secondary" :loading="savingRoutes" @click="saveRoutes">ذخیرهٔ مسیریابی</VButton>
          </div>
        </VCard>

        <VCard title="پروفایل‌ها">
          <ul class="space-y-3">
            <li v-for="profile in profiles" :key="profile.id" class="rounded-ui border border-line-strong p-3">
              <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-semibold">{{ profile.name }}</p>
                <div class="flex items-center gap-2">
                  <VBadge :tone="profile.kind === 'system' ? 'info' : 'neutral'">L{{ profile.automationLevel }}</VBadge>
                  <button class="text-brand-700 text-xs hover:underline" :disabled="copyingProfile === profile.id" @click="copyProfile(profile.id)">
                    {{ copyingProfile === profile.id ? '...' : 'کپی' }}
                  </button>
                </div>
              </div>
              <p class="text-ink-muted mt-1 text-xs">
                آستانهٔ R1: {{ profile.confidenceThreshold }}٪ · حداکثر ریسک: {{ profile.riskTierMax }} · سقف روزانه: {{ profile.dailyCommandLimit }}
              </p>
            </li>
          </ul>
        </VCard>
      </div>
    </div>

    <VCard class="mt-6" title="آخرین اجراها">
      <VTable :columns="executionsColumns" :rows="executions as unknown as TableRow[]" row-key="id">
        <template #cell-confidence="{ row }">
          <span v-if="row.confidence != null" class="font-latin">{{ row.confidence }}</span>
          <span v-else class="text-ink-muted">—</span>
        </template>
        <template #cell-decisionSource="{ row }">
          <VBadge :tone="row.decisionSource === 'policy' ? 'info' : 'neutral'">
            {{ row.decisionSource === 'policy' ? 'سیاست (خودکار)' : 'انسانی' }}
          </VBadge>
        </template>
        <template #cell-status="{ row }">
          <VBadge :tone="statusTone(String(row.status))">{{ row.status }}</VBadge>
        </template>
        <template #cell-publishedAt="{ row }">
          <span class="font-latin text-xs" dir="ltr">{{ row.publishedAt ?? '—' }}</span>
        </template>
      </VTable>
      <p v-if="executions.length === 0" class="text-ink-muted mt-4 text-sm">هنوز اجرایی ثبت نشده است.</p>
    </VCard>
  </AppLayout>
</template>
