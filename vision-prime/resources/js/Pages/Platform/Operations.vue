<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'

import PlatformLayout from '@/platform/layouts/PlatformLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VStatCard from '@/shared/ui/VStatCard.vue'

interface SchedulerRow {
  job: string
  label: string
  last_run: string | null
}

interface AiUsageRow {
  organization_id: number
  organization_name: string
  plan: string
  tokens: number
  cap: number
  usage_percent: number
}

defineProps<{
  queue: { pending: number; failed_24h: number; failed_total: number }
  scheduler: SchedulerRow[]
  aiUsage: AiUsageRow[]
  connections: { paired: number; unpaired: number; sites_without_connection: number }
  plansCount: number
}>()

const usageTone = (percent: number): 'success' | 'warning' | 'danger' | 'neutral' | 'info' =>
  percent >= 100 ? 'danger' : percent >= 80 ? 'warning' : 'success'

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)

const emergencyOpen = ref(false)
const emergencyReason = ref('')

function emergencyStop(): void {
  router.post('/platform/emergency-stop', { reason: emergencyReason.value }, { preserveScroll: true })
}
</script>

<template>
  <Head title="رصد فنی" />
  <PlatformLayout>
    <VPageHeader
      title="رصد فنی پلتفرم"
      description="سلامت صف، زمان‌بندی‌ها، مصرف هوش مصنوعی و اتصال‌ها — همه در یک نگاه."
    />

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <VStatCard
        label="کارهای در صف"
        :value="queue.pending"
        icon="clock"
        icon-tone="neutral"
        hint="در انتظار پردازش"
      />
      <VStatCard
        label="خطای ۲۴ ساعت اخیر"
        :value="queue.failed_24h"
        icon="x"
        icon-tone="danger"
        hint="jobهای ناموفق دیروز"
      />
      <VStatCard
        label="اتصال‌های فعال"
        :value="connections.paired"
        icon="activity"
        icon-tone="success"
        hint="سایت‌های متصل به پلاگین"
      />
      <VStatCard
        label="سایت‌های بدون اتصال"
        :value="connections.sites_without_connection"
        icon="ban"
        icon-tone="warning"
        hint="نیازمند توجه"
      />
    </div>

    <div class="border-danger-200/60 bg-danger-50/60 mt-6 rounded-2xl border p-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-ink-strong font-display text-lg font-bold">🚨 توقف اضطراری اتوماسیون</h2>
          <p class="text-ink-muted mt-1 text-sm">
            توقف فوری همهٔ دستورهای خودکار در کل پلتفرم (یا یک سازمان) — با ثبت کامل در گزارش ممیزی.
          </p>
        </div>
        <VButton variant="danger" @click="emergencyOpen = true">فعال‌سازی توقف اضطراری</VButton>
      </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <VCard title="زمان‌بندی‌ها (Scheduler)" description="آخرین اجرای هر job خودکار">
        <ul class="divide-line divide-y">
          <li
            v-for="job in scheduler"
            :key="job.job"
            class="flex items-center justify-between gap-3 py-3"
          >
            <div>
              <p class="text-ink-strong text-sm font-semibold">{{ job.label }}</p>
              <p class="font-latin text-ink-muted text-xs" dir="ltr">{{ job.job }}</p>
            </div>
            <VBadge :tone="job.last_run ? 'success' : 'warning'">
              {{ job.last_run ? job.last_run : 'هنوز اجرا نشده' }}
            </VBadge>
          </li>
        </ul>
      </VCard>

      <VCard title="مصرف هوش مصنوعی این ماه" description="توکن خروجی هر سازمان در برابر سقف پلن">
        <ul v-if="aiUsage.length" class="space-y-3">
          <li
            v-for="org in aiUsage"
            :key="org.organization_id"
            class="border-line rounded-xl border px-4 py-3"
          >
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-ink-strong text-sm font-semibold">{{ org.organization_name }}</p>
                <p class="text-ink-muted text-xs">پلن: {{ org.plan }}</p>
              </div>
              <VBadge :tone="usageTone(org.usage_percent)">
                {{ org.cap ? `${faNum(org.tokens)} / ${faNum(org.cap)} توکن (${org.usage_percent}٪)` : 'بدون سقف' }}
              </VBadge>
            </div>
            <div
              v-if="org.cap"
              class="bg-surface-muted mt-3 h-2 overflow-hidden rounded-full"
            >
              <div
                class="h-full rounded-full transition-all"
                :class="org.usage_percent >= 100 ? 'bg-danger-500' : org.usage_percent >= 80 ? 'bg-warning-500' : 'bg-brand-500'"
                :style="{ width: `${Math.min(100, org.usage_percent)}%` }"
              />
            </div>
          </li>
        </ul>
        <p v-else class="text-ink-muted py-3 text-sm">سازمان فعالی با اشتراک وجود ندارد.</p>
      </VCard>
    </div>

    <!-- مودال توقف اضطراری -->
    <div
      v-if="emergencyOpen"
      class="bg-ink-900/40 fixed inset-0 z-50 flex items-center justify-center p-4"
      @click.self="emergencyOpen = false"
    >
      <div class="bg-surface rounded-2xl w-full max-w-md p-6 shadow-2xl">
        <h3 class="text-danger-600 font-display text-lg font-bold">🚨 تأیید توقف اضطراری</h3>
        <p class="text-ink-muted mt-1 text-sm">
          این اقدام همهٔ دستورهای خودکار در صف را لغو و سیاست‌های اتوماسیون را متوقف می‌کند.
          دلیل ثبت و در گزارش ممیزی ذخیره می‌شود.
        </p>
        <textarea
          v-model="emergencyReason"
          rows="3"
          class="border-line bg-surface-muted/60 text-ink-strong focus:border-danger-500 mt-4 w-full rounded-xl border px-3 py-2 text-sm outline-none"
          placeholder="دلیل توقف اضطراری (الزامی)…"
        />
        <div class="mt-5 flex justify-end gap-3">
          <VButton variant="ghost" size="sm" @click="emergencyOpen = false">انصراف</VButton>
          <VButton variant="danger" size="sm" :disabled="!emergencyReason.trim()" @click="emergencyStop">
            بله، توقف کن
          </VButton>
        </div>
      </div>
    </div>
  </PlatformLayout>
</template>
