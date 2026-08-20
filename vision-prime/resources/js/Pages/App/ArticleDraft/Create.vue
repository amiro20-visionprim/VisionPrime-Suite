<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface SiteOption { id: number; name: string }
interface GeneratedResult {
  content: string; model: string; source: string
  meta_title: string; meta_description: string
  schemas: unknown[]; links: unknown[]
  quality: { passed: boolean; score: number; failures: string[]; warnings: string[] }
  standard: Record<string, unknown>; profile: Record<string, unknown>
}

const p = defineProps<{
  sites: SiteOption[]
  subtypes: Record<string, string>
  isSuperAdmin: boolean
}>()

const page = usePage<{ flash?: { status?: string; error?: string } }>()

// State
const step = ref<'input' | 'generating' | 'result'>('input')
const loadingGenerate = ref(false)
const selectedSiteId = ref('')
const title = ref('')
const subtype = ref('general')
const result = ref<GeneratedResult | null>(null)
const activeResultTab = ref<'content' | 'meta' | 'seo' | 'schema'>('content')

// SEO metrics (computed from result)
const wordCount = computed(() => {
  if (!result.value?.content) return 0
  return result.value.content.replace(/<[^>]+>/g, ' ').trim().split(/\s+/).filter(w => w.length > 0).length
})

const seoScore = computed(() => result.value?.quality?.score ?? 0)
const seoChecks = computed(() => {
  if (!result.value) return []
  const checks = []
  checks.push({ label: 'امتیاز کیفیت', value: result.value.quality?.score ?? 0, min: 70 })
  checks.push({ label: 'تعداد کلمات', value: wordCount.value, min: 400 })
  checks.push({ label: 'مدل AI', value: result.value.model || '-', min: 0 })
  checks.push({ label: 'منبع', value: result.value.source || '-', min: 0 })
  return checks
})

// Generate with AI
async function generate() {
  if (!selectedSiteId.value || !title.value.trim()) return
  step.value = 'generating'
  loadingGenerate.value = true
  result.value = null

  try {
    const res = await fetch('/api/content/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        site_id: Number(selectedSiteId.value),
        keyword: title.value.trim(),
        title: title.value.trim(),
        subtype: subtype.value || undefined,
      }),
    })
    const data = await res.json()
    if (data.error) {
      alert('\u274c ' + data.error)
      step.value = 'input'
    } else {
      result.value = data
      step.value = 'result'
    }
  } catch (e: unknown) {
    alert('\u274c ' + (e instanceof Error ? e.message : String(e)))
    step.value = 'input'
  }
  loadingGenerate.value = false
}

// Regenerate
function regenerate() {
  step.value = 'input'
  result.value = null
}

// Auto-generate meta title
function autoMetaTitle() {
  if (result.value && title.value) {
    result.value.meta_title = title.value + ' | ' + (p.sites.find(s => String(s.id) === selectedSiteId.value)?.name ?? '')
  }
}
</script>

