<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(
  defineProps<{
    to: number
    duration?: number
  }>(),
  {
    duration: 1400,
  },
)

const root = ref<HTMLElement | null>(null)
const display = ref(0)
let rafId = 0
let started = false

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)

function animate(): void {
  if (started) {
    return
  }
  started = true

  const start = performance.now()

  const tick = (now: number): void => {
    const progress = Math.min(1, (now - start) / props.duration)
    // easeOutCubic for a confident, decelerating count
    const eased = 1 - Math.pow(1 - progress, 3)
    display.value = Math.round(props.to * eased)
    if (progress < 1) {
      rafId = requestAnimationFrame(tick)
    }
  }

  rafId = requestAnimationFrame(tick)
}

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    display.value = props.to
    return
  }

  const el = root.value
  if (!el) {
    display.value = props.to
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
})

onBeforeUnmount(() => {
  cancelAnimationFrame(rafId)
})
</script>

<template>
  <span ref="root">{{ faNum(display) }}</span>
</template>
