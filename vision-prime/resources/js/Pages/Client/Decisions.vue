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
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

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

interface PendingDecision {
  type: 'command' | 'review'
  id: number
  decision: 'approved' | 'rejected' | 'changes_requested'
}

const pendingDecision = ref<PendingDecision | null>(null)
const processing = ref(false)

const page = usePage()
const errors = computed(() => page.props.errors as Record<string, string>)

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
    onFinish: () => {
      processing.value = false
      pendingDecision.value = null
    },
  })
}
</script>

<template>
  <Head title="نیازمند تصمیم شما" />
  <ClientPortalLayout>
    <VPageHeader
      title="نیازمند تصمیم شما"
      description="فقط مواردی که برای ادامه مسیر به تأیید یا بازبینی شما نیاز دارند."
    />

    <div
      v-if="errors.decision"
      class="border-danger-200 bg-danger-50 text-danger-700 mt-8 rounded-ui border px-4 py-3 text-sm"
    >
      {{ errors.decision }}
    </div>

    <section class="mt-8">
      <div class="flex items-center gap-3">
        <h2 class="text-ink-strong text-lg font-bold">تأیید تغییرات اجرایی</h2>
        <VBadge v-if="props.commands.length" tone="warning">{{ props.commands.length }} مورد</VBadge>
      </div>
      <div v-if="props.commands.length" class="mt-4 space-y-4">
        <VCard v-for="command in props.commands" :key="command.id">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <p class="text-ink-strong font-semibold">
                  {{ labelOf(commandTypeLabels, command.type) }}
                </p>
                <VBadge tone="neutral">{{ command.site_name }}</VBadge>
              </div>
              <p class="text-ink-muted mt-2 text-sm">
                سطح ریسک: {{ labelOf(riskTierLabels, command.risk_tier) }} · ثبت در
                {{ formatJalaliDate(command.created_at) }}
              </p>
              <p class="text-warning-700 mt-1 text-xs font-semibold">
                تا {{ formatJalaliDate(command.expires_at) }} فرصت تصمیم‌گیری دارید
              </p>
            </div>
          </div>
          <div v-if="!isPending('command', command.id)" class="mt-4 flex flex-wrap gap-3">
            <VButton size="sm" @click="choose('command', command.id, 'approved')">تأیید</VButton>
            <VButton size="sm" variant="danger" @click="choose('command', command.id, 'rejected')">
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
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
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
            <VButton size="sm" @click="choose('review', review.id, 'approved')">تأیید</VButton>
            <VButton
              size="sm"
              variant="danger"
              @click="choose('review', review.id, 'rejected')"
            >
              رد
            </VButton>
            <VButton
              size="sm"
              variant="secondary"
              @click="choose('review', review.id, 'changes_requested')"
            >
              درخواست تغییر
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
  </ClientPortalLayout>
</template>
