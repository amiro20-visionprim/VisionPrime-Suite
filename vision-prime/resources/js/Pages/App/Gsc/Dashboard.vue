<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import type { GscAccount, GscImportRun, GscProperty } from '@/types/gsc'

const props = defineProps<{ accounts: GscAccount[]; properties: GscProperty[]; runs: GscImportRun[]; flash?: { status?: string } }>()

const propertyId = ref(props.properties[0] ? String(props.properties[0].id) : '')
const days = ref('28')
const importing = ref(false)

const hasProperties = computed(() => props.properties.length > 0)

const dateRange = computed(() => {
  const end = new Date()
  const start = new Date()
  start.setDate(end.getDate() - Number(days.value))
  const fmt = (d: Date) => d.toISOString().slice(0, 10)
  return { start: fmt(start), end: fmt(end) }
})

function startImport() {
  if (!propertyId.value) return
  importing.value = true
  router.post(
    '/app/gsc/import',
    {
      gsc_property_id: Number(propertyId.value),
      date_start: dateRange.value.start,
      date_end: dateRange.value.end,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        importing.value = false
      },
    },
  )
}
</script>
<template>
  <Head title="Google Search Console" /><AppLayout
    ><VPageHeader
      title="Google Search Console"
      description="اتصال داده‌های جستجو و تبدیل آن‌ها به فرصت‌های رشد."
      ><template #actions
        ><VButton href="/app/gsc/connect">اتصال حساب Google</VButton></template
      ></VPageHeader
    >
    <VAlert v-if="props.flash?.status" class="mb-5" tone="success">{{ props.flash.status }}</VAlert>
    <VCard class="mt-6" title="وارد کردن دادهٔ سرچ کنسول"
      ><div class="flex flex-wrap items-end gap-4">
        <div class="w-64">
          <VSelect
            v-model="propertyId"
            :options="
              props.properties.map((p) => ({ value: String(p.id), label: `${p.site_name} — ${p.property_uri}` }))
            "
            label="ملک"
            :disabled="!hasProperties"
          />
        </div>
        <div class="w-40">
          <VInput v-model="days" type="number" min="1" max="90" label="بازهٔ روزهای گذشته" />
        </div>
        <VButton :disabled="!hasProperties || importing" :loading="importing" @click="startImport">
          شروع Import
        </VButton>
      </div>
      <p v-if="!hasProperties" class="text-ink-muted mt-3 text-sm">
        برای import ابتدا یک Property انتخاب کنید.
      </p></VCard
    >
    <section class="mt-8 grid gap-5 lg:grid-cols-2">
      <VCard title="حساب‌های متصل"
        ><div v-if="accounts.length" class="space-y-3">
          <div v-for="account in accounts" :key="account.id" class="flex justify-between">
            <span class="font-latin" dir="ltr">{{ account.email }}</span
            ><VBadge tone="success">متصل</VBadge>
          </div>
        </div>
        <VEmptyState
          v-else
          title="حساب Google متصل نیست"
          description="برای انتخاب Property و دریافت داده، ابتدا حساب Google را متصل کنید." /></VCard
      ><VCard title="ملک‌های انتخاب‌شده"
        ><div v-if="properties.length" class="space-y-3">
          <div v-for="property in properties" :key="property.id">
            <p class="font-semibold">{{ property.site_name }}</p>
            <p class="font-latin text-ink-muted text-sm" dir="ltr">{{ property.property_uri }}</p>
          </div>
        </div>
        <VEmptyState
          v-else
          title="ملک انتخاب نشده است"
          description="پس از اتصال حساب، ملک مناسب هر سایت را انتخاب کنید."
          action-label="انتخاب ملک"
          @action="$inertia.visit('/app/gsc/properties')"
      /></VCard>
    </section>
    <VCard class="mt-6" title="آخرین Importها"
      ><div v-if="runs.length" class="divide-line divide-y">
        <div v-for="run in runs" :key="run.id" class="flex justify-between py-3">
          <span>{{ run.site_name }}</span
          ><VBadge
            :tone="
              run.status === 'completed'
                ? 'success'
                : run.status === 'failed'
                  ? 'danger'
                  : 'warning'
            "
            >{{ run.status }}</VBadge
          >
        </div>
      </div>
      <p v-else class="text-ink-muted">هنوز همگام‌سازی داده‌ای اجرا نشده است.</p></VCard
    ></AppLayout
  >
</template>
