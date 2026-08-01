<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { ConversionRisk, Paginated } from '@/types/seo'
defineProps<{ risks: Paginated<ConversionRisk> }>()
</script>
<template>
  <Head title="ریسک‌های تبدیل" /><AppLayout
    ><VPageHeader
      title="ریسک‌های تبدیل"
      description="ریسک‌هایی که مانع تبدیل بازدیدکننده به مشتری می‌شوند."
    />
    <div class="mt-8 space-y-3">
      <div
        v-for="risk in risks.data"
        :key="risk.id"
        class="rounded-card border-line bg-surface border p-5"
      >
        <div class="flex justify-between">
          <span class="font-latin" dir="ltr">{{ risk.canonical_url }}</span
          ><VBadge :tone="risk.severity === 'high' ? 'danger' : 'warning'">{{
            risk.severity
          }}</VBadge>
        </div>
        <p class="mt-3">{{ risk.explanation }}</p>
      </div>
    </div></AppLayout
  >
</template>
