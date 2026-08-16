<script setup lang="ts">
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import type { AudienceMatrixRow } from '@/types/audience'

withDefaults(
  defineProps<{
    title?: string
    description?: string
    rows?: AudienceMatrixRow[]
  }>(),
  {
    title: 'چرا سوئیت، نه یک سئوکار تمام‌وقت یا ابزار خارجی؟',
    description:
      'مقایسه بر اساس سناریوهای رایج در همین حوزه — صادقانه، نه با وعده‌های تبلیغاتی.',
    rows: () => [],
  },
)
</script>

<template>
  <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
    <div v-reveal>
      <SectionHeading eyebrow="COMPARISON" :title="title" :description="description" />
    </div>
    <div v-reveal class="mt-10 overflow-x-auto">
      <table class="w-full min-w-[760px] border-collapse text-sm">
        <thead>
          <tr class="border-line border-b">
            <th class="text-ink-muted px-4 py-3 text-start font-medium">سناریو و دغدغه</th>
            <th
              class="bg-gradient-brand text-white rounded-t-xl px-4 py-3 text-center font-bold"
            >
              سوئیت
            </th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">سئوکار تمام‌وقت</th>
            <th class="text-ink-muted px-4 py-3 text-center font-medium">ابزارهای SaaS خارجی</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.concern"
            class="border-line border-b align-top last:border-0"
          >
            <td class="px-4 py-4">
              <p class="text-ink-strong font-bold">{{ row.concern }}</p>
              <p class="text-ink-muted mt-1 text-xs leading-5">{{ row.scenario }}</p>
            </td>
            <td class="bg-brand-50/70 px-4 py-4 text-center">
              <p class="text-brand-700 font-semibold leading-6">{{ row.vision }}</p>
            </td>
            <td class="text-ink-muted px-4 py-4 text-center leading-6">{{ row.inhouse }}</td>
            <td class="text-ink-muted px-4 py-4 text-center leading-6">{{ row.saas }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
