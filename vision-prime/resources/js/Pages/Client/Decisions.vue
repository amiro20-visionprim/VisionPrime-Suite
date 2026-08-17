<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import {
  commandTypeLabels,
  decisionLabels,
  labelOf,
  reviewSubjectLabels,
  riskTierLabels,
} from '@/lib/labels'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VDrawer from '@/shared/ui/VDrawer.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VGuideTip from '@/shared/ui/VGuideTip.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VIcon, { type IconName } from '@/shared/ui/VIcon.vue'

interface PendingCommand {
  id: number
  type: string
  risk_tier: string
  expires_at: string
  created_at: string
  site_name: string
}

interface PendingReview {
  id: number
  subject_type: string
  subject_id: number
  due_at: string | null
  created_at: string
  site_name: string
}

const props = defineProps<{
  commands: PendingCommand[]
  reviews: PendingReview[]
}>()

const subjectLabels = reviewSubjectLabels

const commandReasons: Record<string, string> = {
  update_meta_title: 'عنوان صفحه در نتایج گوگل جذاب‌تر می‌شود و کلیک بیشتری می‌گیرد.',
  update_meta_description: 'توضیح زیر عنوان در گوگل واضح‌تر می‌شود و کاربران بهتر متوجه موضوع می‌شوند.',
  update_content: 'متن صفحه کامل‌تر و مفیدتر می‌شود.',
  add_internal_link: 'لینکی از یک صفحه به صفحهٔ دیگر اضافه می‌شود تا کاربران راحت‌تر حرکت کنند.',
  update_schema: 'اطلاعات ساختاریافته به گوگل داده می‌شود تا سایت شما را بهتر بشناسد.',
  update_h1: 'عنوان اصلی صفحه اصلاح می‌شود تا مفهوم را بهتر برساند.',
  update_alt_text: 'توضیح تصویرها بهتر می‌شود؛ هم برای کاربر و هم برای گوگل.',
  add_faq_schema: 'پرسش‌های متداول به گوگل معرفی می‌شوند و شانس نمایش بیشتر می‌شود.',
  publish_content: 'محتوای آماده‌شده روی سایت شما منتشر می‌شود.',
  publish_new_article: 'مقاله/محصول جدید روی سایت شما منتشر می‌شود.',
}

const commandIcons: Record<string, IconName> = {
  update_meta_title: 'search',
  update_meta_description: 'search',
  update_content: 'file',
  add_internal_link: 'list',
  update_schema: 'gauge',
  update_h1: 'file',
  update_alt_text: 'eye',
  add_faq_schema: 'lightbulb',
  publish_content: 'zap',
  publish_new_article: 'zap',
}

interface PendingDecision {
  type: 'command' | 'review'
  id: number
  decision: 'approved' | 'rejected' | 'changes_requested'
}

const pendingDecision = ref<PendingDecision | null>(null)
const processing = ref(false)

interface AskSubject {
  type: 'command' | 'review'
  id: number
  title: string
}

const askSubject = ref<AskSubject | null>(null)
const askText = ref('')
const askSending = ref(false)

const askOpen = computed({
  get: () => askSubject.value !== null,
  set: (value: boolean) => {
    if (!value) askSubject.value = null
  },
})

const page = usePage<{ errors?: Record<string, string>; flash?: { status?: string } }>()
const errors = computed(() => page.props.errors as Record<string, string>)
const flashStatus = computed(() => page.props.flash?.status)

function isPending(type: 'command' | 'review', id: number): boolean {
  const pending = pendingDecision.value
  return pending !== null && pending.type === type && pending.id === id
}

function choose(type: 'command' | 'review', id: number, decision: PendingDecision['decision']): void {
  pendingDecision.value = { type, id, decision }
}

function cancel(): void {
  pendingDecision.value = null
}

function confirmDecision(): void {
  const pending = pendingDecision.value
  if (pending === null || processing.value) return

  processing.value = true
  const url =
    pending.type === 'command'
      ? `/client/decisions/commands/${pending.id}`
      : `/client/decisions/reviews/${pending.id}`

  router.post(url, { decision: pending.decision }, {
    preserveScroll: true,
    onFinish: () => {
      processing.value = false
      pendingDecision.value = null
    },
  })
}

