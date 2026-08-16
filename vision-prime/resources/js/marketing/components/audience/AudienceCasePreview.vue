<script setup lang="ts">
import { CheckCircle2, TrendingUp } from '@lucide/vue'

import SectionHeading from '@/marketing/components/SectionHeading.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import type { AudienceCasePoint } from '@/types/audience'

withDefaults(
  defineProps<{
    title: string
    description: string
    points: AudienceCasePoint[]
    delta?: string
    narrativeTitle?: string
    narrative?: string
  }>(),
  {
    delta: '+۱۸٪',
    narrativeTitle: 'هر عدد، یک تصمیم است؛ نه یک گزارش برای قفسه.',
    narrative:
      'سوئیت همان دادهٔ سرچ کنسول شما را به فرصت‌های رتبه‌بندی‌شده تبدیل می‌کند؛ هر فرصت با ارزش کسب‌وکاری، شکاف CTR و وضعیت صفحه همراه است.',
  },
)
</script>

<template>
  <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
    <div v-reveal>
      <SectionHeading
        eyebrow="REAL DATA EXAMPLE"
        :title="title"
        :description="description"
      />
    </div>
    <div class="mt-12 grid items-center gap-8 lg:grid-cols-2">
      <!-- mock result panel -->
      <div
        v-reveal="{ delay: 120 }"
        class="relative mx-auto w-full max-w-lg"
      >
        <div
          aria-hidden="true"
          class="pointer-events-none absolute -inset-5 rounded-[2rem] bg-gradient-to-br from-brand-500/20 via-indigo-500/15 to-violet-500/20 blur-2xl"
        />
        <div class="shadow-panel relative overflow-hidden rounded-2xl border border-line bg-surface">
          <div class="border-line flex items-center justify-between border-b px-5 py-4">
            <div>
              <p class="text-ink-strong text-sm font-bold">نمونهٔ واقعی از دادهٔ GSC</p>
              <p class="text-ink-muted mt-0.5 text-xs">سایت نمونه — برای نمایش جریان کار</p>
            </div>
            <VBadge tone="success">
              <TrendingUp class="size-3.5" aria-hidden="true" />
              {{ delta }}
            </VBadge>
          </div>
          <div class="space-y-3 p-5">
            <div
              v-for="(point, index) in points"
              :key="point.label"
              class="flex items-center justify-between rounded-xl border border-line bg-canvas px-4 py-3"
            >
              <div class="flex items-center gap-3">
                <span
                  class="flex size-8 items-center justify-center rounded-lg"
                  :class="index % 2 === 0 ? 'bg-brand-50 text-brand-700' : 'bg-success-50 text-success-600'"
                >
                  <CheckCircle2 class="size-4" aria-hidden="true" />
                </span>
                <div>
                  <p class="text-ink-strong text-sm font-bold">{{ point.label }}</p>
                  <p v-if="point.hint" class="text-ink-muted mt-0.5 text-xs">{{ point.hint }}</p>
                </div>
              </div>
              <span class="text-gradient-brand font-display text-xl font-bold">{{ point.value }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- narrative -->
      <div v-reveal="{ delay: 200 }">
        <h3 class="text-ink-strong text-2xl leading-relaxed font-bold sm:text-3xl">
          {{ narrativeTitle }}
        </h3>
        <p class="text-ink-muted mt-4 text-base leading-8">
          {{ narrative }}
        </p>
        <ul class="mt-6 space-y-3">
          <li
            v-for="point in points.slice(0, 3)"
            :key="point.label"
            class="flex items-start gap-3"
          >
            <CheckCircle2 class="mt-1 size-5 shrink-0 text-success-600" aria-hidden="true" />
            <span class="text-ink leading-7">{{ point.hint || point.label }}</span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
