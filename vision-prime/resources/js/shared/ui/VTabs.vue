<script setup lang="ts">
import { computed, useId } from 'vue'

export interface TabItem {
  key: string
  label: string
  disabled?: boolean
}

const props = defineProps<{
  modelValue: string
  tabs: TabItem[]
  ariaLabel?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const generatedId = useId()
const activeTab = computed(() => props.tabs.find((tab) => tab.key === props.modelValue))

function selectTab(tab: TabItem): void {
  if (!tab.disabled) emit('update:modelValue', tab.key)
}
</script>

<template>
  <div>
    <div
      class="border-line flex gap-1 overflow-x-auto border-b"
      role="tablist"
      :aria-label="ariaLabel ?? 'تب‌ها'"
    >
      <button
        v-for="tab in tabs"
        :id="`${generatedId}-${tab.key}-tab`"
        :key="tab.key"
        type="button"
        role="tab"
        :aria-controls="`${generatedId}-${tab.key}-panel`"
        :aria-selected="activeTab?.key === tab.key"
        :disabled="tab.disabled"
        :class="[
          'transition-ui relative shrink-0 px-3 py-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-45',
          activeTab?.key === tab.key ? 'text-brand-700' : 'text-ink-muted hover:text-ink-strong',
        ]"
        @click="selectTab(tab)"
      >
        {{ tab.label }}
        <span
          v-if="activeTab?.key === tab.key"
          class="bg-brand-700 absolute inset-x-3 bottom-0 h-0.5 rounded-full"
          aria-hidden="true"
        />
      </button>
    </div>
    <div
      v-for="tab in tabs"
      :id="`${generatedId}-${tab.key}-panel`"
      :key="tab.key"
      role="tabpanel"
      :aria-labelledby="`${generatedId}-${tab.key}-tab`"
      :hidden="activeTab?.key !== tab.key"
      class="pt-5"
    >
      <slot v-if="activeTab?.key === tab.key" :name="tab.key" />
    </div>
  </div>
</template>
