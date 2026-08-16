<script setup lang="ts">
import { AlertTriangle, ArrowDown, CheckCircle2 } from '@lucide/vue'

import SectionHeading from '@/marketing/components/SectionHeading.vue'
import type { AudiencePain } from '@/types/audience'

withDefaults(
  defineProps<{
    pains?: AudiencePain[]
  }>(),
  {
    pains: () => [],
  },
)
</script>

<template>
  <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
    <div v-reveal>
      <SectionHeading
        eyebrow="PAIN POINTS"
        title="مشکلی که هر روز با آن روبه‌رو هستید."
        description="این‌ها چیزهایی است که شنیده‌ایم؛ سوئیت برای همین‌ها ساخته شده — نه برای یک داشبورد تزئینی."
      />
    </div>
    <div class="mt-12 grid gap-5 md:grid-cols-3">
      <div
        v-for="(pain, index) in pains"
        :key="pain.title"
        v-reveal="{ delay: index * 120 }"
        class="group rounded-2xl border border-line bg-surface p-6 shadow-card transition-all duration-300 hover:-translate-y-1 hover:shadow-panel"
      >
        <span
          class="inline-flex size-11 items-center justify-center rounded-xl bg-danger-50 text-danger-600"
        >
          <AlertTriangle class="size-5" aria-hidden="true" />
        </span>
        <h3 class="text-ink-strong mt-5 text-lg font-bold">{{ pain.title }}</h3>
        <p class="text-ink-muted mt-2.5 leading-7">{{ pain.text }}</p>

        <div class="text-ink-muted mt-5 flex items-center gap-1.5 text-xs font-bold">
          <ArrowDown class="size-3.5" aria-hidden="true" />
          راه‌حل سوئیت
        </div>
        <div class="mt-3 flex items-start gap-2.5 rounded-xl bg-success-50 p-3.5">
          <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-success-600" aria-hidden="true" />
          <p class="text-success-700 text-sm leading-6 font-semibold">{{ pain.solution }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
