<script setup lang="ts">
import SectionHeading from '@/marketing/components/SectionHeading.vue'
import type { AudienceStep } from '@/types/audience'

withDefaults(
  defineProps<{
    steps?: AudienceStep[]
  }>(),
  {
    steps: () => [],
  },
)
</script>

<template>
  <section class="border-y border-line bg-surface">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
      <div v-reveal>
        <SectionHeading
          eyebrow="HOW IT WORKS"
          title="از اتصال تا اقدام در چند گام."
          description="بدون تغییر زیرساخت شما؛ پلاگین روی وردپرس‌تان نصب می‌شود و بقیهٔ مسیر را ما مدیریت می‌کنیم."
        />
      </div>
      <ol class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <li
          v-for="(step, index) in steps"
          :key="step.title"
          v-reveal="{ delay: index * 110 }"
          class="relative rounded-2xl border border-line bg-canvas p-6"
        >
          <span
            class="bg-gradient-brand inline-flex size-10 items-center justify-center rounded-full text-sm font-bold text-white shadow-md"
            >{{ index + 1 }}</span
          >
          <h3 class="text-ink-strong mt-4 text-base font-bold">{{ step.title }}</h3>
          <p class="text-ink-muted mt-2 text-sm leading-6">{{ step.text }}</p>
          <span
            v-if="index < steps.length - 1"
            aria-hidden="true"
            class="text-brand-200 absolute top-9 -left-4 hidden text-xl lg:block"
            >←</span
          >
        </li>
      </ol>
    </div>
  </section>
</template>
