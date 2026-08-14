<script setup lang="ts">
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import type { AudienceWhyItem } from '@/types/audience'

withDefaults(
  defineProps<{
    eyebrow: string
    title: string
    items?: AudienceWhyItem[]
  }>(),
  {
    items: () => [],
  },
)
</script>

<template>
  <section class="border-y border-line bg-surface">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
      <div v-reveal>
        <SectionHeading :eyebrow="eyebrow" :title="title" />
      </div>
      <div class="mt-12 grid gap-5 md:grid-cols-3">
        <div
          v-for="(item, index) in items"
          :key="item.title"
          v-reveal="{ delay: index * 110 }"
          class="group rounded-2xl border border-line bg-canvas p-6 shadow-card transition-all duration-300 hover:-translate-y-1 hover:shadow-panel"
        >
          <span
            class="bg-gradient-brand inline-flex size-11 items-center justify-center rounded-xl text-white shadow-md transition-transform duration-300 group-hover:scale-110"
          >
            <component :is="item.icon" class="size-5" aria-hidden="true" />
          </span>
          <h3 class="text-ink-strong mt-5 text-lg font-bold">{{ item.title }}</h3>
          <p class="text-ink-muted mt-2.5 leading-7">{{ item.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
