<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import type { GscAccount, GscProperty } from '@/types/gsc'
interface SiteOption {
  id: number
  name: string
  canonical_url: string
}
defineProps<{
  accounts: GscAccount[]
  properties: Record<string, GscProperty[]>
  sites: SiteOption[]
  googleErrors: Record<string, string>
}>()
const form = useForm({
  site_id: '',
  gsc_account_id: '',
  property_uri: '',
  property_type: 'url-prefix',
})
function save() {
  form.post('/app/gsc/properties')
}
</script>
<template>
  <Head title="ملک‌های سرچ کنسول" /><AppLayout
    ><VAlert
        v-if="form.gsc_account_id && googleErrors[form.gsc_account_id]"
        tone="danger"
        title="دریافت ملک‌ها از گوگل ناموفق بود"
        class="mb-5"
      >
        {{ googleErrors[form.gsc_account_id] }}
        <a
          class="underline underline-offset-2"
          href="https://console.developers.google.com/apis/api/webmasters.googleapis.com/overview?project=visionprime-suite-505019"
          target="_blank"
          rel="noopener"
        >فعال‌سازی Search Console API</a
        >
      </VAlert
      ><VCard title="انتخاب Property سرچ کنسول"
      ><form class="space-y-4" @submit.prevent="save">
        <VSelect
          v-model="form.site_id"
          label="سایت"
          :options="sites.map((site) => ({ label: site.name, value: String(site.id) }))"
        /><VSelect
          v-model="form.gsc_account_id"
          label="حساب Google"
          :options="
            accounts.map((account) => ({ label: account.email, value: String(account.id) }))
          "
        /><VSelect
          v-model="form.property_uri"
          label="ملک"
          :options="
            (properties[form.gsc_account_id] || []).map((property) => ({
              label: property.property_uri,
              value: property.property_uri,
            }))
          "
        /><VButton type="submit">ذخیره ملک</VButton>
      </form></VCard
    ></AppLayout
  >
</template>
