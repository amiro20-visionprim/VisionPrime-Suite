<script setup lang="ts">
import { computed, ref } from 'vue'

export interface AreaPoint {
  label: string
  value: number
}

const props = withDefaults(
  defineProps<{
    points: AreaPoint[]
    height?: number
    unit?: string
    ariaLabel?: string
  }>(),
  {
    height: 200,
    unit: '',
    ariaLabel: 'نمودار روند',
  },
)

const W = 720
const H = 220
const PAD = { top: 18, right: 12, bottom: 26, left: 34 }

const max = computed(() => Math.max(1, ...props.points.map((p) => p.value)))

const x = (i: number) =>
  PAD.left + (i / Math.max(1, props.points.length - 1)) * (W - PAD.left - PAD.right)
const y = (v: number) => PAD.top + (1 - v / max.value) * (H - PAD.top - PAD.bottom)

const linePath = computed(() =>
  props.points
    .map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(p.value).toFixed(1)}`)
    .join(' '),
)

const areaPath = computed(() => {
  if (!props.points.length) {
    return ''
  }
  const last = props.points.length - 1
  return `${linePath.value} L ${x(last).toFixed(1)} ${(H - PAD.bottom).toFixed(1)} L ${PAD.left.toFixed(1)} ${(H - PAD.bottom).toFixed(1)} Z`
})

const labelEvery = computed(() => Math.max(1, Math.ceil(props.points.length / 7)))

const xLabels = computed(() =>
  props.points.map((p, i) => ({ i, label: p.label })).filter((_, i) => i % labelEvery.value === 0),
)

const hovered = ref<number | null>(null)

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)
</script>

<template>
  <div dir="ltr" class="w-full overflow-x-auto" @mouseleave="hovered = null">
    <svg
      :viewBox="`0 0 ${W} ${H}`"
      class="min-w-[480px] w-full"
      role="img"
      :aria-label="ariaLabel"
    >
      <defs>
        <linearGradient id="vp-area-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#4f46e5" stop-opacity="0.28" />
          <stop offset="100%" stop-color="#4f46e5" stop-opacity="0.02" />
        </linearGradient>
        <linearGradient id="vp-area-line" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stop-color="#245c9b" />
          <stop offset="60%" stop-color="#4f46e5" />
          <stop offset="100%" stop-color="#7c3aed" />
        </linearGradient>
      </defs>

      <!-- خطوط راهنما -->
      <line
        v-for="n in 3"
        :key="`g${n}`"
        :x1="PAD.left"
        :x2="W - PAD.right"
        :y1="PAD.top + (n * (H - PAD.top - PAD.bottom)) / 3"
        :y2="PAD.top + (n * (H - PAD.top - PAD.bottom)) / 3"
        stroke="#e6eef8"
        stroke-width="1"
      />

      <!-- ناحیهٔ گرادیانی -->
      <path
        v-if="areaPath"
        :d="areaPath"
        fill="url(#vp-area-fill)"
        style="animation: vp-area-in 1s ease-out both"
      />

      <!-- خط -->
      <path
        v-if="linePath"
        :d="linePath"
        fill="none"
        stroke="url(#vp-area-line)"
        stroke-width="2.5"
        stroke-linecap="round"
        style="animation: vp-line-in 1.1s ease-out both"
      />

      <!-- نقطه‌ها -->
      <g v-for="(p, i) in points" :key="i">
        <circle :cx="x(i)" :cy="y(p.value)" r="3" fill="#fff" stroke="#4f46e5" stroke-width="2" />
        <circle
          v-if="hovered === i"
          :cx="x(i)"
          :cy="y(p.value)"
          r="5"
          fill="#4f46e5"
          opacity="0.25"
        />
        <!-- ناحیهٔ hover شفاف -->
        <rect
          :x="x(i) - (W - PAD.left - PAD.right) / Math.max(1, points.length) / 2"
          :y="0"
          :width="(W - PAD.left - PAD.right) / Math.max(1, points.length)"
          :height="H"
          fill="transparent"
          @mouseenter="hovered = i"
        />
      </g>

      <!-- tooltip -->
      <g
        v-if="hovered !== null"
        :transform="`translate(${Math.min(Math.max(x(hovered), 70), W - 80)}, ${Math.max(y(points[hovered].value) - 42, 8)})`"
      >
        <rect width="120" height="26" rx="6" fill="#23364d" />
        <text x="60" y="17" text-anchor="middle" fill="#fff" font-size="11" font-weight="600">
          {{ points[hovered].label }} · {{ faNum(points[hovered].value) }}{{ unit }}
        </text>
      </g>

      <text
        v-for="(l, k) in xLabels"
        :key="`xl${k}`"
        :x="x(l.i)"
        :y="H - 6"
        text-anchor="middle"
        fill="#6e87a6"
        font-size="10"
      >
        {{ l.label }}
      </text>
    </svg>
  </div>
</template>

<style scoped>
@keyframes vp-line-in {
  from {
    stroke-dasharray: 2000;
    stroke-dashoffset: 2000;
  }
  to {
    stroke-dasharray: 2000;
    stroke-dashoffset: 0;
  }
}
@keyframes vp-area-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>
