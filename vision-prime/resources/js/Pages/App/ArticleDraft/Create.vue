<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface SiteOption { id: number; name: string; canonical_url: string }
interface SchemaItem { '@type': string; [key: string]: unknown }
interface QualityResult { passed: boolean; score: number; failures: string[]; warnings: string[] }
interface LinkSuggestion { url: string; title: string; anchor: string; relevance_score: number }
interface GeneratedResult {
  content: string
  model: string
  source: string
  meta_title: string
  meta_description: string
  schemas: SchemaItem[]
  links: LinkSuggestion[]
  quality: QualityResult
  profile: { content_type: string; subtype: string; intent: string } | null
}

const p = defineProps<{
  sites: SiteOption[]
  subtypes: Record<string, string>
  standards: Record<string, unknown>
  isSuperAdmin: boolean
}>()

const page = usePage<{ flash?: { status?: string; error?: string } }>()

const step = ref<'input' | 'generating' | 'result'>('input')
const selectedSiteId = ref('')
const title = ref('')
const subtype = ref('how_to_guide')
const loadingGenerate = ref(false)
const result = ref<GeneratedResult | null>(null)
const activeResultTab = ref<'content' | 'meta' | 'seo' | 'schema'>('content')
const errorMsg = ref('')

const wordCount = computed(() => {
  if (!result.value?.content) return 0
  const plain = result.value.content.replace(/<[^>]+>/g, ' ').trim()
  return plain ? plain.split(/\s+/).filter(w => w.length > 0).length : 0
})

const seoScore = computed(() => result.value?.quality?.score ?? 0)

const seoChecks = computed(() => {
  if (!result.value) return []
  const r = result.value
  return [
    { label: 'طول محتوا', value: wordCount.value + ' کلمه', passed: wordCount.value >= 400 },
    { label: 'Meta Title', value: (r.meta_title?.length ?? 0) + '/60', passed: (r.meta_title?.length ?? 0) >= 30 && (r.meta_title?.length ?? 0) <= 60 },
    { label: 'Meta Description', value: (r.meta_description?.length ?? 0) + '/160', passed: (r.meta_description?.length ?? 0) >= 120 && (r.meta_description?.length ?? 0) <= 160 },
    { label: 'لینک‌های داخلی', value: (r.links?.length ?? 0) + ' عدد', passed: (r.links?.length ?? 0) >= 2 },
    { label: 'اسکیما', value: (r.schemas?.length ?? 0) + ' عدد', passed: (r.schemas?.length ?? 0) >= 1 },
    { label: 'امتیاز کیفیت', value: r.quality?.score ?? 0, passed: (r.quality?.score ?? 0) >= 70 },
  ]
})

async function generate() {
  if (!selectedSiteId.value || !title.value.trim()) return
  step.value = 'generating'
  errorMsg.value = ''
  loadingGenerate.value = true
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
      errorMsg.value = data.error
      step.value = 'input'
    } else {
      result.value = data
      step.value = 'result'
    }
  } catch (e: unknown) {
    errorMsg.value = 'خطا: ' + (e instanceof Error ? e.message : String(e))
    step.value = 'input'
  }
  loadingGenerate.value = false
}

function regenerate() { result.value = null; step.value = 'input' }
function autoMetaTitle() {
  if (result.value && title.value) {
    const siteName = p.sites.find(s => String(s.id) === selectedSiteId.value)?.name ?? ''
    result.value.meta_title = title.value.substring(0, 50) + ' | ' + siteName
  }
}
</script>

