<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VConfirmDialog from '@/shared/ui/VConfirmDialog.vue'

const props = defineProps<{
  site: { id: number; name: string; canonicalUrl: string }
  connection: null | {
    status: string
    platformUrl: string | null
    pluginVersion: string | null
    lastSeenAt: string | null
    health: Record<string, unknown>
  }
}>()
const page = usePage<{ flash?: { pairingToken?: string; pairingTokenExpiresAt?: string } }>()
function generateToken(): void {
  router.post(`/app/sites/${props.site.id}/connector/pairing-token`, {}, { preserveScroll: true })
}
const disconnectOpen = ref(false)
function copyToken(): void {
  navigator.clipboard.writeText(page.props.flash?.pairingToken ?? '')
}

const syncing = ref(false)
const syncResult = ref<null | {synced: number, errors: string[], url_profiles_count: number}>(null)

async function syncWordPress() {
  syncing.value = true
  syncResult.value = null
  try {
    const res = await fetch('/api/content/sync-wordpress', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: JSON.stringify({ site_id: props.site.id })
    })
    syncResult.value = await res.json()
  } catch (e: any) {
    syncResult.value = { synced: 0, errors: [e.message], url_profiles_count: 0 }
  }
  syncing.value = false
}
function disconnect(): void {
  router.post(`/app/sites/${props.site.id}/connector/disconnect`)
}
</script>
<template>
  <Head :title="`اتصال وردپرس ${site.name}`" />
  <AppLayout>
    <VPageHeader
      title="اتصال وردپرس"
      :description="site.canonicalUrl"
      :breadcrumbs="[
        { label: 'سایت‌ها', href: '/app/sites' },
        { label: site.name, href: `/app/sites/${site.id}` },
        { label: 'اتصال وردپرس' },
      ]"
    />
    <VCard class="mt-8" title="وضعیت اتصال">
      <template #action
        ><VBadge :tone="connection?.status === 'connected' ? 'success' : 'warning'">{{
          connection?.status === 'connected' ? 'متصل' : 'اتصال برقرار نیست'
        }}</VBadge></template
      >
      <dl v-if="connection" class="divide-line divide-y">
        <div class="flex justify-between gap-4 py-3">
          <dt class="text-ink-muted">آدرس پلتفرم</dt>
          <dd class="font-latin text-ink-strong" dir="ltr">{{ connection.platformUrl }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-3">
          <dt class="text-ink-muted">نسخه پلاگین</dt>
          <dd>{{ connection.pluginVersion || '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-3">
          <dt class="text-ink-muted">آخرین فعالیت</dt>
          <dd>{{ connection.lastSeenAt || '—' }}</dd>
        </div>
      </dl>
      <p v-else class="text-ink-muted">
        برای اتصال، ابتدا افزونه وردپرس را نصب کنید، سپس توکن اتصال ایجاد کرده و در تنظیمات افزونه وارد کنید.
      </p>
      <div v-if="!connection || connection.status !== 'connected'" class="border-line mt-6 border-t pt-5">
        <h3 class="text-ink-strong mb-3 text-sm font-bold">مرحله ۱ — دانلود و نصب افزونه</h3>
        <p class="text-ink-muted mb-3 text-sm leading-6">
          افزونه وردپرس را دانلود کرده و از مسیر <strong dir="ltr">افزونه‌ها → افزودن → بارگذاری افزونه</strong> نصب کنید.
        </p>
        <a
          href="/vision-prime-connector.zip"
          download
          class="transition-ui rounded-ui bg-brand-50 text-brand-700 hover:bg-brand-100 inline-flex items-center gap-2 border border-brand-200 px-4 py-2.5 text-sm font-bold"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          دانلود افزونه وردپرس
        </a>
        <p class="text-ink-muted mt-2 text-xs">نسخه ۱.۲.۰ · پشتیبانی از مقاله، صفحه و محصولات ووکامرس</p>
      </div>

      
      <div v-if="connection?.status === 'connected'" class="border-line mt-6 border-t pt-5">
        <h3 class="text-ink-strong mb-3 text-sm font-bold">همگام‌سازی محتوا از وردپرس</h3>
        <p class="text-ink-muted mb-3 text-sm">صفحات، مقالات و محصولات وردپرس را برای لینک‌سازی داخلی هوشمند دریافت کنید.</p>
        <VButton @click="syncWordPress" :loading="syncing" variant="secondary">🔄 سینک محتوا از وردپرس</VButton>
        <div v-if="syncResult" class="rounded-card bg-surface mt-4 p-4">
          <p v-if="syncResult.errors?.length === 0" class="text-green-600 text-sm font-semibold">{{ syncResult.synced }} محتوا سینک شد — {{ syncResult.url_profiles_count }} صفحه در پایگاه داده</p>
          <div v-else>
            <p class="text-red-600 text-sm">خطا: {{ syncResult.errors?.join(', ') }}</p>
          </div>
        </div>
      </div>
      <div class="border-line mt-6 border-t pt-5">
        <h3 class="text-ink-strong mb-3 text-sm font-bold">مرحله ۲ — جفت‌سازی</h3>
        <div class="flex flex-wrap gap-3">
          <VButton @click="generateToken">ایجاد توکن اتصال</VButton
          ><VButton
            v-if="connection?.status === 'connected'"
            variant="danger"
            @click="disconnectOpen = true"
            >قطع اتصال</VButton
          >
        </div>
        <div v-if="page.props.flash?.pairingToken" class="rounded-card bg-warning-50 mt-4 p-4">
          <p class="text-warning-700 text-sm font-semibold">
            این توکن فقط اکنون نمایش داده می‌شود — کپی کنید و در وردپرس وارد کنید.
          </p>
          <code
            class="rounded-ui bg-surface font-latin text-ink-strong mt-3 block p-3 text-sm break-all"
            dir="ltr"
            >{{ page.props.flash.pairingToken }}</code
          >
          <div class="mt-3 flex items-center gap-3">
            <VButton size="sm" variant="secondary" @click="copyToken">کپی توکن</VButton
            ><span class="text-ink-muted text-sm"
              >انقضا: {{ page.props.flash.pairingTokenExpiresAt }}</span
            >
          </div>
        </div>
      </div>
    </VCard>
    <VConfirmDialog
      v-model="disconnectOpen"
      title="قطع اتصال وردپرس"
      description="کلید محرمانه این سایت حذف می‌شود و افزونه تا اتصال مجدد قادر به ارسال درخواست معتبر نخواهد بود."
      confirm-label="قطع اتصال"
      tone="danger"
      @confirm="disconnect"
    />
  </AppLayout>
</template>
