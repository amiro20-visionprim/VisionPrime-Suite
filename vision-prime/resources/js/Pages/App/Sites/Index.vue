<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
defineProps<{
  sites: { data: { id: number; name: string; canonicalUrl: string; projectName: string }[] }
}>()
</script>
<template>
  <Head title="سایت‌ها" /><AppLayout
    ><VPageHeader title="سایت‌ها" description="سایت‌های عملیاتی و آماده اتصال به منابع داده."
      ><template #actions
        ><VButton href="/app/sites/create">افزودن سایت</VButton></template
      ></VPageHeader
    >
    <div v-if="sites.data.length" class="mt-8 space-y-3">
      <Link
        v-for="site in sites.data"
        :key="site.id"
        :href="`/app/sites/${site.id}`"
        class="rounded-card border-line bg-surface shadow-card block border p-5"
        ><p class="text-ink-strong font-semibold">{{ site.name }}</p>
        <p class="font-latin text-ink-muted mt-2 text-sm" dir="ltr">{{ site.canonicalUrl }}</p>
        <p class="text-ink mt-2 text-sm">{{ site.projectName }}</p></Link
      >
    </div>
    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز سایتی ثبت نشده است"
      description="با افزودن اولین سایت، مسیر اتصال سرچ کنسول و وردپرس را شروع کنید."
      action-label="افزودن سایت"
      @action="$inertia.visit('/app/sites/create')"
  /></AppLayout>
</template>
