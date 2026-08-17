<script setup lang="ts">
import { computed, ref } from 'vue'
import { getStoredPreference, setThemePreference } from '@/lib/theme'
import VIcon from '@/shared/ui/VIcon.vue'

const pref = ref(getStoredPreference())

const isDark = computed(
  () => pref.value === 'dark' || (pref.value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches),
)

function toggle(): void {
  const next = isDark.value ? 'light' : 'dark'
  setThemePreference(next)
  pref.value = next
}
</script>

<template>
  <button
    type="button"
    class="transition-ui rounded-ui border-line text-ink-strong hover:bg-surface-muted inline-flex size-9 shrink-0 cursor-pointer items-center justify-center border"
    :aria-label="isDark ? 'تغییر به حالت روشن' : 'تغییر به حالت تاریک'"
    :title="isDark ? 'حالت تاریک — برای روشن کلیک کن' : 'حالت روشن — برای تاریک کلیک کن'"
    @click="toggle"
  >
    <VIcon v-if="isDark" name="sun" tone="neutral" size="sm" />
    <VIcon v-else name="moon" tone="neutral" size="sm" />
  </button>
</template>
