<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Opportunity, OpportunityFactor } from '@/types/seo'
defineProps<{ opportunity: Opportunity; factors: OpportunityFactor[] }>()
</script>
<template>
  <Head title="Opportunity Detail" /><AppLayout
    ><VPageHeader
      title="جزئیات فرصت رشد"
      :description="opportunity.explanation"
      :breadcrumbs="[{ label: 'فرصت‌های رشد', href: '/app/opportunities' }, { label: 'جزئیات' }]"
    /><VCard class="mt-8" title="امتیاز"
      ><p class="text-3xl font-bold">{{ opportunity.score }}</p>
      <p class="text-ink-muted mt-2">Confidence: {{ opportunity.confidence }}</p></VCard
    ><VCard class="mt-6" title="Factor Breakdown"
      ><div v-for="factor in factors" :key="factor.id" class="border-line border-b py-4">
        <div class="flex justify-between">
          <span>{{ factor.key }}</span
          ><span>{{ factor.weight }}</span>
        </div>
        <p class="text-ink-muted mt-2 text-sm">{{ factor.explanation }}</p>
      </div></VCard
    ></AppLayout
  >
</template>
