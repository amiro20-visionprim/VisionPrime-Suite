<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import { decisionLabels, labelOf, reviewStatusLabels, reviewSubjectLabels } from '@/lib/labels'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'

interface ReviewItem {
  id: number
  subject_type: string
  subject_id: number
  status: string
  created_at: string
}

interface ReviewDecision {
  id: number
  decision: string
  note: string | null
  decided_at: string
}

interface MoneyPageAuditSubject {
  kind: 'money_page_audit'
  audit: { id: number; score: number; summary: Record<string, unknown> | null; auditedAt: string } | null
  issues: { key: string; severity: string; explanation: string }[]
  url: string | null
}

interface AiGenerationSubject {
  kind: 'ai_generation'
  generation: { id: number; input: string | null; status: string; usage: Record<string, unknown> | null; createdAt: string } | null
}

interface CommandSubject {
  kind: 'command'
  command: { id: number; type: string; riskTier: string; payload: Record<string, unknown> | null; status: string; expiresAt: string | null } | null
}

interface UrlProfileSubject {
  kind: 'url_profile'
  profile: { canonicalUrl: string; slug: string; contentType: string } | null
}

type Subject = MoneyPageAuditSubject | AiGenerationSubject | CommandSubject | UrlProfileSubject

const p = defineProps<{
  item: ReviewItem
  decisions: ReviewDecision[]
  subject: Subject | null
}>()

const f = useForm({ decision: 'approved', note: '' })

function decide() {
  f.post(`/app/reviews/${p.item.id}/decision`)
}

const issueSeverityLabels: Record<string, string> = {
  low: 'کم',
  medium: 'متوسط',
  high: 'زیاد',
  critical: 'بحرانی',
}

const issueSeverityTone: Record<string, 'neutral' | 'info' | 'success' | 'warning' | 'danger'> = {
  low: 'info',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
}

function payloadRows(payload: Record<string, unknown> | null): [string, unknown][] {
  if (!payload) return []
  return Object.entries(payload).filter(([, value]) => value !== null && value !== '')
}
</script>
<template>
  <Head title="جزئیات بررسی" />
  <AppLayout>
    <VPageHeader
      title="بررسی خروجی"
      :description="labelOf(reviewSubjectLabels, p.item.subject_type)"
    />

    <VCard class="mt-8" title="محتوا">
      <!-- money_page_audit -->
      <template v-if="p.subject?.kind === 'money_page_audit'">
        <div v-if="p.subject.audit" class="space-y-4">
          <div class="flex flex-wrap items-center gap-3">
            <VBadge tone="info">امتیاز: {{ p.subject.audit.score }}</VBadge>
            <span class="text-ink-muted text-sm">
              بازبینی در {{ formatJalaliDate(p.subject.audit.auditedAt) }}
            </span>
          </div>
          <p v-if="p.subject.url" class="font-latin text-sm" dir="ltr">
            {{ p.subject.url }}
          </p>
          <div v-if="p.subject.issues.length" class="space-y-2">
            <div
              v-for="issue in p.subject.issues"
              :key="issue.key"
              class="border-line flex items-start gap-3 rounded-ui border p-3"
            >
              <VBadge :tone="issueSeverityTone[issue.severity] ?? 'neutral'">
                {{ issueSeverityLabels[issue.severity] ?? issue.severity }}
              </VBadge>
              <p class="text-ink-strong text-sm">{{ issue.explanation }}</p>
            </div>
          </div>
          <p v-else class="text-ink-muted text-sm">مشکلی ثبت نشده است.</p>
        </div>
        <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
      </template>

      <!-- ai_generation -->
      <template v-else-if="p.subject?.kind === 'ai_generation'">
        <div v-if="p.subject.generation" class="space-y-3">
          <p v-if="p.subject.generation.input" class="text-ink-strong text-sm whitespace-pre-wrap">
            {{ p.subject.generation.input }}
          </p>
          <p class="text-ink-muted text-sm">
            وضعیت: {{ p.subject.generation.status }} · ایجاد در
            {{ formatJalaliDate(p.subject.generation.createdAt) }}
          </p>
        </div>
        <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
      </template>

      <!-- command -->
      <template v-else-if="p.subject?.kind === 'command'">
        <div v-if="p.subject.command" class="space-y-3">
          <p class="text-ink-strong text-sm">
            {{ labelOf(reviewSubjectLabels, p.subject.command.type) }} (ریسک:
            {{ p.subject.command.riskTier }})
          </p>
          <div v-if="payloadRows(p.subject.command.payload).length" class="space-y-1">
            <div
              v-for="[key, value] in payloadRows(p.subject.command.payload)"
              :key="key"
              class="text-ink-muted text-sm"
            >
              <span class="text-ink-strong">{{ key }}:</span> {{ String(value) }}
            </div>
          </div>
        </div>
        <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
      </template>

      <!-- url_profile -->
      <template v-else-if="p.subject?.kind === 'url_profile'">
        <div v-if="p.subject.profile" class="space-y-2">
          <p class="font-latin text-sm" dir="ltr">{{ p.subject.profile.canonicalUrl }}</p>
          <p class="text-ink-muted text-sm">
            نوع محتوا: {{ p.subject.profile.contentType }} · اسلاگ: {{ p.subject.profile.slug }}
          </p>
        </div>
        <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
      </template>

      <p v-else class="text-ink-muted text-sm">محتوا در دسترس نیست.</p>
    </VCard>

    <VCard class="mt-6" title="وضعیت">
      <VBadge :tone="p.item.status === 'approved' ? 'success' : p.item.status === 'rejected' ? 'danger' : 'warning'">
        {{ labelOf(reviewStatusLabels, p.item.status) }}
      </VBadge>
    </VCard>

    <VCard class="mt-6" title="تصمیم">
      <form class="space-y-4" @submit.prevent="decide">
        <select v-model="f.decision" class="w-full">
          <option value="approved">تأیید</option>
          <option value="rejected">رد</option>
          <option value="changes_requested">درخواست تغییر</option>
        </select>
        <VTextarea v-model="f.note" label="یادداشت تصمیم" />
        <VButton type="submit" :loading="f.processing">ثبت تصمیم</VButton>
      </form>
    </VCard>

    <VCard class="mt-6" title="تاریخچه تصمیم">
      <div v-if="decisions.length">
        <div v-for="d in decisions" :key="d.id" class="border-line border-b py-3">
          <div class="flex items-center gap-2">
            <VBadge :tone="d.decision === 'approved' ? 'success' : d.decision === 'rejected' ? 'danger' : 'warning'">
              {{ labelOf(decisionLabels, d.decision) }}
            </VBadge>
            <span class="text-ink-muted text-sm">{{ formatJalaliDate(d.decided_at) }}</span>
          </div>
          <p v-if="d.note" class="text-ink-muted mt-1 text-sm">{{ d.note }}</p>
        </div>
      </div>
      <p v-else class="text-ink-muted">هنوز تصمیمی ثبت نشده است.</p>
    </VCard>
  </AppLayout>
</template>
