<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
defineProps<{
  profiles: {
    data: { id: number; url: string; type: string; status: string; lastSyncedAt: string | null }[]
  }
}>()
</script>
<template>
  <Head title="URLها و محتوا" /><AppLayout
    ><VPageHeader
      title="URLها و محتوا"
      description="پروفایل‌های همگام‌سازی‌شده و تاریخچه محتوای سایت‌ها." />
    <div v-if="profiles.data.length" class="mt-8 space-y-3">
      <Link
        v-for="p in profiles.data"
        :key="p.id"
        :href="`/app/url-profiles/${p.id}`"
        class="rounded-card border-line bg-surface block border p-5"
        ><p class="font-latin text-brand-700 text-sm font-semibold break-all" dir="ltr">
          {{ p.url }}
        </p>
        <p class="text-ink mt-2 text-sm">{{ p.type }} · {{ p.status }}</p></Link
      >
    </div>
    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز URL همگام‌سازی نشده است"
      description="یک سایت متصل را Sync کنید تا URL Profileها در اینجا نمایش داده شوند."
  /></AppLayout>
</template>
