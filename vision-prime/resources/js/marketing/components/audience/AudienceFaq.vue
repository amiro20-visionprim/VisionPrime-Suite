<script setup lang="ts">
import { ref } from 'vue'

import SectionHeading from '@/marketing/components/SectionHeading.vue'
import type { AudienceFaq } from '@/types/audience'

withDefaults(
  defineProps<{
    faqs?: AudienceFaq[]
  }>(),
  {
    faqs: () => [],
  },
)

const openIndex = ref<number | null>(0)

function toggle(index: number): void {
  openIndex.value = openIndex.value === index ? null : index
}
</script>

<template>
  <section class="mx-auto max-w-4xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
    <div v-reveal>
      <SectionHeading
        eyebrow="FAQ"
        title="سؤال‌هایی که معمولاً می‌پرسند."
        centered
      />
    </div>
    <div class="mt-10 space-y-3">
      <div
        v-for="(faq, index) in faqs"
        :key="faq.q"
        v-reveal="{ delay: index * 70 }"
        class="rounded-xl border border-line bg-surface shadow-card"
      >
        <button
          type="button"
          class="text-ink-strong flex w-full items-center justify-between gap-4 px-5 py-4 text-right text-base font-bold"
          :aria-expanded="openIndex === index"
          @click="toggle(index)"
        >
          {{ faq.q }}
          <span
            class="text-brand-700 shrink-0 text-xl leading-none transition-transform duration-300"
            :class="openIndex === index ? 'rotate-45' : ''"
            aria-hidden="true"
            >+</span
          >
        </button>
        <div
          v-show="openIndex === index"
          class="border-line border-t px-5 py-4"
        >
          <p class="text-ink-muted leading-7">{{ faq.a }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
