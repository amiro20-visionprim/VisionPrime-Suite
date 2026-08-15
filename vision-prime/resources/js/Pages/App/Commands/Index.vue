<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import {
  commandStatusLabels,
  commandTypeLabels,
  contentScopeLabels,
  labelOf,
  riskTierLabels,
} from '@/lib/labels'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Command, Paginated } from '@/types/automation'
defineProps<{ commands: Paginated<Command> }>()

function decide(command: Command, decision: 'approved' | 'rejected'): void {
  router.post(`/app/commands/${command.id}/decision`, { decision }, { preserveScroll: true })
}

const statusTone = (status: string) =>
  status === 'executed' || status === 'completed' || status === 'approved'
    ? 'success'
    : status === 'failed' || status === 'cancelled' || status === 'rejected'
      ? 'danger'
      : status === 'rolled_back'
        ? 'warning'
        : status === 'pending_approval'
          ? 'warning'
          : 'info'

const isAutoPublishable = (command: Command) =>
  command.type === 'publish_new_article'

function factorLabel(key: string): string {
  const labels: Record<string, string> = {
    data_quality: 'کیفیت داده (تازگی GSC)',
    signal_strength: 'قدرت سیگنال',
    source_agreement: 'توافق منابع',
    history: 'سابقه',
    human_approved: 'تأیید انسانی',
    gsc_freshness: 'تازگی GSC',
    source: 'منبع',
    score: 'امتیاز خام',
  }
  return labels[key] ?? key
}

function factorValue(value: unknown): string {
  if (typeof value === 'number') return Math.round(value * 100) / 100 === value ? String(value) : String(Math.round(value * 100) / 100)
  if (typeof value === 'boolean') return value ? 'بله' : 'خیر'
  if (value === null || value === undefined) return '—'
  return String(value)
}

