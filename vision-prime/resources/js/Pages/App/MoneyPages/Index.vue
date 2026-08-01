<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { MoneyPageAudit, Paginated } from '@/types/seo'
defineProps<{ audits: Paginated<MoneyPageAudit> }>()
</script>
<template>
  <Head title="صفحات درآمدزا" /><AppLayout
    ><VPageHeader
      title="صفحات درآمدزا"
      description="صفحه‌های تجاری با بیشترین ظرفیت اثر SEO و Conversion."
    />
    <div class="mt-8 space-y-3">
      <div
        v-for="audit in audits.data"
        :key="audit.id"
        class="rounded-card border-line bg-surface border p-5"
      >
        <div class="flex justify-between">
          <span class="font-latin" dir="ltr">{{ audit.canonical_url }}</span
          ><VBadge tone="warning">{{ audit.score }}</VBadge>
        </div>
      </div>
    </div></AppLayout
  >
</template>
