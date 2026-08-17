<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { tips } from '@/lib/tips'
import VButton from '@/shared/ui/VButton.vue'
import VDrawer from '@/shared/ui/VDrawer.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import type {
  AssistantChatResponse,
  AssistantKnowledge,
  AssistantQuestion,
  ChatMessage,
} from '@/types/assistant'

const open = defineModel<boolean>({ required: true })

const activeTab = ref<'chat' | 'support'>('chat')
const messages = ref<ChatMessage[]>([])
const draft = ref('')
const loading = ref(false)
const knowledge = ref<AssistantKnowledge | null>(null)

const supportForm = ref({ name: '', contact: '', message: '' })
const supportStatus = ref<'idle' | 'sending' | 'success' | 'error'>('idle')

const greeting =
  'سلام 👋 من دستیار سوئیت هستم؛ به کاتالوگ کامل محصول وصل‌ام و پاسخ سؤال‌های رایج را می‌دانم. اگر جواب نگرفتید، تیم انسانی در کمتر از ۲۴ ساعت کاری پاسخ می‌دهد.'

const quickQuestions = computed<AssistantQuestion[]>(() => {
  const fromKnowledge = (knowledge.value?.questions ?? []).slice(0, 4)
  if (fromKnowledge.length >= 4) {
    return fromKnowledge
  }
  return [
    { id: 'q1', category: 'عمومی', question: 'سایت من در گوگل چه وضعیتی دارد؟' },
    { id: 'q2', category: 'عمومی', question: 'چرا باید چیزی را تأیید کنم؟' },
    { id: 'q3', category: 'عمومی', question: 'بهترین زمان انتشار چه وقتی است؟' },
    { id: 'q4', category: 'عمومی', question: 'می‌خواهم با یک انسان صحبت کنم' },
  ]
})

async function loadKnowledge(): Promise<void> {
  try {
    const response = await fetch('/assistant/knowledge', {
      headers: { Accept: 'application/json' },
    })
    if (response.ok) {
      knowledge.value = (await response.json()) as AssistantKnowledge
    }
  } catch {
    // Knowledge is progressive enhancement; chat still works without it.
  }
}

function ask(question: string): void {
  draft.value = ''
  messages.value.push({ role: 'user', text: question })
  void send(question)
}

async function send(text: string): Promise<void> {
  loading.value = true
  try {
    const response = await fetch('/assistant/chat', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({ message: text }),
    })
    const data = (await response.json()) as AssistantChatResponse
    messages.value.push({
      role: 'assistant',
      text: data.answer,
      links: data.links ?? [],
      suggestions: data.suggestions ?? [],
    })
  } catch {
    messages.value.push({
      role: 'assistant',
      text: 'اتصال برقرار نشد. لطفاً دوباره تلاش کنید یا از تب «پشتیبانی» استفاده کنید.',
    })
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

function submitSupport(): void {
  if (supportStatus.value === 'sending') {
    return
  }
  supportStatus.value = 'sending'
  void fetch('/assistant/contact', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-XSRF-TOKEN': csrfToken(),
    },
    body: JSON.stringify(supportForm.value),
  })
    .then(async (response) => {
      const data = (await response.json()) as { ok?: boolean; message?: string }
      if (response.ok && data.ok) {
        supportStatus.value = 'success'
        supportForm.value = { name: '', contact: '', message: '' }
      } else {
        supportStatus.value = 'error'
      }
    })
    .catch(() => {
      supportStatus.value = 'error'
    })
}

function scrollToBottom(): void {
  requestAnimationFrame(() => {
    const area = document.getElementById('vp-support-messages')
    if (area) {
      area.scrollTop = area.scrollHeight
    }
  })
}

function csrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : ''
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey && draft.value.trim() !== '' && !loading.value) {
    event.preventDefault()
    ask(draft.value.trim())
  }
}

function chipLabel(question: AssistantQuestion): string {
  return question.question.replace(/[؟?]\s*$/, '')
}

const channelLinks = [
  { icon: '📞', label: 'تماس تلفنی', href: 'tel:+989024151630' },
  { icon: '💬', label: 'واتس‌اپ', href: 'https://wa.me/989024151630' },
  { icon: '✈️', label: 'تلگرام', href: 'https://t.me/+989024151630' },
  { icon: '✉️', label: 'ایمیل', href: 'mailto:hello@visionprime-suite.ir' },
]

