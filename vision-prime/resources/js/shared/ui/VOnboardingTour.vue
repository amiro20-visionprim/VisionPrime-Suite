<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import VIcon, { type IconName } from './VIcon.vue'

export interface TourStep {
  icon: IconName
  title: string
  body: string
  cta?: { label: string; href: string }
}

const props = withDefaults(
  defineProps<{
    storageKey?: string
    steps?: TourStep[]
  }>(),
  {
    storageKey: 'vp-client-onboarding-done',
    steps: () => [
      {
        icon: 'sparkles',
        title: 'به داشبورد خود خوش آمدید',
        body: 'اینجا همه‌چیز سایت شما در یک نگاه است: رشد، اولویت‌ها و کارهایی که منتظر تصمیم شماست.',
      },
      {
        icon: 'trend-up',
        title: 'این اعداد یعنی چه؟',
        body: 'هر بخش یک دکمهٔ 💡 کنار خودش دارد؛ روی آن بزنید تا توضیح ساده و غیرتخصصی ببینید.',
      },
      {
        icon: 'user-check',
        title: 'منتظر تصمیم شما',
        body: 'اگر پیشنهادی برای تأیید دارید، از بخش «تأییدهای من» اقدام کنید. بدون تأیید شما، هیچ تغییری روی سایت اعمال نمی‌شود.',
      },
      {
        icon: 'graduation',
        title: 'هر وقت سؤال داشتید',
        body: 'دکمهٔ «راهنما و پشتیبانی» بالای صفحه همیشه در دسترس است؛ و در «مرکز آموزش» همهٔ امکانات سوئیت را قدم‌به‌قدم یاد می‌گیرید.',
        cta: { label: 'بازدید از مرکز آموزش', href: '/client/training' },
      },
    ],
  },
)

const steps = props.steps ?? []

const visible = ref(false)
const step = ref(0)

const currentStep = computed(() => steps[step.value] ?? steps[0])

onMounted(() => {
  try {
    if (!localStorage.getItem(props.storageKey)) {
      visible.value = true
    }
  } catch {
    visible.value = true
  }
})

function next(): void {
  if (step.value < steps.length - 1) {
    step.value += 1
  } else {
    finish()
  }
}

function finish(): void {
  try {
    localStorage.setItem(props.storageKey, '1')
  } catch {
    // ignore
  }
  visible.value = false
}

function skip(): void {
  finish()
}
</script>

<template>
  <section
    v-if="visible"
    class="rounded-panel bg-gradient-brand text-white relative overflow-hidden p-6 shadow-card sm:p-8"
    role="region"
    aria-label="راهنمای شروع کار"
  >
    <div class="relative z-10 flex flex-wrap items-start justify-between gap-6">
      <div class="flex min-w-0 items-start gap-4">
        <span class="bg-white/15 flex size-12 shrink-0 items-center justify-center rounded-xl">
          <VIcon :name="currentStep.icon" size="xl" />
        </span>
        <div class="min-w-0 max-w-xl">
          <p class="text-white/80 text-xs font-bold">قدم {{ step + 1 }} از {{ steps.length }}</p>
          <h2 class="font-display mt-1 text-lg font-bold">{{ currentStep.title }}</h2>
          <p class="mt-2 text-sm leading-7 text-white/90">{{ currentStep.body }}</p>
        </div>
      </div>
      <div class="flex shrink-0 items-center gap-3">
        <button
          type="button"
          class="text-white/80 hover:text-white text-sm font-semibold"
          @click="skip"
        >
          رد کردن
        </button>
        <button
          type="button"
          class="rounded-ui bg-white px-5 py-2 text-sm font-bold text-brand-700 transition-transform hover:scale-105"
          @click="next"
        >
          {{ step === steps.length - 1 ? 'شروع میکنم ✨' : 'بعدی' }}
        </button>
      </div>
    </div>

    <div class="relative z-10 mt-5 flex flex-wrap items-center justify-between gap-3">
      <div class="flex max-w-xs gap-1.5">
        <span
          v-for="(s, i) in steps"
          :key="i"
          class="h-1 flex-1 rounded-full transition-all"
          :class="i <= step ? 'bg-white' : 'bg-white/30'"
        />
      </div>
      <a
        v-if="currentStep.cta"
        :href="currentStep.cta.href"
        class="bg-white/15 hover:bg-white/25 rounded-ui inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold text-white transition-colors"
      >
        <VIcon name="arrow-up" size="sm" class="rotate-45" />
        {{ currentStep.cta.label }}
      </a>
    </div>
  </section>
</template>
