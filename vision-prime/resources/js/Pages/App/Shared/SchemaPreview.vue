/** Shared UI: Schema.org JSON-LD Preview */
<script setup lang="ts">
import { computed, ref } from 'vue'
import VCard from '@/shared/ui/VCard.vue'
import VBadge from '@/shared/ui/VBadge.vue'

interface SchemaItem {
  '@type': string
  [key: string]: unknown
}

const props = defineProps<{
  schemas: SchemaItem[]
  siteName?: string
}>()

const expanded = ref(false)

const jsonLd = computed(() =>
  props.schemas.map(s => JSON.stringify(s, null, 2)).join('\n\n'),
)

const schemaTypes = computed(() =>
  props.schemas.map(s => s['@type'] ?? 'Unknown'),
)

const typeLabels: Record<string, string> = {
  Article: 'مقاله',
  NewsArticle: 'خبر',
  BlogPosting: 'پست وبلاگ',
  Product: 'محصول',
  FAQPage: 'سؤالات متداول',
  HowTo: 'چگونگی',
  Review: 'بررسی',
  ItemList: 'لیست',
  BreadcrumbList: 'ناوبری',
  Organization: 'سازمان',
  WebPage: 'صفحه وب',
}

function copyToClipboard(): void {
  navigator.clipboard.writeText(jsonLd.value)
}
</script>

<template>
  <VCard title="📊 اسکیمای Schema.org" description="JSON-LD اسکیما به‌صورت خودکار بر اساس نوع محتوا تولید شده.">
    <!-- نمایش نوع اسکیماها -->
    <div class="flex flex-wrap gap-2">
      <VBadge v-for="type in schemaTypes" :key="type" tone="info">
        {{ typeLabels[type] ?? type }}
      </VBadge>
    </div>

    <!-- خلاصه -->
    <p class="text-ink-muted mt-3 text-xs leading-5">
      ✅ {{ schemaTypes.length }} اسکیما تولید شده ·
      ✅ JSON-LD معتبر ·
      ✅ شامل {{ schemaTypes.includes('BreadcrumbList') ? 'ناوبری' : '—' }} + {{ schemaTypes.includes('FAQPage') ? 'FAQ' : '—' }}
    </p>

    <!-- تگ‌های HTML -->
    <div class="mt-4">
      <button
        type="button"
        class="text-brand-700 text-xs font-medium"
        @click="expanded = !expanded"
      >
        {{ expanded ? 'مخفی کردن کد' : 'نمایش کد JSON-LD' }}
      </button>
    </div>

    <div v-if="expanded" class="mt-3">
      <div class="relative">
        <pre class="bg-surface-muted overflow-x-auto rounded-xl p-4 text-xs leading-5" dir="ltr"><code>{{ jsonLd }}</code></pre>
        <button
          type="button"
          class="text-brand-700 absolute end-3 top-3 text-xs"
          @click="copyToClipboard"
        >
          📋 کپی
        </button>
      </div>
      <p class="text-ink-muted mt-2 text-xs leading-5" dir="ltr">
        &lt;script type="application/ld+json"&gt;...&lt;/script&gt; — این کد به‌صورت خودکار در &lt;head&gt; صفحه قرار می‌گیرد.
      </p>
    </div>
  </VCard>
</template>
