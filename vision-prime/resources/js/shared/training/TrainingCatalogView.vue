<script setup lang="ts">
import { computed, ref } from 'vue'

import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VIcon, { type IconTone } from '@/shared/ui/VIcon.vue'
import { catalogCategories, catalogFor, type CatalogAudience } from '@/lib/catalog'

const props = withDefaults(
  defineProps<{
    audience: CatalogAudience
    heading?: string
    intro?: string
  }>(),
  {
    heading: 'مرکز آموزش سوئیت',
    intro:
      'همهٔ امکانات سوئیت را قدم‌به‌قدم یاد بگیرید — به زبان ساده، بدون اصطلاح فنی. یک قابلیت را انتخاب کنید تا ببینید چه فایده‌ای دارد و چطور استفاده می‌شود.',
  },
)

const items = computed(() => catalogFor(props.audience))
const categories = computed(() => catalogCategories(items.value))
const openItem = ref<string | null>(null)

const toneFor = (index: number): IconTone => {
  const tones: IconTone[] = ['brand', 'violet', 'success', 'warning']
  return tones[index % tones.length] ?? 'brand'
}

function toggle(id: string): void {
  openItem.value = openItem.value === id ? null : id
}
</script>

<template>
  <div class="space-y-8">
    <header class="space-y-3">
      <p class="text-brand-700 text-xs font-bold">📚 مرکز آموزش</p>
      <h1 class="font-display text-ink-strong text-2xl font-bold sm:text-3xl">{{ heading }}</h1>
      <p class="text-ink-muted max-w-3xl leading-7">{{ intro }}</p>
    </header>

    <section
      v-for="category in categories"
      :key="category"
      class="space-y-3"
    >
      <h2 class="text-ink-strong flex items-center gap-2 text-base font-bold">
        <span class="bg-brand-50 text-brand-700 rounded-ui inline-flex size-7 items-center justify-center">
          <VIcon name="list" size="sm" tone="brand" />
        </span>
        {{ category }}
      </h2>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <VCard
          v-for="(item, index) in items.filter((i) => i.category === category)"
          :key="item.id"
          class="flex flex-col"
        >
          <div class="flex items-start gap-4">
            <span
              class="rounded-ui flex size-11 shrink-0 items-center justify-center"
              :class="{
                'bg-brand-50': toneFor(index) === 'brand',
                'bg-violet-50': toneFor(index) === 'violet',
                'bg-success-50': toneFor(index) === 'success',
                'bg-warning-50': toneFor(index) === 'warning',
              }"
            >
              <VIcon :name="item.icon" :tone="toneFor(index)" size="lg" />
            </span>
            <div class="min-w-0">
              <h3 class="text-ink-strong font-bold">{{ item.title }}</h3>
              <p class="text-ink-muted mt-1 text-sm leading-6">{{ item.summary }}</p>
            </div>
          </div>

          <div class="mt-4 space-y-3">
            <div class="border-line bg-surface-muted rounded-ui border p-3">
              <p class="text-ink-strong flex items-center gap-1.5 text-xs font-bold">
                <VIcon name="lightbulb" tone="warning" size="sm" />
                چرا مفید است؟
              </p>
              <p class="text-ink mt-1 text-sm leading-6">{{ item.benefit }}</p>
            </div>

            <button
              type="button"
              class="text-ink-strong hover:bg-surface-muted transition-ui rounded-ui flex w-full items-center justify-between px-2 py-2 text-sm font-semibold"
              :aria-expanded="openItem === item.id"
              @click="toggle(item.id)"
            >
              <span class="flex items-center gap-1.5">
                <VIcon name="list" size="sm" />
                نحوهٔ استفاده — قدم‌به‌قدم
              </span>
              <span
                class="text-ink-muted transition-transform"
                :class="openItem === item.id ? 'rotate-180' : ''"
              >▾</span>
            </button>

            <ol
              v-if="openItem === item.id"
              class="space-y-2"
            >
              <li
                v-for="(step, stepIndex) in item.steps"
                :key="stepIndex"
                class="flex items-start gap-2.5"
              >
                <span
                  class="bg-brand-700 mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white"
                >{{ stepIndex + 1 }}</span>
                <p class="text-ink text-sm leading-6">{{ step }}</p>
              </li>
            </ol>

            <VButton
              :href="item.href"
              class="w-full"
              variant="secondary"
              size="sm"
            >
              <template #icon><VIcon :name="item.icon" size="sm" /></template>
              {{ item.hrefLabel }}
            </VButton>
          </div>
        </VCard>
      </div>
    </section>
  </div>
</template>