<template>
  <Head title="تولید مقاله هوشمند" />
  <AppLayout>
    <VPageHeader title="📝 تولید مقاله هوشمند" description="با وارد کردن عنوان، سیستم خودکار محتوا را تولید می‌کند." />

    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">{{ page.props.flash.status }}</VAlert>

    <!-- STEP 1: Input -->
    <div v-if="step === 'input'" class="mt-6 max-w-2xl mx-auto">
      <VCard title="عنوان مقاله را وارد کنید">
        <div class="space-y-4">
          <VSelect v-model="selectedSiteId" label="سایت" :options="p.sites.map(s => ({ label: s.name, value: String(s.id) }))" placeholder="انتخاب سایت" />

          <div>
            <label class="text-ink-strong text-sm font-semibold">عنوان مقاله را وارد کنید</label>
            <input v-model="title" type="text" dir="auto" class="border-line mt-2 w-full rounded-xl border px-4 py-3 text-lg focus:ring-2 focus:ring-brand-500" placeholder="مثل: راهنمای جامع سئو برای سایت فروشگاهی" @keyup.enter="generate" />
            <p class="text-ink-muted mt-1 text-xs">با انتخاب عنوان مقاله، سیستم خودکار محتوا را تولید می‌کند.</p>
          </div>

          <VSelect v-model="subtype" label="زیرنوع (خودکار)" :options="Object.entries(p.subtypes).map(([v,l]) => ({label:l, value:v}))" />

          <VButton @click="generate" :loading="loadingGenerate" :disabled="!selectedSiteId || !title.trim()" variant="primary" size="lg" class="w-full">
            <span v-if="!loadingGenerate">🚀 شروع تولید</span>
            <span v-else>در حال تولید...</span>
          </VButton>
        </div>
      </VCard>
    </div>

    <!-- STEP 2: Generating -->
    <div v-if="step === 'generating'" class="mt-6 max-w-2xl mx-auto text-center py-16">
      <div class="text-6xl mb-4 animate-pulse">🤖</div>
      <h2 class="text-xl font-bold text-ink-strong">در حال تولید مقاله...</h2>
      <p class="text-ink-muted mt-2">هوش مصنوعی در حال تحلیل اطلاعات GSC و تولید مقاله است...</p>
      <div class="mt-8 space-y-2 text-sm text-ink-muted">
        <p>✅ تحلیل اطلاعات GSC</p>
        <p>✅ پروفایل نوع محتوا</p>
        <p>✅ اعمال گاردرایلس</p>
        <p class="animate-pulse">در انتظار تولید مقاله با AI...</p>
      </div>
    </div>

    <!-- STEP 3: Result -->
    <div v-if="step === 'result' && result" class="mt-6">
      <!-- Success Banner -->
      <VCard class="mb-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">✅</span>
            <div>
              <h3 class="font-bold text-ink-strong">مقاله تولید شد!</h3>
              <p class="text-ink-muted text-sm">مدل: {{ result.model }} | منبع: {{ result.source }} | کلمات: {{ wordCount }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <VBadge :tone="seoScore >= 70 ? 'success' : seoScore >= 40 ? 'warning' : 'danger'" size="lg">
              امتیاز: {{ seoScore }}/100
            </VBadge>
            <VButton @click="regenerate" variant="secondary">🔄 تولید مجدد</VButton>
          </div>
        </div>
      </VCard>

      <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <!-- Main Content -->
        <div class="space-y-6">
          <!-- Tabs -->
          <div class="border-line flex gap-1 border-b">
            <button v-for="tab in [{id:'content',label:'✏️ محتوا'}, {id:'meta',label:'🏷️ Meta'}, {id:'seo',label:'📊 امتیاز'}, {id:'schema',label:'📊 اسکیما'}]" :key="tab.id" type="button" class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors" :class="activeResultTab === tab.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-muted hover:text-ink-strong'" @click="activeResultTab = tab.id">{{ tab.label }}</button>
          </div>

          <!-- Content Tab -->
          <VCard v-if="activeResultTab === 'content'">
            <div class="prose prose-sm max-w-none" dir="auto" v-html="result.content" />
          </VCard>

          <!-- Meta Tab -->
          <VCard v-if="activeResultTab === 'meta'">
            <div class="space-y-4">
              <div>
                <div class="flex items-center justify-between"><label class="text-ink-strong text-sm font-semibold">Meta Title</label><button type="button" class="text-brand-700 text-xs" @click="autoMetaTitle">تولید خودکار</button></div>
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
                <p class="text-red-600 text-sm font-semibold">❌ مشکلات:</p>
                <ul class="mt-1 space-y-1"><li v-for="f in result.quality.failures" :key="f" class="text-red-500 text-xs">• {{ f }}</li></ul>
              </div>
              <div v-if="result.quality?.warnings?.length" class="mt-4">
                <p class="text-yellow-600 text-sm font-semibold">⚠️ نکات:</p>
                <ul class="mt-1 space-y-1"><li v-for="w in result.quality.warnings" :key="w" class="text-yellow-500 text-xs">• {{ w }}</li></ul>
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
          <VCard title="📊 خلاصه">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-ink-muted">کلمات:</span><span class="font-medium">{{ wordCount }}</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">امتیاز:</span><span class="font-medium">{{ seoScore }}/100</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">لینک‌ها:</span><span class="font-medium">{{ result.links?.length ?? 0 }}</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">اسکیما:</span><span class="font-medium">{{ result.schemas?.length ?? 0 }}</span></div>
            </div>
          </VCard>

          <VCard v-if="result.links?.length" title="🔗 لینک‌های داخلی">
            <div class="space-y-2">
              <div v-for="link in result.links.slice(0, 5)" :key="link.url" class="text-xs">
                <span class="text-brand-700">{{ link.anchor }}</span> → <span class="text-ink-muted">{{ link.url }}</span>
              </div>
            </div>
          </VCard>

          <VCard v-if="result.profile" title="🎯 پروفایل">
            <div class="space-y-1 text-xs">
              <div>نوع: {{ result.profile.content_type }}</div>
              <div>زیرنوع: {{ result.profile.subtype }}</div>
              <div>قصد: {{ result.profile.intent }}</div>
            </div>
          </VCard>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
