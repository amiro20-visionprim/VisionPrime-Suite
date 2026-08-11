<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDateTime } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'
import type { LeadNote, LeadStatus, MarketingLead } from '@/types/marketing'

const props = defineProps<{
  lead: MarketingLead
  notes: LeadNote[]
  statusLabels: Record<LeadStatus, string>
  canManage: boolean
}>()

const statusTone: Record<LeadStatus, 'success' | 'warning' | 'info' | 'danger'> = {
  new: 'info',
  contacted: 'warning',
  qualified: 'success',
  unqualified: 'danger',
}

const noteForm = useForm({ body: '' })

function setStatus(status: LeadStatus): void {
  if (status === props.lead.status) {
    return
  }
  router.put(`/app/marketing/leads/${props.lead.id}/status`, { status }, { preserveScroll: true })
}

function addNote(): void {
  noteForm.post(`/app/marketing/leads/${props.lead.id}/notes`, {
    preserveScroll: true,
    onSuccess: () => noteForm.reset(),
  })
}

const attribution: { label: string; value: string | null | undefined }[] = [
  { label: 'منبع (utm_source)', value: props.lead.utmSource },
  { label: 'رسانه (utm_medium)', value: props.lead.utmMedium },
  { label: 'کمپین (utm_campaign)', value: props.lead.utmCampaign },
  { label: 'عبارت (utm_term)', value: props.lead.utmTerm },
  { label: 'محل تبلیغ (utm_content)', value: props.lead.utmContent },
  { label: 'صفحهٔ فرود', value: props.lead.landingPage },
  { label: 'مرجع (referrer)', value: props.lead.referrer },
  { label: 'دستگاه', value: props.lead.device },
  { label: 'زبان', value: props.lead.locale },
]
</script>

<template>
  <Head :title="lead.name" />
  <AppLayout>
    <VPageHeader
      :title="lead.name"
      :description="lead.company ?? 'لید ثبت‌شده'"
      :status="{ label: statusLabels[lead.status], tone: statusTone[lead.status] }"
    >
      <template #actions>
        <VBadge :tone="lead.source === 'demo' ? 'info' : 'warning'">
          {{ lead.source === 'demo' ? 'درخواست دمو' : 'پیام پشتیبانی' }}
        </VBadge>
      </template>
    </VPageHeader>

    <!-- Status decisions -->
    <div v-if="canManage" class="rounded-panel border-line bg-surface mt-8 border p-5">
      <h2 class="text-ink-strong text-sm font-bold">تصمیم دربارهٔ این لید</h2>
      <p class="text-ink-muted mt-1 text-sm">وضعیت فعلی: <span class="text-ink font-semibold">{{ statusLabels[lead.status] }}</span></p>
      <div class="mt-4 flex flex-wrap gap-2">
        <VButton size="sm" variant="secondary" :disabled="lead.status === 'new'" @click="setStatus('new')">جدید</VButton>
        <VButton size="sm" variant="secondary" :disabled="lead.status === 'contacted'" @click="setStatus('contacted')">تماس گرفته‌شده</VButton>
        <VButton size="sm" :disabled="lead.status === 'qualified'" @click="setStatus('qualified')">واجد شرایط ✓</VButton>
        <VButton size="sm" variant="danger" :disabled="lead.status === 'unqualified'" @click="setStatus('unqualified')">رد شد</VButton>
      </div>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
      <!-- Contact info -->
      <div class="rounded-panel border-line bg-surface border p-6">
        <h2 class="text-ink-strong text-sm font-bold">اطلاعات تماس</h2>
        <dl class="mt-4 space-y-3 text-sm">
          <div class="flex justify-between gap-4">
            <dt class="text-ink-muted shrink-0">نام</dt>
            <dd class="text-ink-strong font-semibold">{{ lead.name }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-ink-muted shrink-0">ایمیل</dt>
            <dd class="text-ink font-semibold" dir="ltr">{{ lead.email ?? '—' }}</dd>
          </div>
          <div v-if="lead.contact" class="flex justify-between gap-4">
            <dt class="text-ink-muted shrink-0">شمارهٔ تماس</dt>
            <dd class="text-ink font-semibold" dir="ltr">{{ lead.contact }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-ink-muted shrink-0">شرکت</dt>
            <dd class="text-ink font-semibold">{{ lead.company ?? '—' }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-ink-muted shrink-0">وب‌سایت</dt>
            <dd class="text-ink max-w-[60%] break-all font-semibold" dir="ltr">{{ lead.website ?? '—' }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-ink-muted shrink-0">تاریخ ثبت</dt>
            <dd class="text-ink font-semibold">{{ formatJalaliDateTime(lead.createdAt) }}</dd>
          </div>
        </dl>
        <div v-if="lead.message" class="border-line bg-surface-muted mt-5 rounded-card border p-4">
          <p class="text-ink-strong text-xs font-bold">پیام لید</p>
          <p class="text-ink mt-2 text-sm leading-7">{{ lead.message }}</p>
        </div>
      </div>

      <!-- Attribution -->
      <div class="rounded-panel border-line bg-surface border p-6">
        <h2 class="text-ink-strong text-sm font-bold">دادهٔ کمپین و انتساب</h2>
        <dl class="mt-4 space-y-3 text-sm">
          <div v-for="item in attribution" :key="item.label" class="flex justify-between gap-4">
            <dt class="text-ink-muted shrink-0">{{ item.label }}</dt>
            <dd class="text-ink max-w-[65%] break-all text-start font-semibold" dir="ltr">{{ item.value ?? '—' }}</dd>
          </div>
        </dl>
        <p v-if="lead.userAgent" class="text-ink-muted mt-5 border-line border-t pt-4 text-xs leading-6 break-all">
          User-Agent: {{ lead.userAgent }}
        </p>
      </div>
    </div>

    <!-- Notes -->
    <div class="rounded-panel border-line bg-surface mt-6 border p-6">
      <h2 class="text-ink-strong text-sm font-bold">یادداشت‌ها، پیشنهادها و بازخوردها</h2>
      <p class="text-ink-muted mt-1 text-sm leading-6">
        تصمیمات تیم دربارهٔ این لید، پیشنهاد برای جلسهٔ دمو و هر بازخورد داخلی اینجا ثبت می‌شود.
      </p>

      <div v-if="notes.length" class="mt-5 space-y-3">
        <div v-for="note in notes" :key="note.id" class="border-line bg-surface-muted/50 rounded-card border p-4">
          <div class="flex items-center justify-between gap-3">
            <p class="text-ink-strong text-xs font-bold">{{ note.user?.name ?? 'سیستم' }}</p>
            <p class="text-ink-muted text-xs">{{ formatJalaliDateTime(note.createdAt) }}</p>
          </div>
          <p class="text-ink mt-2 text-sm leading-7">{{ note.body }}</p>
        </div>
      </div>
      <p v-else class="text-ink-muted mt-4 text-sm">هنوز یادداشتی ثبت نشده است.</p>

      <form v-if="canManage" class="mt-5 space-y-3" @submit.prevent="addNote">
        <VTextarea v-model="noteForm.body" label="یادداشت جدید" placeholder="پیشنهاد یا بازخورد تیم دربارهٔ این لید…" :error="noteForm.errors.body" />
        <VButton type="submit" :loading="noteForm.processing">ثبت یادداشت</VButton>
      </form>
    </div>
  </AppLayout>
</template>