function gateRows(snapshot: Record<string, unknown> | null | undefined): [string, string][] {
  if (!snapshot) return []
  const rows: [string, string][] = []
  const labels: Record<string, string> = {
    auto_publish_scope: 'دامنه انتشار',
    warmup_count: 'گرمایش (موفق)',
    warmup_required: 'گرمایش (موردنیاز)',
    quality_score: 'امتیاز کیفیت',
    confidence_score: 'امتیاز اطمینان',
    confidence_threshold: 'آستانه اطمینان',
    max_risk_tier: 'سقف ریسک',
    automation_level: 'سطح خودکارسازی',
    ai_policy: 'سیاست هوش مصنوعی',
    risk_tier: 'ریسک',
  }
  for (const [key, value] of Object.entries(snapshot)) {
    const label = labels[key]
    if (!label) continue
    if (value === null || value === undefined || value === '') continue
    rows.push([label, String(value)])
  }
  return rows
}
</script>
<template>
  <Head title="تغییرات اجرایی" /><AppLayout
    ><VPageHeader
      title="تغییرات اجرایی"
      description="دستورهای کنترل‌شده، وضعیت تأیید و چرخه اجرای آن‌ها."
    />
    <div class="mt-8 space-y-3">
      <div
        v-for="command in commands.data"
        :key="command.id"
        class="rounded-card border-line bg-surface border p-5"
      >
        <Link
          :href="`/app/commands/${command.id}`"
          class="block transition-colors hover:text-brand-700"
        >
          <div class="flex justify-between">
            <div class="flex flex-wrap items-center gap-2">
              <span>{{ labelOf(commandTypeLabels, command.type) }}</span>
              <VBadge v-if="command.content_type" tone="info">
                {{ labelOf(contentScopeLabels, command.content_type) }}
              </VBadge>
              <VBadge v-if="command.auto_approved" tone="success">انتشار خودکار</VBadge>
            </div>
            <VBadge :tone="statusTone(command.status)">
              {{ labelOf(commandStatusLabels, command.status) }}
            </VBadge>
          </div>
          <p class="text-ink-muted mt-2 text-sm">
            {{ command.site_name ?? '—' }}
            <template v-if="command.platform_url">
              · <span class="font-latin" dir="ltr">{{ command.platform_url }}</span>
            </template>
            · {{ labelOf(riskTierLabels, command.risk_tier) }}
            <template v-if="command.confidence_score !== null && command.confidence_score !== undefined">
              · اطمینان: {{ command.confidence_score }}
            </template>
            <template v-if="command.published_at">
              · منتشر: {{ formatJalaliDate(command.published_at) }}
            </template>
          </p>
        </Link>

        <!-- جزئیات auto_publish برای publish_new_article -->
        <div
          v-if="isAutoPublishable(command)"
          class="border-line mt-3 grid gap-4 border-t pt-3 sm:grid-cols-2"
        >
          <div v-if="gateRows(command.gate_snapshot).length">
            <p class="text-ink-strong mb-2 text-sm font-semibold">🛡️ snapshot گیت‌ها</p>
            <div class="flex flex-wrap gap-2">
              <VBadge
                v-for="[label, value] in gateRows(command.gate_snapshot)"
                :key="label"
                tone="neutral"
              >
                {{ label }}: {{ value }}
              </VBadge>
            </div>
          </div>
          <div v-if="Object.keys(command.confidence_factors ?? {}).length">
            <p class="text-ink-strong mb-2 text-sm font-semibold">🎯 عوامل اطمینان</p>
            <ul class="space-y-1">
              <li
                v-for="[key, value] in Object.entries(command.confidence_factors ?? {})"
                :key="key"
                class="text-ink-muted text-sm"
              >
                {{ factorLabel(key) }}: <span class="text-ink-strong">{{ factorValue(value) }}</span>
              </li>
            </ul>
          </div>
        </div>

        <div
          v-if="command.post_url"
          class="border-line mt-3 flex items-center gap-2 border-t pt-3"
        >
          <span class="text-ink-strong text-sm">📄 مقالهٔ منتشرشده:</span>
          <a
            :href="command.post_url"
            target="_blank"
            rel="noopener noreferrer"
            class="font-latin text-sm text-brand-700 underline hover:text-brand-900"
            dir="ltr"
          >
            {{ command.post_url }}
          </a>
        </div>

        <!-- گزارش تأثیر پس از انتشار (GSC) -->
        <div v-if="command.impact" class="border-line mt-3 border-t pt-3">
          <p class="text-ink-strong mb-2 text-sm font-semibold">📈 تأثیر پس از انتشار</p>
          <template v-if="command.impact.status === 'ready' && command.impact.delta">
            <VBadge
              :tone="
                command.impact.verdict === 'improved'
                  ? 'success'
                  : command.impact.verdict === 'declined'
                    ? 'danger'
                    : 'info'
              "
            >
              {{
                command.impact.verdict === 'improved'
                  ? 'بهبود'
                  : command.impact.verdict === 'declined'
                    ? 'افت'
                    : 'بدون تغییر'
              }}
            </VBadge>
            <div class="mt-2 flex flex-wrap gap-2">
              <VBadge tone="neutral">جایگاه: {{ command.impact.delta.position }}</VBadge>
              <VBadge tone="neutral">کلیک: {{ command.impact.delta.clicks }}</VBadge>
              <VBadge tone="neutral">نمایش: {{ command.impact.delta.impressions }}</VBadge>
            </div>
          </template>
          <p v-else class="text-ink-muted text-sm">
            دادهٔ GSC کافی برای مقایسه در دسترس نیست.
          </p>
        </div>

        <div
          v-if="command.status === 'pending_approval'"
          class="border-line mt-3 flex gap-2 border-t pt-3"
        >
          <VButton size="sm" @click="decide(command, 'approved')">تأیید</VButton>
          <VButton size="sm" variant="danger" @click="decide(command, 'rejected')">رد</VButton>
        </div>
      </div>
    </div></AppLayout
  >
</template>
