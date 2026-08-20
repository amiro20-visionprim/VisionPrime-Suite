<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
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

interface ContentSummary {
  total: number
  byType: Record<string, number>
  recent: Array<{ id: number; url: string; type: string; status: string; lastSyncedAt: string | null }>
}

const props = defineProps<{
  site: Site
  gsc: GscStatus
  connector: ConnectorStatus
  content: ContentSummary
  isSuperAdmin?: boolean
  isAdmin?: boolean
}>()

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

function disconnectSite(): void {
  if (!confirm('آیا مطمئنید اتصال این سایت قطع شود؟ تمام داده‌های همگام‌سازی حذف خواهند شد.')) return
  router.post(`/app/sites/${props.site.id}/connector/disconnect`)
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
          <div class="mt-4 flex items-center gap-2">
            <VButton :href="`/app/sites/${site.id}/connector`" variant="secondary" size="sm">
              مدیریت اتصال
            </VButton>
            <VButton
              v-if="isSuperAdmin || isAdmin"
              variant="danger"
              size="sm"
              @click="disconnectSite"
            >
              قطع اتصال
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

    <!-- Content Summary -->
    <div v-if="content.total > 0" class="mt-8">
      <h2 class="text-ink-strong mb-4 text-lg font-bold">محتوای همگام‌سازی شده</h2>
      <div class="grid gap-4 md:grid-cols-3 mb-5">
        <VCard v-for="(count, type) in content.byType" :key="type">
          <div class="text-center">
            <p class="text-ink-strong text-2xl font-bold">{{ count }}</p>
            <p class="text-ink-muted text-sm">{{ type === 'page' ? 'صفحه' : type === 'post' ? 'مقاله' : type === 'product' ? 'محصول' : type }}</p>
          </div>
        </VCard>
      </div>
      <VCard title="آخرین صفحات همگام‌سازی شده">
        <div class="space-y-2">
          <div
            v-for="item in content.recent"
            :key="item.id"
            class="border-line flex items-center justify-between rounded-lg border px-4 py-3"
          >
            <div class="min-w-0 flex-1">
              <p class="text-ink-strong truncate text-sm font-medium" dir="ltr">{{ item.url }}</p>
              <p class="text-ink-muted text-xs">
                {{ item.type === 'page' ? 'صفحه' : item.type === 'post' ? 'مقاله' : 'محصول' }}
                · {{ item.status === 'publish' ? 'منتشر شده' : item.status }}
              </p>
            </div>
            <VBadge :tone="item.status === 'publish' ? 'success' : 'neutral'" size="sm">
              {{ item.status === 'publish' ? 'فعال' : item.status }}
            </VBadge>
          </div>
        </div>
        <p v-if="content.total > 10" class="text-ink-muted mt-3 text-center text-xs">
          و {{ content.total - 10 }} صفحه دیگر...
        </p>
      </VCard>
    </div>

    <div v-else class="mt-8">
      <VCard>
        <p class="text-ink-muted text-center text-sm">
          هنوز محتوایی از این سایت همگام‌سازی نشده است.
        </p>
      </VCard>
    </div>
  </AppLayout>
</template>
