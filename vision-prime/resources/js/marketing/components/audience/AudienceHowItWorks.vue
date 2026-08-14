<script setup lang="ts">
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import type { AudienceStep } from '@/types/audience'

withDefaults(
  defineProps<{
    title?: string
    description?: string
    steps?: AudienceStep[]
  }>(),
  {
    title: 'در سه گام شروع کنید.',
    description: 'بدون تغییر زیرساخت شما؛ پلاگین نصب می‌شود و بقیهٔ مسیر را ما مدیریت می‌کنیم.',
    steps: () => [],
  },
)
</script>

<template>
  <section class="border-y border-line bg-surface">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
      <div v-reveal>
        <SectionHeading eyebrow="HOW TO START" :title="title" :description="description" centered />
      </div>
      <div class="mt-12 grid gap-5 md:grid-cols-3">
        <div
          v-for="(step, index) in steps"
          :key="step.title"
          v-reveal="{ delay: index * 110 }"
          class="rounded-2xl border border-line bg-canvas p-6 shadow-card transition-all duration-300 hover:-translate-y-1 hover:shadow-panel"
        >
          <span
            class="bg-gradient-brand flex size-11 items-center justify-center rounded-xl text-lg font-bold text-white shadow-md"
            >{{ index + 1 }}</span
          >
          <h3 class="text-ink-strong mt-5 text-lg font-bold">{{ step.title }}</h3>
          <p class="text-ink-muted mt-2.5 leading-7">{{ step.text }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
