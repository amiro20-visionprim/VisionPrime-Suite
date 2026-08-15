<script setup lang="ts">
import { computed } from 'vue'

export interface TrendPoint {
  date: string
  position: number | null
  clicks: number
}

const props = defineProps<{
  points: TrendPoint[]
  publishDate: string
}>()

const W = 720
const H = 240
const PAD = { top: 16, right: 16, bottom: 28, left: 40 }

/** روز انتشار به‌صورت عدد ایندکس در سری. */
const publishIndex = computed(() => {
  const pub = props.publishDate.slice(0, 10)
  const i = props.points.findIndex((p) => p.date === pub)
  return i >= 0 ? i : null
})

const clicksMax = computed(() => Math.max(1, ...props.points.map((p) => p.clicks)))

const positionMin = computed(() => {
  const vals = props.points.filter((p) => p.position !== null).map((p) => p.position as number)
  return vals.length ? Math.min(...vals) : 1
})
const positionMax = computed(() => {
  const vals = props.points.filter((p) => p.position !== null).map((p) => p.position as number)
  return vals.length ? Math.max(...vals) : 10
})

const x = (i: number) => PAD.left + (i / Math.max(1, props.points.length - 1)) * (W - PAD.left - PAD.right)

const yFor = (v: number, min: number, max: number) =>
  PAD.top + (1 - (v - min) / Math.max(0.0001, max - min)) * (H - PAD.top - PAD.bottom)

const clicksY = (v: number) => yFor(v, 0, clicksMax.value)

/** جایگاه کمتر = بهتر → محور معکوس. */
const positionY = (v: number) => yFor(v, positionMin.value, positionMax.value)

const clicksPath = computed(() => {
  return props.points
    .map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${clicksY(p.clicks).toFixed(1)}`)
    .join(' ')
})

const positionPath = computed(() => {
  const pts = props.points
    .map((p, i) => (p.position !== null ? { i, v: p.position } : null))
    .filter((p): p is { i: number; v: number } => p !== null)
  if (!pts.length) return ''
  return pts
    .map((p, k) => `${k === 0 ? 'M' : 'L'} ${x(p.i).toFixed(1)} ${positionY(p.v).toFixed(1)}`)
    .join(' ')
})

const positionDots = computed(() =>
  props.points
    .map((p, i) => (p.position !== null ? { i, v: p.position } : null))
    .filter((p): p is { i: number; v: number } => p !== null)
    .map((p) => ({ cx: x(p.i).toFixed(1), cy: positionY(p.v).toFixed(1) })),
)

const labelEvery = computed(() => Math.max(1, Math.ceil(props.points.length / 8)))

const xLabels = computed(() =>
  props.points
    .map((p, i) => ({ i, date: p.date }))
    .filter((_, i) => i % labelEvery.value === 0),
)

const fmtDate = (d: string) => {
  const [, m, day] = d.split('-').map(Number)
  return `${m}/${day}`
}
</script>

<template>
  <div dir="ltr" class="w-full overflow-x-auto">
    <svg :viewBox="`0 0 ${W} ${H}`" class="min-w-[560px]" role="img" aria-label="روند جایگاه و کلیک">
      <line
        v-if="publishIndex !== null"
        :x1="x(publishIndex)"
        :x2="x(publishIndex)"
        y1="0"
        :y2="H"
        stroke="#94a3b8"
        stroke-dasharray="4 3"
        stroke-width="1.2"
      />
      <text
        v-if="publishIndex !== null"
        :x="x(publishIndex)"
        y="12"
        text-anchor="middle"
        fill="#64748b"
        font-size="10"
      >
        انتشار
      </text>

      <!-- خطوط راهنما -->
      <line
        v-for="n in 4"
        :key="`g${n}`"
        :x1="PAD.left"
        :x2="W - PAD.right"
        :y1="PAD.top + (n * (H - PAD.top - PAD.bottom)) / 4"
        :y2="PAD.top + (n * (H - PAD.top - PAD.bottom)) / 4"
        stroke="#e2e8f0"
        stroke-width="1"
      />

      <!-- کلیک (نارنجی) -->
      <path v-if="clicksPath" :d="clicksPath" fill="none" stroke="#f59e0b" stroke-width="2" />
      <!-- جایگاه (آبی، محور معکوس) -->
      <path v-if="positionPath" :d="positionPath" fill="none" stroke="#2563eb" stroke-width="2" />

      <circle
        v-for="(d, k) in positionDots"
        :key="`pd${k}`"
        :cx="d.cx"
        :cy="d.cy"
        r="2.4"
        fill="#2563eb"
      />

      <text
        v-for="(l, k) in xLabels"
        :key="`xl${k}`"
        :x="x(l.i)"
        :y="H - 8"
        text-anchor="middle"
        fill="#94a3b8"
        font-size="10"
      >
        {{ fmtDate(l.date) }}
      </text>

      <!-- افسانه -->
      <g transform="translate(0, 2)">
        <line x1="0" y1="0" x2="14" y2="0" stroke="#2563eb" stroke-width="2" />
        <text x="18" y="3" fill="#475569" font-size="10">جایگاه (کمتر بهتر)</text>
        <line x1="110" y1="0" x2="124" y2="0" stroke="#f59e0b" stroke-width="2" />
        <text x="128" y="3" fill="#475569" font-size="10">کلیک</text>
      </g>
    </svg>
  </div>
</template>
