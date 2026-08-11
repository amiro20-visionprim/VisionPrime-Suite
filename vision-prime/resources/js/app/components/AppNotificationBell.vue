<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'

import { formatJalaliDateTime } from '@/lib/locale'
import type { AppPageProps } from '@/types/app'

interface AppNotification {
  id: string
  read: boolean
  leadId: number | null
  leadName: string
  source: string | null
  score: number | null
  campaign: string | null
  createdAt: string | null
}

const page = usePage<
  AppPageProps & { notificationCount?: number; permissions?: string[] }
>()

const open = ref(false)
const loading = ref(false)
const notifications = ref<AppNotification[]>([])
const localUnread = ref(page.props.notificationCount ?? 0)

const canViewMarketing = computed(
  () => page.props.permissions?.includes('marketing.view.organization') ?? false,
)

const unreadCount = computed(() => localUnread.value)

async function toggle(): Promise<void> {
  open.value = !open.value
  if (open.value && notifications.value.length === 0) {
    await load()
  }
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await fetch('/app/notifications', {
      headers: { Accept: 'application/json' },
    })
    const data = (await response.json()) as {
      notifications: AppNotification[]
      unreadCount: number
    }
    notifications.value = data.notifications
    localUnread.value = data.unreadCount
  } catch {
    // Bell remains functional on next open.
  } finally {
    loading.value = false
  }
}

async function markAllRead(): Promise<void> {
  await fetch('/app/notifications/read-all', {
    method: 'PUT',
    headers: {
      Accept: 'application/json',
      'X-XSRF-TOKEN': csrfToken(),
    },
  })
  localUnread.value = 0
  notifications.value = notifications.value.map((n) => ({ ...n, read: true }))
}

async function openNotification(notification: AppNotification): Promise<void> {
  if (!notification.read) {
    await fetch(`/app/notifications/${notification.id}/read`, {
      method: 'PUT',
      headers: {
        Accept: 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
      },
    })
    localUnread.value = Math.max(0, localUnread.value - 1)
    notifications.value = notifications.value.map((n) =>
      n.id === notification.id ? { ...n, read: true } : n,
    )
  }

  open.value = false
  if (notification.leadId !== null) {
    router.visit(`/app/marketing/leads/${notification.leadId}`)
  } else {
    router.visit('/app/marketing')
  }
}

function csrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : ''
}

onMounted(() => {
  localUnread.value = page.props.notificationCount ?? 0
})
</script>

<template>
  <div v-if="canViewMarketing" class="relative">
    <button
      type="button"
      class="border-line text-ink-strong relative flex size-10 items-center justify-center rounded-ui border transition hover:bg-surface-muted"
      aria-label="اعلان‌ها"
      :aria-expanded="open"
      @click="toggle"
    >
      <span aria-hidden="true" class="text-lg leading-none">🔔</span>
      <span
        v-if="unreadCount > 0"
        class="bg-danger-600 absolute -top-1.5 -end-1.5 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] font-bold text-white"
        >{{ unreadCount }}</span
      >
    </button>

    <div
      v-if="open"
      class="shadow-panel border-line bg-surface absolute end-0 top-12 z-40 flex w-[min(360px,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border"
      role="menu"
      aria-label="لیست اعلان‌ها"
    >
      <div class="border-line flex items-center justify-between border-b px-4 py-3">
        <p class="text-ink-strong text-sm font-bold">اعلان لیدهای جدید</p>
        <button
          v-if="unreadCount > 0"
          type="button"
          class="text-brand-700 text-xs font-bold hover:underline"
          @click="markAllRead"
        >
          خواندن همه
        </button>
      </div>

      <div class="max-h-80 overflow-y-auto">
        <div v-if="loading" class="text-ink-muted px-4 py-6 text-center text-sm">در حال بارگیری…</div>
        <template v-else-if="notifications.length">
          <button
            v-for="notification in notifications"
            :key="notification.id"
            type="button"
            class="hover:bg-surface-muted/60 flex w-full items-start gap-3 border-b px-4 py-3 text-start last:border-0"
            :class="notification.read ? 'opacity-70' : ''"
            @click="openNotification(notification)"
          >
            <span class="text-brand-700 mt-0.5 text-base" aria-hidden="true">🆕</span>
            <span class="min-w-0 flex-1">
              <span class="text-ink-strong block text-sm font-bold">
                لید جدید: {{ notification.leadName }}
              </span>
              <span class="text-ink-muted mt-1 block text-xs leading-5">
                {{ notification.source === 'support' ? 'پیام پشتیبانی' : 'درخواست دمو' }}
                <template v-if="notification.campaign"> · {{ notification.campaign }}</template>
                <template v-if="notification.score !== null"> · امتیاز {{ notification.score }}</template>
              </span>
              <span class="text-ink-muted mt-1 block text-xs">
                {{ formatJalaliDateTime(notification.createdAt) }}
              </span>
            </span>
            <span
              v-if="!notification.read"
              class="bg-brand-600 mt-1 size-2 shrink-0 rounded-full"
              aria-hidden="true"
            />
          </button>
        </template>
        <p v-else class="text-ink-muted px-4 py-6 text-center text-sm">
          اعلان جدیدی نیست.
        </p>
      </div>

      <Link
        href="/app/marketing"
        class="border-line text-brand-700 border-t px-4 py-3 text-center text-xs font-bold hover:bg-surface-muted"
        @click="open = false"
      >
        مشاهدهٔ داشبورد بازاریابی
      </Link>
    </div>
  </div>
</template>
