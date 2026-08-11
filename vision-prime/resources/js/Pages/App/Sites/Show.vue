<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface Site {
  id: number
  name: string
  canonicalUrl: string
  projectName?: string
  clientName?: string
  locale?: string
  timezone?: string
  businessImportance?: string
  status?: string
}

interface GscStatus {
  connected: boolean
  property: { id: number; uri: string; type: string; status: string } | null
  accountEmail: string | null
  latestRun: {
    status: string
    summary: Record<string, unknown> | null
    error: string | null
    startedAt: string | null
    finishedAt: string | null
  } | null
}

interface ConnectorStatus {
  connected: boolean
  status: string | null
  platformUrl: string | null
  pluginVersion: string | null
  lastSeenAt: string | null
}

defineProps<{ site: Site; gsc: GscStatus; connector: ConnectorStatus }>()

const runStatusLabel: Record<string, string> = {
  pending: 'در صف',
  running: 'در حال اجرا',
  completed: 'تکمیل شده',
  failed: 'ناموفق',
}

type BadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'

const runStatusTone: Record<string, BadgeTone> = {
  pending: 'warning',
  running: 'info',
  completed: 'success',
  failed: 'danger',
}
</script>
<template>
  <Head :title="site.name" />
  <AppLayout>
    <VPageHeader :title="site.name" :description="site.canonicalUrl">
      <template #actions>
        <VButton :href="`/app/sites/${site.id}/edit`" variant="secondary">ویرایش</VButton>
      </template>
    </VPageHeader>

    <div class="mt-8 grid gap-5 md:grid-cols-2">
      <VCard title="سرچ کنسول">
        <template v-if="gsc.connected && gsc.property">
          <div class="flex items-center gap-2">
            <VBadge tone="success">متصل</VBadge>
            <span class="text-ink-strong font-latin text-sm" dir="ltr">{{ gsc.property.uri }}</span>
          </div>
          <p v-if="gsc.accountEmail" class="text-ink-muted mt-2 text-sm">
            حساب: {{ gsc.accountEmail }}
          </p>
          <div v-if="gsc.latestRun" class="text-ink-muted mt-3 border-t pt-3 text-sm">
            <div class="flex items-center gap-2">
              <VBadge :tone="runStatusTone[gsc.latestRun.status] ?? 'warning'">
                {{ runStatusLabel[gsc.latestRun.status] ?? gsc.latestRun.status }}
              </VBadge>
              <span v-if="gsc.latestRun.finishedAt">
                {{ formatJalaliDate(gsc.latestRun.finishedAt) }}
              </span>
            </div>
            <p
              v-if="gsc.latestRun.summary && gsc.latestRun.summary.rows"
              class="mt-1"
            >
              {{ gsc.latestRun.summary.rows }} ردیف همگام‌سازی شد.
            </p>
          </div>
          <div v-else class="text-ink-muted mt-3 text-sm">هنوز همگام‌سازی‌ای اجرا نشده است.</div>
          <div class="mt-4">
            <VButton :href="`/app/gsc`" variant="secondary" size="sm">
              رفتن به سرچ کنسول
            </VButton>
          </div>
        </template>
        <template v-else>
          <p class="text-ink-muted text-sm">این سایت هنوز به سرچ کنسول متصل نشده است.</p>
          <div class="mt-4">
            <VButton :href="`/app/gsc`" variant="secondary" size="sm">اتصال سرچ کنسول</VButton>
          </div>
        </template>
      </VCard>

      <VCard title="اتصال وردپرس">
        <template v-if="connector.connected">
          <div class="flex items-center gap-2">
            <VBadge :tone="connector.status === 'active' ? 'success' : 'warning'">
              {{ connector.status === 'active' ? 'متصل' : connector.status }}
            </VBadge>
            <span class="text-ink-strong font-latin text-sm" dir="ltr">
              {{ connector.platformUrl }}
            </span>
          </div>
          <p v-if="connector.pluginVersion" class="text-ink-muted mt-2 text-sm">
            نسخه افزونه: {{ connector.pluginVersion }}
          </p>
          <p v-if="connector.lastSeenAt" class="text-ink-muted mt-1 text-sm">
            آخرین ارتباط: {{ formatJalaliDate(connector.lastSeenAt) }}
          </p>
          <div class="mt-4">
            <VButton :href="`/app/sites/${site.id}/connector`" variant="secondary" size="sm">
              مدیریت اتصال
            </VButton>
          </div>
        </template>
        <template v-else>
          <p class="text-ink-muted text-sm">این سایت هنوز به وردپرس متصل نشده است.</p>
          <div class="mt-4">
            <VButton :href="`/app/sites/${site.id}/connector`" variant="secondary" size="sm">
              اتصال وردپرس
            </VButton>
          </div>
        </template>
      </VCard>
    </div>
  </AppLayout>
</template>
