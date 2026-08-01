<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Opportunity, Paginated } from '@/types/seo'
defineProps<{ opportunities: Paginated<Opportunity> }>()
</script>
<template>
  <Head title="فرصت‌های رشد" /><AppLayout
    ><VPageHeader
      title="فرصت‌های رشد"
      description="فرصت‌های رتبه‌بندی‌شده بر اساس داده GSC و پتانسیل بهبود."
    />
    <div class="mt-8 space-y-3">
      <Link
        v-for="item in opportunities.data"
        :key="item.id"
        :href="`/app/opportunities/${item.id}`"
        class="rounded-card border-line bg-surface block border p-5"
        ><div class="flex justify-between">
          <p class="font-semibold">{{ item.query_normalized || 'فرصت بدون Query' }}</p>
          <VBadge tone="warning">{{ item.score }}</VBadge>
        </div>
        <p class="font-latin text-ink-muted mt-2 text-sm" dir="ltr">{{ item.canonical_url }}</p>
        <p class="mt-2 text-sm">{{ item.type }}</p></Link
      >
    </div></AppLayout
  >
</template>