<template>
  <Head title="\u062a\u0648\u0644\u06cc\u062f \u0645\u0642\u0627\u0644\u0647 \u0647\u0648\u0634\u0645\u0646\u062f" />
  <AppLayout>
    <VPageHeader title="\u{1f4dd} \u062a\u0648\u0644\u06cc\u062f \u0645\u0642\u0627\u0644\u0647 \u0647\u0648\u0634\u0645\u0646\u062f" description="\u0633\u0627\u062f\u0647 \u062a\u0648\u0644\u06cc\u062f \u0628\u0627 \u0631\u0648\u06cc \u0627\u0639\u062a\u0628\u0627\u0631\u06cc \u0635\u0641\u062d\u0647 \u0634\u0648\u06cc\u062f." />

    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">{{ page.props.flash.status }}</VAlert>

    <!-- STEP 1: Input -->
    <div v-if="step === 'input'" class="mt-6 max-w-2xl mx-auto">
      <VCard title="\u0639\u0646\u0648\u0627\u0635\u0631 \u0645\u0642\u0627\u0644\u0647 \u0631\u0627 \u0648\u0627\u0631\u062f \u06a9\u0646\u06cc\u062f">
        <div class="space-y-4">
          <VSelect v-model="selectedSiteId" label="\u0633\u0627\u06cc\u062a" :options="p.sites.map(s => ({ label: s.name, value: String(s.id) }))" placeholder="\u0627\u0646\u062a\u062e\u0627\u0628 \u0633\u0627\u06cc\u062a" />

          <div>
            <label class="text-ink-strong text-sm font-semibold">\u0639\u0646\u0648\u0627\u0635\u0631 \u0645\u0642\u0627\u0644\u0647 (\u0635\u0641\u062d\u0647 \u0631\u0627 \u0627\u0646\u062a\u062e\u0627\u0628 \u06a9\u0646\u06cc\u062f)</label>
            <input v-model="title" type="text" dir="auto" class="border-line mt-2 w-full rounded-xl border px-4 py-3 text-lg focus:ring-2 focus:ring-brand-500" placeholder="\u0645\u062b\u0644: \u0631\u0627\u0647\u0646\u0645\u0627\u06cc \u062c\u0627\u0645\u0639 \u0633\u0626\u0648 \u0628\u0631\u0627\u06cc \u0633\u0627\u06cc\u062a \u0641\u0631\u0648\u0634\u06af\u0627\u0647\u06cc" @keyup.enter="generate" />
            <p class="text-ink-muted mt-1 text-xs">\u0628\u0627 \u0627\u0646\u062a\u062e\u0627\u0628 \u0639\u0646\u0648\u0627\u0635\u0631 \u0645\u0642\u0627\u0644\u0647\u060c \u0633\u06cc\u0633\u062a\u0645 \u062e\u0648\u062f\u06a9\u0627\u0631 \u0631\u0627 \u062a\u0648\u0633\u0637 \u0645\u06cc\u062f\u0647\u062f.</p>
          </div>

          <VSelect v-model="subtype" label="\u0632\u06cc\u0631\u0646\u0648\u0639 (\u062e\u0648\u062f\u06a9\u0627\u0631)" :options="Object.entries(p.subtypes).map(([v,l]) => ({label:l, value:v}))" />

          <VButton @click="generate" :loading="loadingGenerate" :disabled="!selectedSiteId || !title.trim()" variant="primary" size="lg" class="w-full">
            <span v-if="!loadingGenerate">\u{1f680} \u0634\u0631\u0648\u0639 \u062a\u0648\u0644\u06cc\u062f</span>
            <span v-else>\u062f\u0631 \u062d\u0627\u0644 \u062a\u0648\u0644\u06cc\u062f...</span>
          </VButton>
        </div>
      </VCard>
    </div>

    <!-- STEP 2: Generating -->
    <div v-if="step === 'generating'" class="mt-6 max-w-2xl mx-auto text-center py-16">
      <div class="text-6xl mb-4 animate-pulse">\u{1f916}</div>
      <h2 class="text-xl font-bold text-ink-strong">\u062f\u0631 \u062d\u0627\u0644 \u062a\u0648\u0644\u06cc\u062f \u0645\u0642\u0627\u0644\u0647...</h2>
      <p class="text-ink-muted mt-2">\u0647\u0648\u0634\u0645\u0646\u062f \u0627\u0635\u0644\u06cc \u0628\u0647 \u062a\u062d\u0644\u06cc\u0644 \u0627\u0637\u0644\u0627\u0639\u0627\u062a GSC \u0648 \u062a\u0648\u0644\u06cc\u062f \u0645\u0642\u0627\u0644\u0647 \u0638\u0631\u06cc\u0641\u0647 \u0634\u062f...</p>
      <div class="mt-8 space-y-2 text-sm text-ink-muted">
        <p>\u2705 \u062a\u062d\u0644\u06cc\u0644 \u0627\u0637\u0644\u0627\u0639\u0627\u062a GSC</p>
        <p>\u2705 \u067e\u0631\u0648\u0641\u0627\u06cc\u0644 \u0646\u0648\u0639 \u0645\u062d\u062a\u0648\u0627</p>
        <p>\u2705 \u0627\u0639\u0645\u0627\u0644 \u06af\u0627\u0631\u062f\u0631\u0627\u06cc\u0644\u0633</p>
        <p class="animate-pulse">\u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631 \u062a\u0648\u0644\u06cc\u062f \u0645\u0642\u0627\u0644\u0647 \u0628\u0627 AI...</p>
      </div>
    </div>

    <!-- STEP 3: Result -->
    <div v-if="step === 'result' && result" class="mt-6">
      <!-- Success Banner -->
      <VCard class="mb-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">\u2705</span>
            <div>
              <h3 class="font-bold text-ink-strong">\u0645\u0642\u0627\u0644\u0647 \u062a\u0648\u0644\u06cc\u062f \u0634\u062f!</h3>
              <p class="text-ink-muted text-sm">\u0645\u062f\u0644: {{ result.model }} | \u0645\u0646\u0628\u0639: {{ result.source }} | \u06a9\u0644\u0645\u0627\u062a: {{ wordCount }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <VBadge :tone="seoScore >= 70 ? 'success' : seoScore >= 40 ? 'warning' : 'danger'" size="lg">
              \u0627\u0645\u062a\u06cc\u0627\u0632: {{ seoScore }}/100
            </VBadge>
            <VButton @click="regenerate" variant="secondary">\u{1f504} \u062a\u0648\u0644\u06cc\u062f \u0645\u062c\u062f\u062f</VButton>
          </div>
        </div>
      </VCard>

      <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <!-- Main Content -->
        <div class="space-y-6">
          <!-- Tabs -->
          <div class="border-line flex gap-1 border-b">
            <button v-for="tab in [{id:'content' as const,label:'\u270f\ufe0f \u0645\u062d\u062a\u0648\u0627'}, {id:'meta' as const,label:'\u{1f3f7}\ufe0f Meta'}, {id:'seo' as const,label:'\u{1f4ca} \u0627\u0645\u062a\u06cc\u0627\u0632'}, {id:'schema' as const,label:'\u{1f4ca} \u0627\u0633\u06a9\u06cc\u0645\u0627'}]" :key="tab.id" type="button" class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors" :class="activeResultTab === tab.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-muted hover:text-ink-strong'" @click="activeResultTab = tab.id">{{ tab.label }}</button>
          </div>

          <!-- Content Tab -->
          <VCard v-if="activeResultTab === 'content'">
            <div class="prose prose-sm max-w-none" dir="auto" v-html="result.content" />
          </VCard>

          <!-- Meta Tab -->
          <VCard v-if="activeResultTab === 'meta'">
            <div class="space-y-4">
              <div>
                <div class="flex items-center justify-between"><label class="text-ink-strong text-sm font-semibold">Meta Title</label><button type="button" class="text-brand-700 text-xs" @click="autoMetaTitle">\u062a\u0648\u0644\u06cc\u062f \u062e\u0648\u062f\u06a9\u0627\u0631</button></div>
                <input v-model="result.meta_title" dir="auto" maxlength="70" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
                <div class="mt-1 flex items-center gap-2">
                  <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full transition-all" :class="(result.meta_title?.length ?? 0) >= 30 && (result.meta_title?.length ?? 0) <= 60 ? 'bg-green-500' : 'bg-red-500'" :style="{ width: Math.min(100, ((result.meta_title?.length ?? 0) / 60) * 100) + '%' }" /></div>
                  <span class="text-ink-muted text-xs">{{ result.meta_title?.length ?? 0 }}/60</span>
                </div>
              </div>
              <div>
                <label class="text-ink-strong text-sm font-semibold">Meta Description</label>
                <textarea v-model="result.meta_description" dir="auto" rows="3" maxlength="200" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
                <div class="mt-1 flex items-center gap-2">
                  <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full transition-all" :class="(result.meta_description?.length ?? 0) >= 120 && (result.meta_description?.length ?? 0) <= 160 ? 'bg-green-500' : 'bg-red-500'" :style="{ width: Math.min(100, ((result.meta_description?.length ?? 0) / 160) * 100) + '%' }" /></div>
                  <span class="text-ink-muted text-xs">{{ result.meta_description?.length ?? 0 }}/160</span>
                </div>
              </div>
            </div>
          </VCard>

          <!-- SEO Tab -->
          <VCard v-if="activeResultTab === 'seo'">
            <div class="space-y-3">
              <div v-for="check in seoChecks" :key="check.label" class="flex items-center justify-between">
                <span class="text-ink-strong text-sm">{{ check.label }}</span>
                <VBadge :tone="check.label === 'امتیاز کیفیت' ? (check.value >= check.min ? 'success' : 'danger') : 'info'">
                  {{ check.value }}
                </VBadge>
              </div>
              <div v-if="result.quality?.failures?.length" class="mt-4">
                <p class="text-red-600 text-sm font-semibold">\u274c \u0645\u0634\u06a9\u0644\u0627\u062a:</p>
                <ul class="mt-1 space-y-1"><li v-for="f in result.quality.failures" :key="f" class="text-red-500 text-xs">\u2022 {{ f }}</li></ul>
              </div>
              <div v-if="result.quality?.warnings?.length" class="mt-4">
                <p class="text-yellow-600 text-sm font-semibold">\u26a0\ufe0f \u0646\u06a9\u0627\u062a:</p>
                <ul class="mt-1 space-y-1"><li v-for="w in result.quality.warnings" :key="w" class="text-yellow-500 text-xs">\u2022 {{ w }}</li></ul>
              </div>
            </div>
          </VCard>

          <!-- Schema Tab -->
          <VCard v-if="activeResultTab === 'schema'">
            <div class="space-y-2">
              <div v-for="(schema, i) in (result.schemas || [])" :key="i">
                <pre class="bg-surface-muted rounded-xl p-4 text-xs overflow-x-auto" dir="ltr">{{ JSON.stringify(schema, null, 2) }}</pre>
              </div>
            </div>
          </VCard>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <VCard title="\u{1f4ca} \u062e\u0644\u0627\u0635\u0647">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-ink-muted">\u06a9\u0644\u0645\u0627\u062a:</span><span class="font-medium">{{ wordCount }}</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">\u0627\u0645\u062a\u06cc\u0627\u0632:</span><span class="font-medium">{{ seoScore }}/100</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">\u0644\u06cc\u0646\u06a9\u200c\u0647\u0627:</span><span class="font-medium">{{ result.links?.length ?? 0 }}</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">\u0627\u0633\u06a9\u06cc\u0645\u0627:</span><span class="font-medium">{{ result.schemas?.length ?? 0 }}</span></div>
            </div>
          </VCard>

          <VCard v-if="result.links?.length" title="\u{1f517} \u0644\u06cc\u0646\u06a9\u200c\u0647\u0627\u06cc \u062f\u0627\u062e\u0644\u06cc">
            <div class="space-y-2">
              <div v-for="link in result.links.slice(0, 5)" :key="link.url" class="text-xs">
                <span class="text-brand-700">{{ link.anchor }}</span> \u2192 <span class="text-ink-muted">{{ link.url }}</span>
              </div>
            </div>
          </VCard>

          <VCard v-if="result.profile" title="\u{1f3af} \u067e\u0631\u0648\u0641\u0627\u06cc\u0644">
            <div class="space-y-1 text-xs">
              <div>\u0646\u0648\u0639: {{ result.profile.content_type }}</div>
              <div>\u0632\u06cc\u0631\u0646\u0648\u0639: {{ result.profile.subtype }}</div>
              <div>\u0642\u0635\u062f: {{ result.profile.intent }}</div>
            </div>
          </VCard>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
