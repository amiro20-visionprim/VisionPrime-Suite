<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { trackEvent } from '@/lib/analytics'
import VButton from '@/shared/ui/VButton.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import type {
  AssistantChatResponse,
  AssistantKnowledge,
  AssistantQuestion,
  ChatMessage,
} from '@/types/assistant'

const open = ref(false)
const activeTab = ref<'chat' | 'support'>('chat')
const messages = ref<ChatMessage[]>([])
const draft = ref('')
const loading = ref(false)
const knowledge = ref<AssistantKnowledge | null>(null)

const supportForm = ref({ name: '', contact: '', message: '' })
const supportStatus = ref<'idle' | 'sending' | 'success' | 'error'>('idle')

const greeting =
  'سلام 👋 من مشاور سوئیت هستم؛ دانش‌ام همیشه با آخرین آپدیت‌های محصول همگام است. دربارهٔ معرفی، قیمت‌گذاری، دمو، امنیت، پرتال مشتری یا پشتیبانی بپرس — یا از سؤال‌های آماده شروع کن.'

function toggle(): void {
  open.value = !open.value
  if (open.value) {
    trackEvent('assistant_open')
    if (messages.value.length === 0) {
      messages.value.push({ role: 'assistant', text: greeting })
    }
    void loadKnowledge()
  }
}

async function loadKnowledge(): Promise<void> {
  try {
    const response = await fetch('/assistant/knowledge', {
      headers: { Accept: 'application/json' },
    })
    if (response.ok) {
      knowledge.value = (await response.json()) as AssistantKnowledge
    }
  } catch {
    // Knowledge is a progressive enhancement; chat still works without it.
  }
}

function ask(question: string): void {
  draft.value = ''
  messages.value.push({ role: 'user', text: question })
  void send(question)
}

