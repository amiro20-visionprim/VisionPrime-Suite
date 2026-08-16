<script setup lang="ts">
import { computed, ref } from 'vue'

export interface BarDatum {
  label: string
  value: number
  /** نمایش با رنگ خنثی (مثلاً مقایسه با ماه قبل) */
  muted?: boolean
  /** برجسته (مثلاً امروز) */
  highlight?: boolean
}

const props = withDefaults(
  defineProps<{
    data: BarDatum[]
    height?: number
    unit?: string
    ariaLabel?: string
  }>(),
  {
    height: 180,
    unit: '',
    ariaLabel: 'نمودار میله‌ای',
  },
)

const max = computed(() => Math.max(1, ...props.data.map((d) => d.value)))

const hovered = ref<number | null>(null)

const faNum = (value: number): string => new Intl.NumberFormat('fa-IR').format(value)
</script>

<template>
  <div class="w-full" :aria-label="ariaLabel" role="img">
    <div
      class="flex items-end gap-2"
      :style="{ height: `${height}px` }"
      @mouseleave="hovered = null"
    >
      <div v-for="(d, i) in data" :key="i" class="group relative flex h-full flex-1 flex-col justify-end">
        <!-- tooltip -->
        <div
          v-if="hovered === i"
          class="rounded-ui bg-ink-strong pointer-events-none absolute -top-2 left-1/2 z-20 -translate-x-1/2 -translate-y-full px-2.5 py-1 text-xs whitespace-nowrap text-white shadow-lg"
        >
          {{ d.label }}: {{ faNum(d.value) }}{{ unit }}
        </div>

        <div
          class="w-full max-w-10 cursor-pointer rounded-t-lg transition-all duration-500 ease-out"
          :class="
            d.muted
              ? 'bg-line-strong'
              : d.highlight
                ? 'bg-gradient-brand'
                : 'bg-gradient-brand opacity-85 hover:opacity-100'
          "
          :style="{ height: `${(d.value / max) * 100}%`, animation: `vp-bar-grow 0.8s ${i * 60}ms cubic-bezier(0.2, 0.8, 0.2, 1) both` }"
          @mouseenter="hovered = i"
          @mouseleave="hovered = null"
        />
      </div>
    </div>
    <div class="mt-2 flex gap-2">
      <span
        v-for="(d, i) in data"
        :key="i"
        class="text-ink-muted flex-1 text-center text-[11px]"
      >
        {{ d.label }}
      </span>
    </div>
  </div>
</template>

<style scoped>
@keyframes vp-bar-grow {
  from {
    transform: scaleY(0);
    transform-origin: bottom;
  }
  to {
    transform: scaleY(1);
    transform-origin: bottom;
  }
}
</style>
