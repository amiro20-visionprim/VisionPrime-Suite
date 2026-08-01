<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
defineProps<{
  profile: {
    id: number
    url: string
    metadata: Record<string, string | null>
    snapshots: {
      hash: string
      title: string | null
      wordCount: number
      capturedAt: string | null
    }[]
  }
}>()
</script>
<template>
  <Head title="URL Profile" /><AppLayout
    ><VPageHeader
      title="URL Profile"
      :description="profile.url"
      :breadcrumbs="[{ label: 'URLها و محتوا', href: '/app/url-profiles' }, { label: 'جزئیات' }]"
    /><VCard class="mt-8" title="Metadata"
      ><dl class="divide-line divide-y">
        <div class="flex justify-between py-3">
          <dt>Meta Title</dt>
          <dd>{{ profile.metadata?.meta_title || '—' }}</dd>
        </div>
        <div class="flex justify-between py-3">
          <dt>Meta Description</dt>
          <dd>{{ profile.metadata?.meta_description || '—' }}</dd>
        </div>
      </dl></VCard
    ><VCard class="mt-6" title="تاریخچه محتوا"
      ><div v-for="s in profile.snapshots" :key="s.hash" class="border-line border-b py-4">
        <p class="font-semibold">{{ s.title || 'بدون عنوان' }}</p>
        <p class="font-latin text-ink-muted mt-1 text-xs" dir="ltr">{{ s.hash }}</p>
        <p class="text-ink-muted mt-1 text-sm">{{ s.wordCount }} کلمه</p>
      </div>
      <p v-if="!profile.snapshots.length" class="text-ink-muted">Snapshotی وجود ندارد.</p></VCard
    ></AppLayout
  >
</template>
