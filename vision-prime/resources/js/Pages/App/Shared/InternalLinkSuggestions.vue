/** Shared UI: Internal Link Suggestions */
<script setup lang="ts">
import { ref } from 'vue'
import VCard from '@/shared/ui/VCard.vue'
import VBadge from '@/shared/ui/VBadge.vue'

interface LinkSuggestion {
  url: string
  title: string
  anchor: string
  relevance_score: number
}

const props = defineProps<{
  suggestions: LinkSuggestion[]
  baseUrl?: string
}>()

const emit = defineEmits<{
  (e: 'toggle', index: number): void
  (e: 'update-anchor', index: number, anchor: string): void
}>()

const enabled = ref<Record<number, boolean>>(
  Object.fromEntries(props.suggestions.map((_, i) => [i, true])),
)

function scoreLabel(s: number): string {
  if (s >= 0.7) return 'مرتبط'
  if (s >= 0.4) return 'متوسط'
  return 'کم‌ارتباط'
}

function scoreColor(s: number): string {
  if (s >= 0.7) return 'success'
  if (s >= 0.4) return 'info'
  return 'neutral'
}

function toggleLink(index: number): void {
  enabled.value[index] = !enabled.value[index]
  emit('toggle', index)
}

function updateAnchor(index: number, event: Event): void {
  const target = event.target as HTMLInputElement
  emit('update-anchor', index, target.value)
}
</script>

<template>
  <VCard title="🔗 لینک‌های داخلی پیشنهادی" description="صفحات مرتبط با محتوای شما بر اساس شباهت موضوعی رتبه‌بندی شده‌اند.">
    <div v-if="suggestions.length === 0" class="text-ink-muted text-sm leading-7">
      هنوز صفحه‌ای در سایت وجود ندارد که لینک داخلی پیشنهاد شود.
      پس از انتشار محتوا و همگام‌سازی سایت، لینک‌ها به‌صورت خودکار پیشنهاد می‌شوند.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="(link, index) in suggestions"
        :key="index"
        class="border-line rounded-xl border p-4 transition-all"
        :class="enabled[index] ? 'bg-surface' : 'opacity-50'"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <input
                type="checkbox"
                :checked="enabled[index]"
                class="accent-brand-600"
                @change="toggleLink(index)"
              />
              <p class="text-ink-strong truncate text-sm font-semibold">{{ link.title }}</p>
              <VBadge :tone="scoreColor(link.relevance_score)">
                {{ scoreLabel(link.relevance_score) }} ({{ (link.relevance_score * 100).toFixed(0) }}%)
              </VBadge>
            </div>
            <p class="text-ink-muted mt-1 text-xs" dir="ltr">{{ link.url }}</p>
            <div class="mt-2 flex items-center gap-2">
              <span class="text-ink-muted text-xs">Anchor:</span>
              <input
                type="text"
                :value="link.anchor"
                class="border-line rounded-lg border px-2 py-1 text-xs"
                dir="auto"
                @input="updateAnchor(index, $event)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-if="suggestions.length > 0" class="text-ink-muted mt-3 text-xs leading-5">
      ✅ لینک‌های انتخاب‌شده به‌صورت خودکار در محتوا قرار داده می‌شوند.
      تعداد پیشنهادی: حداقل {{ Math.min(2, suggestions.length) }} — حداکثر {{ Math.min(8, suggestions.length) }} لینک.
    </p>
  </VCard>
</template>
