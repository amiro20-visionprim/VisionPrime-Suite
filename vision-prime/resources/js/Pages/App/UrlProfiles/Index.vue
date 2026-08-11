<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface ProfileRow {
  id: number
  url: string
  type: string
  status: string
  lastSyncedAt: string | null
  gsc: { clicks: number; impressions: number; ctr: number; position: number } | null
  auditId: number | null
}

defineProps<{ profiles: { data: ProfileRow[] } }>()

const typeLabels: Record<string, string> = {
  post: 'نوشته',
  page: 'صفحه',
  product: 'محصول',
  product_variation: 'نسخهٔ محصول',
}

const statusLabels: Record<string, string> = {
  publish: 'منتشرشده',
  draft: 'پیش‌نویس',
  private: 'خصوصی',
  pending: 'در انتظار',
  trash: 'حذف‌شده',
}

const statusTone: Record<string, 'success' | 'info' | 'warning' | 'neutral' | 'danger'> = {
  publish: 'success',
  draft: 'warning',
  pending: 'info',
  private: 'neutral',
  trash: 'danger',
}
</script>
<template>
  <Head title="URLها و محتوا" /><AppLayout
    ><VPageHeader
      title="URLها و محتوا"
      description="پروفایل‌های همگام‌سازی‌شده، دادهٔ جستجو و تاریخچه محتوای سایت‌ها."
    />
    <div v-if="profiles.data.length" class="mt-8 space-y-3">
      <Link
        v-for="p in profiles.data"
        :key="p.id"
        :href="`/app/url-profiles/${p.id}`"
        class="rounded-card border-line bg-surface block border p-5 transition-colors hover:border-brand-300"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="font-latin text-brand-700 text-sm font-semibold break-all" dir="ltr">
            {{ p.url }}
          </p>
          <div class="flex items-center gap-2">
            <VBadge tone="info">{{ typeLabels[p.type] ?? p.type }}</VBadge>
            <VBadge :tone="statusTone[p.status] ?? 'neutral'">
              {{ statusLabels[p.status] ?? p.status }}
            </VBadge>
            <VBadge v-if="p.auditId" tone="warning">صفحهٔ درآمدزا</VBadge>
          </div>
        </div>
        <div v-if="p.gsc" class="font-latin text-ink-muted mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs" dir="ltr">
          <span>{{ p.gsc.clicks }} clicks</span>
          <span>{{ p.gsc.impressions }} impressions</span>
          <span>pos {{ p.gsc.position }}</span>
        </div>
        <p v-else class="text-ink-muted mt-2 text-xs">دادهٔ جستجو: در دسترس نیست</p>
      </Link>
    </div>
    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز URL همگام‌سازی نشده است"
      description="یک سایت متصل را Sync کنید تا URL Profileها در اینجا نمایش داده شوند."
  /></AppLayout>
</template>
