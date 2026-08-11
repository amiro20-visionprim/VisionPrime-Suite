<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

defineProps<{
  profile: {
    id: number
    url: string
    type: string
    status: string
    metadata: Record<string, string | null>
    gsc: { clicks: number; impressions: number; ctr: number; position: number } | null
    auditId: number | null
    snapshots: {
      hash: string
      title: string | null
      wordCount: number
      capturedAt: string | null
    }[]
  }
}>()

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

function percent(value: number): string {
  return `${(value * 100).toFixed(1)}٪`
}
</script>
<template>
  <Head title="پروفایل URL" /><AppLayout
    ><VPageHeader
      title="پروفایل URL"
      :description="profile.url"
      :breadcrumbs="[{ label: 'URLها و محتوا', href: '/app/url-profiles' }, { label: 'جزئیات' }]"
    >
      <template #actions>
        <VButton
          v-if="profile.auditId"
          variant="secondary"
          :href="`/app/money-pages/${profile.auditId}`"
        >
          صفحهٔ درآمدزا
        </VButton>
      </template>
    </VPageHeader>

    <div class="mt-8 space-y-6">
      <VCard title="مشخصات">
        <div class="flex flex-wrap items-center gap-2">
          <VBadge tone="info">{{ typeLabels[profile.type] ?? profile.type }}</VBadge>
          <VBadge :tone="profile.status === 'publish' ? 'success' : 'warning'">
            {{ statusLabels[profile.status] ?? profile.status }}
          </VBadge>
        </div>
      </VCard>

      <VCard v-if="profile.gsc" title="دادهٔ جستجو (Search Console)">
        <div class="grid gap-4 sm:grid-cols-4">
          <div>
            <p class="text-ink-muted text-sm">کلیک‌ها</p>
            <p class="text-ink-strong mt-1 text-lg font-semibold">{{ profile.gsc.clicks }}</p>
          </div>
          <div>
            <p class="text-ink-muted text-sm">نمایش‌ها</p>
            <p class="text-ink-strong mt-1 text-lg font-semibold">{{ profile.gsc.impressions }}</p>
          </div>
          <div>
            <p class="text-ink-muted text-sm">نرخ کلیک</p>
            <p class="text-ink-strong mt-1 text-lg font-semibold">{{ percent(profile.gsc.ctr) }}</p>
          </div>
          <div>
            <p class="text-ink-muted text-sm">میانگین جایگاه</p>
            <p class="text-ink-strong mt-1 text-lg font-semibold">{{ profile.gsc.position }}</p>
          </div>
        </div>
      </VCard>

      <VCard title="فراداده"
        ><dl class="divide-line divide-y">
          <div class="flex justify-between gap-4 py-3">
            <dt>عنوان متا</dt>
            <dd>{{ profile.metadata?.meta_title || '—' }}</dd>
          </div>
          <div class="flex justify-between gap-4 py-3">
            <dt>توضیح متا</dt>
            <dd>{{ profile.metadata?.meta_description || '—' }}</dd>
          </div>
        </dl></VCard
      >
      <VCard title="تاریخچه محتوا"
        ><div v-for="s in profile.snapshots" :key="s.hash" class="border-line border-b py-4">
          <p class="font-semibold">{{ s.title || 'بدون عنوان' }}</p>
          <p class="font-latin text-ink-muted mt-1 text-xs" dir="ltr">{{ s.hash }}</p>
          <p class="text-ink-muted mt-1 text-sm">{{ s.wordCount }} کلمه</p>
        </div>
        <p v-if="!profile.snapshots.length" class="text-ink-muted">عکسی از محتوا ثبت نشده است.</p></VCard
      >
    </div></AppLayout
  >
</template>
