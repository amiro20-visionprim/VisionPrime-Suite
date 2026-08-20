/** Shared UI component: SEO Score Card — RankMath-style */
<script setup lang="ts">
import VCard from '@/shared/ui/VCard.vue'
import VBadge from '@/shared/ui/VBadge.vue'

interface CheckItem {
  label: string
  passed: boolean
  warning?: boolean
  detail?: string
}

const props = defineProps<{
  score: number
  checks: CheckItem[]
  metaTitleLength: number
  metaTitleRange: [number, number]
  metaDescLength: number
  metaDescRange: [number, number]
  keywordDensity: number
  keywordRange: [number, number]
  wordCount: number
  wordRange: [number, number]
  headingCount: number
  headingMin: number
}>()

const scoreColor = (s: number) => s >= 80 ? 'success' : s >= 60 ? 'warning' : 'danger'
const scoreLabel = (s: number) => s >= 80 ? 'عالی' : s >= 60 ? 'قابل قبول' : 'نیاز به بهبود'
</script>

<template>
  <VCard title="📊 امتیاز SEO" description="امتیاز لحظه‌ای بر اساس معیارهای RankMath/Yoast">
    <!-- دایره امتیاز -->
    <div class="flex items-center gap-5">
      <div class="relative flex h-20 w-20 shrink-0 items-center justify-center">
        <svg class="h-20 w-20 -rotate-90" viewBox="0 0 36 36">
          <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" stroke-width="2" class="text-surface-muted" />
          <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" stroke-width="2.5"
            :stroke-dasharray="`${score * 1} ${100 - score}`" stroke-linecap="round"
            :class="scoreColor(score) === 'success' ? 'text-green-500' : scoreColor(score) === 'warning' ? 'text-amber-500' : 'text-red-500'" />
        </svg>
        <span class="absolute text-xl font-bold">{{ score }}</span>
      </div>
      <div>
        <p class="text-ink-strong text-lg font-bold">{{ scoreLabel(score) }}</p>
        <p class="text-ink-muted text-xs leading-5">{{ checks.filter(c => c.passed).length }}/{{ checks.length }} آیتم پاس شده</p>
      </div>
    </div>

    <!-- متریک‌های اصلی -->
    <div class="mt-5 grid grid-cols-2 gap-3 text-xs">
      <div class="rounded-lg bg-surface-muted p-3">
        <p class="text-ink-muted">عنوان متا</p>
        <p class="text-ink-strong font-semibold" :class="metaTitleLength >= metaTitleRange[0] && metaTitleLength <= metaTitleRange[1] ? 'text-green-500' : 'text-red-500'">
          {{ metaTitleLength }}/{{ metaTitleRange[1] }}
        </p>
      </div>
      <div class="rounded-lg bg-surface-muted p-3">
        <p class="text-ink-muted">توضیح متا</p>
        <p class="text-ink-strong font-semibold" :class="metaDescLength >= metaDescRange[0] && metaDescLength <= metaDescRange[1] ? 'text-green-500' : 'text-red-500'">
          {{ metaDescLength }}/{{ metaDescRange[1] }}
        </p>
      </div>
      <div class="rounded-lg bg-surface-muted p-3">
        <p class="text-ink-muted">تراکم کلیدواژه</p>
        <p class="text-ink-strong font-semibold" :class="keywordDensity >= keywordRange[0] && keywordDensity <= keywordRange[1] ? 'text-green-500' : 'text-red-500'">
          {{ keywordDensity.toFixed(1) }}%
        </p>
      </div>
      <div class="rounded-lg bg-surface-muted p-3">
        <p class="text-ink-muted">تعداد کلمات</p>
        <p class="text-ink-strong font-semibold" :class="wordCount >= wordRange[0] ? 'text-green-500' : 'text-red-500'">
          {{ wordCount }}/{{ wordRange[0] }}
        </p>
      </div>
    </div>

    <!-- لیست چک‌ها -->
    <div class="mt-4 space-y-2">
      <div v-for="(check, i) in checks" :key="i" class="flex items-start gap-2 text-xs">
        <span v-if="check.passed" class="mt-0.5 text-green-500">✅</span>
        <span v-else-if="check.warning" class="mt-0.5 text-amber-500">⚠️</span>
        <span v-else class="mt-0.5 text-red-500">❌</span>
        <div>
          <span class="text-ink-strong">{{ check.label }}</span>
          <span v-if="check.detail" class="text-ink-muted ms-1">— {{ check.detail }}</span>
        </div>
      </div>
    </div>
  </VCard>
</template>
