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
        برای اتصال، توکن اتصال (Pairing Token) ایجاد کرده و آن را در تنظیمات افزونه سوئیت
        وردپرس وارد کنید.
      </p>
      <div class="border-line mt-6 border-t pt-5">
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
            این توکن فقط اکنون نمایش داده می‌شود.
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