function openAsk(type: 'command' | 'review', id: number, title: string): void {
  askSubject.value = { type, id, title }
  askText.value = ''
}

function sendQuestion(): void {
  const subject = askSubject.value
  if (subject === null || askSending.value || askText.value.trim().length < 5) return

  askSending.value = true
  router.post(
    '/client/decisions/questions',
    {
      subject_type: subject.type,
      subject_id: subject.id,
      question: askText.value.trim(),
    },
    {
      preserveScroll: true,
      onFinish: () => {
        askSending.value = false
        askSubject.value = null
      },
    },
  )
}
</script>

<template>
  <Head title="تأییدهای من | پرتال مشتری" />
  <ClientPortalLayout>
    <VPageHeader
      title="تأییدهای من"
      description="چیزهایی که منتظر تصمیم شماست. بدون تأیید شما، هیچ تغییری روی سایت اعمال نمی‌شود."
    >
      <template #actions
        ><VGuideTip :text="'پیشنهادهایی که تیم ما آماده کرده و منتظر تأیید شماست. بدون تأیید شما هیچ تغییری روی سایت انجام نمی‌شود.'" /></template
      >
    </VPageHeader>

    <VAlert v-if="flashStatus" tone="success" class="mt-6">{{ flashStatus }}</VAlert>
    <VAlert v-if="errors.decision" tone="danger" class="mt-6">{{ errors.decision }}</VAlert>
    <VAlert v-if="errors.question" tone="danger" class="mt-6">{{ errors.question }}</VAlert>

    <section class="mt-8">
      <div class="flex items-center gap-3">
        <h2 class="text-ink-strong text-lg font-bold">تغییرات در انتظار تأیید</h2>
        <VBadge v-if="props.commands.length" tone="warning">{{ props.commands.length }} مورد</VBadge>
      </div>
      <div v-if="props.commands.length" class="mt-4 space-y-4">
        <VCard v-for="command in props.commands" :key="command.id">
          <div class="flex items-start gap-4">
            <span class="rounded-ui bg-brand-50 text-brand-700 flex size-11 shrink-0 items-center justify-center">
              <VIcon :name="commandIcons[command.type] ?? 'zap'" size="lg" />
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-ink-strong font-semibold">
                  {{ labelOf(commandTypeLabels, command.type) }}
                </p>
                <VBadge tone="neutral">{{ command.site_name }}</VBadge>
              </div>

              <div class="bg-surface-muted rounded-ui mt-3 border border-line p-3">
                <p class="text-ink-muted text-xs font-bold">چرا این پیشنهاد شده؟</p>
                <p class="text-ink mt-1 text-sm leading-6">
                  {{ commandReasons[command.type] ?? 'این تغییر به رشد سایت کمک می‌کند.' }}
                </p>
              </div>

              <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                <span class="text-ink-muted inline-flex items-center gap-1">
                  <VIcon name="shield" size="sm" />
                  {{ labelOf(riskTierLabels, command.risk_tier) }}
                </span>
                <span class="text-ink-muted inline-flex items-center gap-1">
                  <VIcon name="clock" size="sm" />
                  تا {{ formatJalaliDate(command.expires_at) }} فرصت تصمیم‌گیری دارید
                </span>
              </div>
            </div>
          </div>

          <div v-if="!isPending('command', command.id)" class="mt-4 flex flex-wrap gap-3">
            <VButton size="sm" @click="choose('command', command.id, 'approved')">✓ تأیید</VButton>
            <VButton
              size="sm"
              variant="secondary"
              @click="openAsk('command', command.id, labelOf(commandTypeLabels, command.type))"
            >
              ❓ سؤال از تیم
            </VButton>
            <VButton size="sm" variant="ghost" @click="choose('command', command.id, 'rejected')">
              رد
            </VButton>
          </div>
          <div v-else class="mt-4 flex flex-wrap items-center gap-3">
            <span class="text-ink-strong text-sm font-semibold">
              مطمئن هستید؟ {{ decisionLabels[pendingDecision!.decision] }}
            </span>
            <VButton
              size="sm"
              :variant="pendingDecision!.decision === 'approved' ? 'primary' : 'danger'"
              :loading="processing"
              @click="confirmDecision"
            >
              بله، ثبت تصمیم
            </VButton>
            <VButton size="sm" variant="ghost" :disabled="processing" @click="cancel">
              انصراف
            </VButton>
          </div>
        </VCard>
      </div>
      <VEmptyState
        v-else
        class="mt-4"
        title="تغییری در انتظار تأیید نیست"
        description="هر تغییری که به تأیید شما نیاز داشته باشد، با مهلت تصمیم‌گیری در اینجا نمایش داده می‌شود."
      />
    </section>

    <section class="mt-10">
      <div class="flex items-center gap-3">
        <h2 class="text-ink-strong text-lg font-bold">بازبینی‌های در انتظار شما</h2>
        <VBadge v-if="props.reviews.length" tone="warning">{{ props.reviews.length }} مورد</VBadge>
      </div>
      <div v-if="props.reviews.length" class="mt-4 space-y-4">
        <VCard v-for="review in props.reviews" :key="review.id">
          <div class="flex items-start gap-4">
            <span class="rounded-ui bg-violet-50 text-violet-600 flex size-11 shrink-0 items-center justify-center">
              <VIcon name="eye" size="lg" />
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-ink-strong font-semibold">
                  {{ labelOf(subjectLabels, review.subject_type) }}
                </p>
                <VBadge tone="neutral">{{ review.site_name }}</VBadge>
              </div>
              <p class="text-ink-muted mt-2 text-sm">
                در انتظار بازبینی از {{ formatJalaliDate(review.created_at) }}
              </p>
            </div>
          </div>
          <div v-if="!isPending('review', review.id)" class="mt-4 flex flex-wrap gap-3">
            <VButton size="sm" @click="choose('review', review.id, 'approved')">✓ تأیید</VButton>
            <VButton
              size="sm"
              variant="secondary"
              @click="openAsk('review', review.id, labelOf(subjectLabels, review.subject_type))"
            >
              ❓ سؤال از تیم
            </VButton>
            <VButton
              size="sm"
              variant="ghost"
              @click="choose('review', review.id, 'changes_requested')"
            >
              درخواست تغییر
            </VButton>
            <VButton size="sm" variant="ghost" @click="choose('review', review.id, 'rejected')">
              رد
            </VButton>
          </div>
          <div v-else class="mt-4 flex flex-wrap items-center gap-3">
            <span class="text-ink-strong text-sm font-semibold">
              مطمئن هستید؟ {{ decisionLabels[pendingDecision!.decision] }}
            </span>
            <VButton
              size="sm"
              :variant="pendingDecision!.decision === 'approved' ? 'primary' : 'danger'"
              :loading="processing"
              @click="confirmDecision"
            >
              بله، ثبت تصمیم
            </VButton>
            <VButton size="sm" variant="ghost" :disabled="processing" @click="cancel">
              انصراف
            </VButton>
          </div>
        </VCard>
      </div>
      <VEmptyState
        v-else
        class="mt-4"
        title="موردی در انتظار بازبینی نیست"
        description="محتوا یا تغییراتی که به نظر شما نیاز داشته باشند، در این بخش قرار می‌گیرند."
      />
    </section>

    <VDrawer v-model="askOpen" title="سؤال از تیم" side="end">
      <template v-if="askSubject">
        <div class="rounded-card border-line bg-surface-muted border p-4">
          <p class="text-ink-muted text-xs">دربارهٔ این مورد سؤال دارید:</p>
          <p class="text-ink-strong mt-1 text-sm font-bold">{{ askSubject.title }}</p>
        </div>
        <label class="mt-5 block">
          <span class="text-ink-strong text-sm font-bold">سؤال شما</span>
          <textarea
            v-model="askText"
            rows="5"
            class="mt-2 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm leading-6 outline-none focus:border-brand-600"
            placeholder="مثلاً: این تغییر دقیقاً چه تأثیری روی سایت من دارد؟"
          />
        </label>
        <p class="text-ink-muted mt-2 text-xs">
          تیم ما پاسخ را برای شما می‌فرستد؛ در این فاصله، این مورد در انتظار می‌ماند.
        </p>
        <div class="mt-5 flex gap-3">
          <VButton :loading="askSending" :disabled="askText.trim().length < 5" @click="sendQuestion">
            ارسال سؤال
          </VButton>
          <VButton variant="ghost" @click="askSubject = null">انصراف</VButton>
        </div>
      </template>
    </VDrawer>
  </ClientPortalLayout>
</template>
