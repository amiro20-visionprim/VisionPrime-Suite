<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import VIcon, { type IconName, type IconTone } from './VIcon.vue'
import VGuideTip from './VGuideTip.vue'

export type StatTrend = 'up' | 'down' | 'flat'

const props = withDefaults(
  defineProps<{
    label: string
    value: number
    icon?: IconName
    iconTone?: IconTone
    hint?: string
    trend?: StatTrend
    trendLabel?: string
    format?: 'number' | 'percent'
    duration?: number
  }>(),
  {
    icon: 'trend-up',
    iconTone: 'brand',
    hint: '',
    trend: 'flat',
    trendLabel: '',
    format: 'number',
    duration: 1200,
  },
)

const root = ref<HTMLElement | null>(null)
const display = ref(0)
let rafId = 0
let started = false

const faNum = (value: number): string => {
  if (props.format === 'percent') {
    return `${new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 1 }).format(value)}٪`
  }
  return new Intl.NumberFormat('fa-IR').format(Math.round(value))
}

function animate(): void {
  if (started) {
    return
  }
  started = true
  const start = performance.now()

  const tick = (now: number): void => {
    const progress = Math.min(1, (now - start) / props.duration)
    const eased = 1 - Math.pow(1 - progress, 3)
    display.value = props.value * eased
    if (progress < 1) {
      rafId = requestAnimationFrame(tick)
    }
  }
  rafId = requestAnimationFrame(tick)
}

function startIfVisible(): void {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    display.value = props.value
    return
  }
  const el = root.value
  if (!el) {
    display.value = props.value
    return
  }
  const observer = new IntersectionObserver(
    (entries) => {
      const entry = entries[0]
      if (!entry || !entry.isIntersecting) {
        return
      }
      observer.disconnect()
      animate()
    },
    { threshold: 0.3 },
  )
  observer.observe(el)
}

watch(
  () => props.value,
  () => {
    started = false
    display.value = 0
    startIfVisible()
  },
)

onMounted(startIfVisible)
onBeforeUnmount(() => cancelAnimationFrame(rafId))

const iconBg: Record<IconTone, string> = {
  brand: 'bg-brand-50',
  success: 'bg-success-50',
  warning: 'bg-warning-50',
  danger: 'bg-danger-50',
  neutral: 'bg-surface-muted',
  violet: 'bg-violet-50',
}

const trendTone = {
  up: { cls: 'bg-success-50 text-success-600', label: '▲' },
  down: { cls: 'bg-danger-50 text-danger-600', label: '▼' },
  flat: { cls: 'bg-surface-muted text-ink-muted', label: '•' },
} as const
</script>

<template>
  <article
    ref="root"
    class="rounded-card border-line bg-surface shadow-card border p-5 transition-transform duration-200 ease-out hover:-translate-y-0.5"
  >
    <div class="flex items-start justify-between gap-3">
      <div :class="['rounded-ui flex size-10 items-center justify-center', iconBg[iconTone]]">
        <VIcon :name="icon" :tone="iconTone" size="lg" />
      </div>
      <VGuideTip v-if="hint" :text="hint" />
    </div>

    <div class="font-display text-ink-strong mt-4 text-3xl leading-none font-extrabold tracking-tight">
      {{ faNum(display) }}
    </div>
    <p class="text-ink-muted mt-1.5 text-sm">{{ label }}</p>

    <div class="mt-3 flex items-center gap-2">
      <span
        v-if="trend !== 'flat'"
        :class="[
          'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold',
          trendTone[trend].cls,
        ]"
      >
        {{ trendTone[trend].label }} {{ trendLabel || (trend === 'up' ? 'رو به رشد' : 'کاهش') }}
      </span>
      <span v-else class="text-ink-muted text-xs">{{ trendLabel || 'بدون تغییر' }}</span>
    </div>

    <slot />
  </article>
</template>
