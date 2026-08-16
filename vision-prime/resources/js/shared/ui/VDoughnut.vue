<script setup lang="ts">
import { computed } from 'vue'

export interface DoughnutDatum {
  label: string
  value: number
  color: string
}

const props = withDefaults(
  defineProps<{
    data: DoughnutDatum[]
    size?: number
    thickness?: number
    /** متن مرکز (مثلاً جمع) */
    centerLabel?: string
    centerValue?: string
  }>(),
  {
    size: 160,
    thickness: 22,
    centerLabel: '',
    centerValue: '',
  },
)

const total = computed(() => props.data.reduce((s, d) => s + d.value, 0))

const C = 2 * Math.PI * (props.size / 2 - props.thickness / 2)

/** شروع هر قطعه بر حسب درجهٔ دایره. */
const segments = computed(() => {
  let acc = 0
  return props.data.map((d) => {
    const start = acc
    const frac = total.value > 0 ? d.value / total.value : 0
    acc += frac * 360
    return { ...d, start, end: acc, frac }
  })
})

const dash = (seg: { start: number; end: number; frac: number }) => {
  const len = Math.max(0, seg.frac * C)
  const gap = C - len
  const offset = -((seg.start / 360) * C)
  return {
    'stroke-dasharray': `${len.toFixed(2)} ${gap.toFixed(2)}`,
    'stroke-dashoffset': offset.toFixed(2),
  }
}

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)
</script>

<template>
  <div class="flex flex-wrap items-center gap-6">
    <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
      <svg :viewBox="`0 0 ${size} ${size}`" class="size-full -rotate-90" role="img" aria-label="نمودار دونات">
        <circle
          :cx="size / 2"
          :cy="size / 2"
          :r="size / 2 - thickness / 2"
          fill="none"
          stroke="#e6eef8"
          :stroke-width="thickness"
        />
        <circle
          v-for="(seg, i) in segments"
          :key="i"
          :cx="size / 2"
          :cy="size / 2"
          :r="size / 2 - thickness / 2"
          fill="none"
          :stroke="seg.color"
          :stroke-width="thickness"
          stroke-linecap="round"
          v-bind="dash(seg)"
          :style="{ animation: `vp-doughnut-in 0.9s ${i * 120}ms ease-out both` }"
        />
      </svg>
      <div class="absolute inset-0 flex flex-col items-center justify-center">
        <span v-if="centerValue" class="text-ink-strong text-xl font-extrabold">{{ centerValue }}</span>
        <span v-if="centerLabel" class="text-ink-muted text-xs">{{ centerLabel }}</span>
      </div>
    </div>

    <ul class="min-w-40 space-y-2">
      <li v-for="(d, i) in data" :key="i" class="flex items-center gap-2 text-sm">
        <span class="size-2.5 shrink-0 rounded-full" :style="{ background: d.color }" />
        <span class="text-ink">{{ d.label }}</span>
        <span class="text-ink-strong ms-auto font-bold">{{ faNum(d.value) }}</span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
@keyframes vp-doughnut-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>