watch(open, (isOpen) => {
  if (isOpen) {
    if (messages.value.length === 0) {
      messages.value.push({ role: 'assistant', text: greeting })
    }
    void loadKnowledge()
  }
})
</script>

<template>
  <VDrawer v-model="open" title="راهنما و پشتیبانی" side="end">
    <div class="rounded-card border-line bg-brand-50/60 border p-4">
      <div class="flex items-start gap-3">
        <span class="rounded-ui bg-brand-700 flex size-9 shrink-0 items-center justify-center text-white">
          <VIcon name="sparkles" size="sm" />
        </span>
        <div>
          <p class="text-ink-strong text-sm font-bold">دستیار سوئیت</p>
          <p class="text-ink-muted mt-1 text-xs leading-5">
            اول پاسخ سریع سؤال‌های رایج را بگیرید؛ اگر جواب نگرفتید، تیم انسانی کنار شماست.
          </p>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-line mt-4 flex border-b">
      <button
        type="button"
        class="flex-1 px-4 py-2.5 text-sm font-bold transition"
        :class="activeTab === 'chat' ? 'text-brand-700 border-brand-700 border-b-2' : 'text-ink-muted'"
        @click="activeTab = 'chat'"
      >
        مشاورهٔ هوشمند
      </button>
      <button
        type="button"
        class="flex-1 px-4 py-2.5 text-sm font-bold transition"
        :class="activeTab === 'support' ? 'text-brand-700 border-brand-700 border-b-2' : 'text-ink-muted'"
        @click="activeTab = 'support'"
      >
        پشتیبانی انسانی
      </button>
    </div>

    <!-- Chat tab -->
    <div v-if="activeTab === 'chat'" class="flex min-h-0 flex-1 flex-col">
      <div
        id="vp-support-messages"
        class="min-h-0 flex-1 space-y-3 overflow-y-auto py-4"
      >
        <div
          v-for="(message, index) in messages"
          :key="index"
          class="flex"
          :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <div
            class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm leading-6"
            :class="
              message.role === 'user'
                ? 'bg-brand-700 text-white rounded-b-sm'
                : 'bg-surface-muted border-line text-ink border rounded-b-sm'
            "
          >
            <p>{{ message.text }}</p>
            <div v-if="message.links && message.links.length" class="mt-2 space-y-1">
              <a
                v-for="(link, linkIndex) in message.links"
                :key="linkIndex"
                :href="link.href"
                class="text-brand-700 block text-xs font-bold underline decoration-dotted underline-offset-2"
              >
                ← {{ link.label }}
              </a>
            </div>
            <div v-if="message.suggestions && message.suggestions.length" class="mt-2 flex flex-wrap gap-1.5">
              <button
                v-for="suggestion in message.suggestions"
                :key="suggestion.id"
                type="button"
                class="rounded-ui border-line bg-surface text-ink-muted hover:text-ink-strong border px-2 py-1 text-[11px] font-semibold transition"
                @click="ask(suggestion.question)"
              >
                {{ chipLabel(suggestion) }}
              </button>
            </div>
          </div>
        </div>
        <div v-if="loading" class="text-ink-muted text-xs">دستیار در حال فکر کردن…</div>
      </div>

      <div class="border-line border-t pt-3">
        <p class="text-ink-muted mb-2 text-[11px] font-semibold">شروع کنید از:</p>
        <div class="mb-3 flex flex-wrap gap-1.5">
          <button
            v-for="question in quickQuestions"
            :key="question.id"
            type="button"
            class="rounded-ui border-line bg-surface-muted text-ink hover:text-brand-700 border px-2.5 py-1.5 text-[11px] font-semibold transition"
            @click="ask(question.question)"
          >
            {{ chipLabel(question) }}
          </button>
        </div>

        <p class="text-ink-strong text-sm font-bold">سؤال‌های پرتکرار</p>
        <div class="mt-2 space-y-2">
          <details
            v-for="(item, index) in [
              { q: 'سایت من در گوگل چه وضعیتی دارد؟', a: tips.impressions },
              { q: 'چرا باید چیزی را تأیید کنم؟', a: tips['pending-decisions'] },
              { q: 'اگر تأیید کنم چه اتفاقی می‌افتد؟', a: tips.recommendation },
            ]"
            :key="index"
            class="rounded-ui border-line bg-surface-muted group border p-3"
          >
            <summary class="text-ink-strong cursor-pointer list-none text-xs font-semibold">
              <span class="flex items-center justify-between gap-2">
                {{ item.q }}
                <span class="text-ink-muted transition-transform group-open:rotate-180">▾</span>
              </span>
            </summary>
            <p class="text-ink-muted mt-2 text-xs leading-6">{{ item.a }}</p>
          </details>
        </div>

        <div class="mt-3 flex items-end gap-2">
          <textarea
            v-model="draft"
            rows="2"
            class="border-line bg-surface text-ink rounded-ui min-w-0 flex-1 resize-none border px-3 py-2 text-sm outline-none focus:border-brand-500"
            placeholder="سؤال خود را بنویسید…"
            aria-label="سؤال از دستیار"
            @keydown="onKeydown"
          />
          <VButton size="sm" :disabled="loading || !draft.trim()" @click="ask(draft.trim())">
            <template #icon><VIcon name="arrow-up" size="sm" /></template>
            ارسال
          </VButton>
        </div>
      </div>
    </div>

    <!-- Human support tab -->
    <div v-else class="py-4">
      <div class="rounded-card border-line bg-surface-muted border p-4">
        <p class="text-ink-strong text-sm font-bold">در تماس باشیم</p>
        <p class="text-ink-muted mt-1 text-xs leading-5">
          اگر پاسخ سؤال‌تان را پیدا نکردید، تیم ما آمادهٔ گفت‌وگو با شماست — کمتر از ۲۴ ساعت کاری.
        </p>
        <div class="mt-3 grid grid-cols-2 gap-2">
          <a
            v-for="channel in channelLinks"
            :key="channel.label"
            :href="channel.href"
            class="rounded-ui border-line bg-surface hover:bg-brand-50 text-ink-strong inline-flex items-center justify-center gap-1.5 border px-3 py-2 text-xs font-semibold transition"
          >
            <span aria-hidden="true">{{ channel.icon }}</span>
            {{ channel.label }}
          </a>
        </div>
      </div>

      <div class="border-line mt-5 border-t pt-5">
        <p class="text-ink-strong text-sm font-bold">پیام بگذارید</p>
        <p class="text-ink-muted mt-1 text-xs leading-5">
          پیام شما مستقیم به تیم پشتیبانی می‌رسد و در کمتر از ۲۴ ساعت کاری پاسخ می‌گیرید.
        </p>

        <div v-if="supportStatus === 'success'" class="rounded-ui bg-success-50 text-success-700 mt-4 border border-success-200 p-3 text-xs font-semibold">
          ✅ پیام شما به تیم پشتیبانی رسید؛ به‌زودی پاسخ می‌گیرید.
        </div>
        <div v-else-if="supportStatus === 'error'" class="rounded-ui bg-danger-50 text-danger-700 mt-4 border border-danger-200 p-3 text-xs font-semibold">
          ⚠️ ارسال نشد؛ لطفاً دوباره تلاش کنید یا از کانال‌های تماس استفاده کنید.
        </div>

        <form class="mt-4 space-y-3" @submit.prevent="submitSupport">
          <input
            v-model="supportForm.name"
            type="text"
            required
            class="border-line bg-surface text-ink rounded-ui w-full border px-3 py-2 text-sm outline-none focus:border-brand-500"
            placeholder="نام شما"
            aria-label="نام"
          />
          <input
            v-model="supportForm.contact"
            type="text"
            required
            class="border-line bg-surface text-ink rounded-ui w-full border px-3 py-2 text-sm outline-none focus:border-brand-500"
            placeholder="شماره تماس یا ایمیل"
            aria-label="شماره تماس یا ایمیل"
          />
          <textarea
            v-model="supportForm.message"
            rows="3"
            required
            class="border-line bg-surface text-ink rounded-ui w-full resize-none border px-3 py-2 text-sm outline-none focus:border-brand-500"
            placeholder="توضیح کوتاه مشکل یا سؤال"
            aria-label="متن پیام"
          />
          <VButton
            class="w-full"
            :disabled="supportStatus === 'sending'"
            :loading="supportStatus === 'sending'"
            type="submit"
          >
            <template #icon><VIcon name="support" size="sm" /></template>
            ارسال به تیم پشتیبانی
          </VButton>
        </form>
      </div>
    </div>
  </VDrawer>
</template>
