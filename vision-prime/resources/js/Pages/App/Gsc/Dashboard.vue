<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { GscAccount, GscImportRun, GscProperty } from '@/types/gsc'
defineProps<{ accounts: GscAccount[]; properties: GscProperty[]; runs: GscImportRun[] }>()
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
      ><VCard title="Propertyهای انتخاب‌شده"
        ><div v-if="properties.length" class="space-y-3">
          <div v-for="property in properties" :key="property.id">
            <p class="font-semibold">{{ property.site_name }}</p>
            <p class="font-latin text-ink-muted text-sm" dir="ltr">{{ property.property_uri }}</p>
          </div>
        </div>
        <VEmptyState
          v-else
          title="Property انتخاب نشده است"
          description="پس از اتصال حساب، Property مناسب هر Site را انتخاب کنید."
          action-label="انتخاب Property"
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
      <p v-else class="text-ink-muted">هنوز Importی اجرا نشده است.</p></VCard
    ></AppLayout
  >
</template>