async function send(text: string): Promise<void> {
  loading.value = true
  trackEvent('assistant_chat')

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
  trackEvent('assistant_support_submit')

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
    const area = document.getElementById('assistant-messages')
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

onMounted(() => {
  void loadKnowledge()
})
</script>

<template>
  <div class="fixed bottom-4 left-4 z-50 flex flex-col items-start gap-3">
    <!-- Panel -->
    <div
      v-if="open"
      class="shadow-panel border-line bg-surface flex max-h-[min(600px,calc(100dvh-8rem))] w-[min(380px,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border"
      role="dialog"
      aria-label="مشاور سوئیت"
    >
      <!-- Header -->
      <div class="bg-brand-900 flex items-center justify-between gap-3 px-4 py-3 text-white">
        <div class="flex items-center gap-2.5">
          <span
            class="bg-brand-700 flex size-9 items-center justify-center rounded-full shadow-inner"
            aria-hidden="true"
          >
            <VIcon name="sparkles" size="sm" />
          </span>
          <div>
            <p class="text-sm font-bold">مشاور سوئیت</p>
            <p class="text-brand-200 flex items-center gap-1 text-xs">
              <span class="size-1.5 animate-pulse rounded-full bg-emerald-400" /> آنلاین — همیشه به‌روز
            </p>
          </div>
        </div>
        <button
          type="button"
          class="rounded-ui px-2 py-1 text-lg leading-none text-white/80 hover:bg-white/10 hover:text-white"
          aria-label="بستن"
          @click="open = false"
        >
          ×
        </button>
      </div>

      <!-- Tabs -->
      <div class="border-line flex border-b">
        <button
          type="button"
          class="flex-1 px-4 py-2.5 text-sm font-bold transition"
          :class="activeTab === 'chat' ? 'text-brand-700 border-brand-700 border-b-2' : 'text-ink-muted'"
          @click="activeTab = 'chat'"
        >
          مشاوره
        </button>
        <button
          type="button"
          class="flex-1 px-4 py-2.5 text-sm font-bold transition"
          :class="activeTab === 'support' ? 'text-brand-700 border-brand-700 border-b-2' : 'text-ink-muted'"
          @click="activeTab = 'support'"
        >
          پشتیبانی
        </button>
      </div>

      <!-- Chat tab -->
      <template v-if="activeTab === 'chat'">
        <div
          id="assistant-messages"
          class="bg-surface-muted/40 flex-1 space-y-3 overflow-y-auto px-4 py-4"
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
                  ? 'bg-brand-700 rounded-bl-sm text-white'
                  : 'border-line bg-surface rounded-br-sm text-ink shadow-sm'
              "
            >
              <p>{{ message.text }}</p>
              <div v-if="message.links?.length" class="mt-2 flex flex-wrap gap-1.5">
                <a
                  v-for="link in message.links"
                  :key="link.href"
                  :href="link.href"
                  class="rounded-ui bg-brand-50 text-brand-700 px-2.5 py-1 text-xs font-bold hover:bg-brand-100"
                  >{{ link.label }}</a
                >
              </div>
            </div>
          </div>

          <div v-if="loading" class="text-ink-muted flex items-center gap-2 text-sm">
            <span class="size-2 animate-bounce rounded-full bg-current" />
            <span class="size-2 animate-bounce rounded-full bg-current [animation-delay:120ms]" />
            <span class="size-2 animate-bounce rounded-full bg-current [animation-delay:240ms]" />
          </div>
        </div>

        <!-- Suggestions (after the user asked something) -->
        <div
          v-if="messages.length > 1 && !loading"
          class="border-line flex gap-2 overflow-x-auto border-t px-4 py-2.5"
        >
          <button
            v-for="question in messages[messages.length - 1]?.suggestions?.slice(0, 3)"
            :key="question.id"
            type="button"
            class="rounded-ui border-line text-ink-muted hover:text-brand-700 hover:border-brand-200 shrink-0 border px-3 py-1.5 text-xs font-semibold"
            @click="ask(question.question)"
          >
            {{ chipLabel(question) }}
          </button>
        </div>

        <!-- Quick chips (before first user message) -->
        <div
          v-else-if="knowledge && messages.length === 1"
          class="border-line flex gap-2 overflow-x-auto border-t px-4 py-2.5"
        >
          <button
            v-for="question in knowledge.questions.slice(0, 4)"
            :key="question.id"
            type="button"
            class="rounded-ui border-line text-ink-muted hover:text-brand-700 hover:border-brand-200 shrink-0 border px-3 py-1.5 text-xs font-semibold"
            @click="ask(question.question)"
          >
            {{ chipLabel(question) }}
          </button>
        </div>

        <!-- Input -->
        <div class="border-line flex items-center gap-2 border-t px-3 py-3">
          <input
            v-model="draft"
            type="text"
            class="border-line bg-surface text-ink-strong placeholder:text-ink-muted/60 focus:border-brand-400 min-w-0 flex-1 rounded-ui border px-3 py-2 text-sm outline-none"
            placeholder="سؤال خود را بپرسید…"
            :disabled="loading"
            @keydown="onKeydown"
          />
          <button
            type="button"
            class="rounded-ui bg-brand-700 flex size-9 shrink-0 items-center justify-center text-white transition hover:bg-brand-900 disabled:opacity-50"
            :disabled="loading || draft.trim() === ''"
            aria-label="ارسال"
            @click="ask(draft.trim())"
          >
            <span aria-hidden="true">➤</span>
          </button>
        </div>
      </template>

      <!-- Support tab -->
      <template v-else>
        <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
          <div>
            <p class="text-ink-strong text-sm font-bold">راه‌های ارتباطی</p>
            <p class="text-ink-muted mt-1 text-xs leading-5">
              همهٔ کانال‌ها روی شمارهٔ ۰۹۰۲۴۱۵۱۶۳۰ · پاسخ در کمتر از ۲۴ ساعت کاری
            </p>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <a
              v-for="channel in channelLinks"
              :key="channel.label"
              :href="channel.href"
              target="_blank"
              rel="noopener noreferrer"
              class="rounded-card border-line text-ink-strong hover:border-brand-200 hover:text-brand-700 flex items-center gap-2 border px-3 py-2.5 text-xs font-bold transition"
            >
              <span aria-hidden="true">{{ channel.icon }}</span>
              {{ channel.label }}
            </a>
          </div>

          <div class="border-line rounded-card border p-4">
            <p class="text-ink-strong text-sm font-bold">پیام برای پشتیبان</p>
            <form class="mt-3 space-y-3" @submit.prevent="submitSupport">
              <input
                v-model="supportForm.name"
                type="text"
                required
                class="border-line bg-surface text-ink-strong placeholder:text-ink-muted/60 focus:border-brand-400 w-full rounded-ui border px-3 py-2 text-sm outline-none"
                placeholder="نام شما"
              />
              <input
                v-model="supportForm.contact"
                type="text"
                required
                dir="ltr"
                class="border-line bg-surface text-ink-strong placeholder:text-ink-muted/60 focus:border-brand-400 w-full rounded-ui border px-3 py-2 text-sm outline-none"
                placeholder="ایمیل یا شمارهٔ تماس"
              />
              <textarea
                v-model="supportForm.message"
                required
                rows="3"
                class="border-line bg-surface text-ink-strong placeholder:text-ink-muted/60 focus:border-brand-400 w-full resize-none rounded-ui border px-3 py-2 text-sm outline-none"
                placeholder="پیام شما"
              />
              <VButton
                type="submit"
                class="w-full"
                size="sm"
                :loading="supportStatus === 'sending'"
                >ارسال پیام</VButton
              >
              <p
                v-if="supportStatus === 'success'"
                class="text-success-700 bg-success-50 rounded-ui px-3 py-2 text-xs font-semibold"
              >
                ✅ پیام شما رسید؛ در کمتر از ۲۴ ساعت کاری پاسخ می‌گیرید.
              </p>
              <p
                v-else-if="supportStatus === 'error'"
                class="text-danger-600 bg-danger-50 rounded-ui px-3 py-2 text-xs font-semibold"
              >
                ارسال نشد؛ دوباره تلاش کنید یا از واتس‌اپ استفاده کنید.
              </p>
            </form>
          </div>
        </div>
      </template>
    </div>

    <!-- FAB -->
    <button
      type="button"
      class="bg-brand-700 hover:bg-brand-900 shadow-panel group flex items-center gap-2 rounded-full py-3 ps-4 pe-6 text-white transition focus:outline-none"
      :aria-label="open ? 'بستن مشاور' : 'باز کردن مشاور'"
      @click="toggle"
    >
      <span class="bg-brand-600 relative flex size-6 items-center justify-center rounded-full" aria-hidden="true">
        <VIcon name="sparkles" size="sm" />
        <span
          class="absolute -top-0.5 -right-0.5 size-2.5 animate-pulse rounded-full border-2 border-white bg-emerald-400"
        />
      </span>
      <span class="text-sm font-bold">{{ open ? 'بستن' : 'مشاور آنلاین' }}</span>
    </button>
  </div>
</template>
