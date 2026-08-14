<script setup lang="ts">
import { ArrowUpRight, Sparkles } from '@lucide/vue'

import AnimatedNumber from '@/marketing/components/AnimatedNumber.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import type { AudienceStat } from '@/types/audience'

withDefaults(
  defineProps<{
    badge: string
    titleBefore: string
    gradientWord: string
    description: string
    stats: AudienceStat[]
    ctaLabel: string
    ctaHref: string
    secondaryHref?: string
  }>(),
  {
    secondaryHref: '/pricing',
  },
)

const emit = defineEmits<{
  'cta-click': []
}>()

function handleCta(): void {
  emit('cta-click')
}
</script>

<template>
  <section class="relative overflow-hidden border-b border-line bg-canvas">
    <!-- ambient background -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
      <div class="bg-radial-fade bg-hero-grid absolute inset-0" />
      <div class="absolute -top-32 right-[-10%] size-[520px] rounded-full bg-brand-200/50 blur-3xl" />
      <div class="absolute top-24 left-[-12%] size-[480px] rounded-full bg-violet-200/40 blur-3xl" />
    </div>

    <div
      v-reveal
      class="relative mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10 lg:py-24"
    >
      <div class="max-w-3xl">
        <VBadge tone="info">
          <Sparkles class="size-3.5" aria-hidden="true" />
          {{ badge }}
        </VBadge>
        <h1
          class="font-display text-ink-strong mt-5 max-w-3xl text-4xl leading-[1.55] font-bold sm:text-5xl sm:leading-[1.5]"
        >
          {{ titleBefore }}
          <span class="text-gradient-brand">{{ gradientWord }}</span>
        </h1>
        <p class="text-ink-muted mt-5 max-w-2xl text-base leading-8 sm:text-lg">
          {{ description }}
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
          <VButton :href="ctaHref" size="lg" variant="gradient" @click="handleCta">
            {{ ctaLabel }}
            <ArrowUpRight class="size-4" aria-hidden="true" />
          </VButton>
          <VButton :href="secondaryHref" size="lg" variant="secondary">مشاهدهٔ قیمت‌گذاری</VButton>
        </div>
      </div>

      <!-- stats strip -->
      <div
        v-if="stats.length"
        class="mt-12 grid grid-cols-2 gap-6 rounded-2xl border border-line bg-surface/80 p-6 shadow-card backdrop-blur sm:grid-cols-3 lg:grid-cols-4 lg:px-10"
      >
        <div
          v-for="(stat, index) in stats"
          :key="stat.label"
          v-reveal="{ delay: index * 100 }"
          class="text-center"
        >
          <p class="font-display text-gradient-brand text-3xl font-bold sm:text-4xl">
            <AnimatedNumber :to="stat.to" />
            <span v-if="stat.suffix">{{ stat.suffix }}</span>
          </p>
          <p class="text-ink-muted mt-1.5 text-sm font-semibold">{{ stat.label }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
