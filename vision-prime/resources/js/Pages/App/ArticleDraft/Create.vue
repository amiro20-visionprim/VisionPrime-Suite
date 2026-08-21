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
interface OutlineItem { heading: string; level: 2 | 3; note: string }
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

const step = ref<'input' | 'outline' | 'generating' | 'result'>('input')

const selectedSiteId = ref('')
const title = ref('')
const subtype = ref('how_to_guide')

const outline = ref<OutlineItem[]>([])
const outlineLoading = ref(false)
const outlineError = ref('')
const outlineModel = ref('')
const dragging = ref<number | null>(null)

const generatingLoading = ref(false)
const generatingStatus = ref('')
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

const outlineH2Count = computed(() => outline.value.filter(i => i.level === 2).length)
const outlineH3Count = computed(() => outline.value.filter(i => i.level === 3).length)

// SERP Intelligence
interface SerpCompetitor { title: string; url: string; headings: string[]; word_count: number; snippet: string }
interface SerpAnalysis {
  competitors: SerpCompetitor[]
  avg_word_count: number
  common_headings: string[]
  content_gaps: string[]
  recommendations: string[]
  model: string
}
const serpAnalysis = ref<SerpAnalysis | null>(null)
const serpLoading = ref(false)
const serpError = ref('')
const showSerpPanel = ref(false)

async function generateOutline() {
  if (!selectedSiteId.value || !title.value.trim()) return
  outlineLoading.value = true
  outlineError.value = ''
  try {
    const res = await fetch('/api/content/outline', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        site_id: Number(selectedSiteId.value),
        title: title.value.trim(),
        subtype: subtype.value || undefined,
      }),
    })
    const data = await res.json()
    if (data.error) {
      outlineError.value = data.error
      return
    }
    outline.value = data.outline ?? []
    outlineModel.value = data.model ?? ''
    if (outline.value.length === 0) {
      outlineError.value = 'Outline خالی برگشت — دوباره تلاش کنید'
      return
    }
    step.value = 'outline'
  } catch (e: unknown) {
    outlineError.value = 'خطا: ' + (e instanceof Error ? e.message : String(e))
  }
  outlineLoading.value = false
}

function addOutlineItem(level: 2 | 3) {
  outline.value.push({ heading: '', level, note: '' })
}

function removeOutlineItem(index: number) {
  outline.value.splice(index, 1)
}

function moveOutlineItem(index: number, direction: -1 | 1) {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= outline.value.length) return
  const temp = outline.value[index]
  outline.value[index] = outline.value[newIndex]
  outline.value[newIndex] = temp
}

function startDrag(index: number) {
  dragging.value = index
}

function onDragOver(event: DragEvent, index: number) {
  event.preventDefault()
  if (dragging.value === null || dragging.value === index) return
  const item = outline.value.splice(dragging.value, 1)[0]
  outline.value.splice(index, 0, item)
  dragging.value = index
}

function endDrag() {
  dragging.value = null
}

async function generateWithOutline() {
  if (outline.value.length === 0) return
  step.value = 'generating'
  generatingLoading.value = true
  generatingStatus.value = 'تحلیل outline و اعمال گاردرایل‌ها...'
  errorMsg.value = ''
  try {
    const res = await fetch('/api/content/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        site_id: Number(selectedSiteId.value),
        keyword: title.value.trim(),
        title: title.value.trim(),
        subtype: subtype.value || undefined,
        outline: outline.value.map(i => i.heading),
      }),
    })
    const data = await res.json()
    if (data.error) {
      errorMsg.value = data.error
      step.value = 'outline'
    } else {
      result.value = data
      step.value = 'result'
    }
  } catch (e: unknown) {
    errorMsg.value = 'خطا: ' + (e instanceof Error ? e.message : String(e))
    step.value = 'outline'
  }
  generatingLoading.value = false
}

async function analyzeSerp() {
  if (!title.value.trim()) return
  serpLoading.value = true
  serpError.value = ''
  try {
    const res = await fetch('/api/content/serp-analysis', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        keyword: title.value.trim(),
        subtype: subtype.value || undefined,
        outline: outline.value.map(i => ({ heading: i.heading, level: i.level })),
      }),
    })
    const data = await res.json()
    if (data.error) {
      serpError.value = data.error
    } else {
      serpAnalysis.value = data
      showSerpPanel.value = true
    }
  } catch (e: unknown) {
    serpError.value = 'خطا: ' + (e instanceof Error ? e.message : String(e))
  }
  serpLoading.value = false
}

function addSerpHeading(heading: string) {
  // Add a heading from SERP analysis to the outline
  outline.value.push({ heading: heading.replace(/^H[23]:\s*/, ''), level: 2, note: 'از تحلیل رقبا' })
}

function goToInput() {
  step.value = 'input'
  result.value = null
  outline.value = []
  serpAnalysis.value = null
  showSerpPanel.value = false
  errorMsg.value = ''
}

function goToOutline() {
  step.value = 'outline'
  result.value = null
  errorMsg.value = ''
}

function regenerate() {
  result.value = null
  step.value = 'outline'
}

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
    <VPageHeader title="تولید مقاله هوشمند" description="با وارد کردن عنوان، outline پیشنهادی را بررسی و سپس مقاله را تولید کنید." />

    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">{{ page.props.flash.status }}</VAlert>
    <VAlert v-if="errorMsg" tone="danger" class="mt-6">{{ errorMsg }}</VAlert>

    <!-- Breadcrumb -->
    <div class="mt-6 flex items-center gap-2 text-sm text-ink-muted">
      <span :class="step === 'input' ? 'font-bold text-brand-700' : ''">۱. ورودی</span>
      <span>›</span>
      <span :class="step === 'outline' ? 'font-bold text-brand-700' : ''">۲. Outline</span>
      <span>›</span>
      <span :class="step === 'generating' || step === 'result' ? 'font-bold text-brand-700' : ''">۳. نتیجه</span>
    </div>

    <!-- STEP 1: INPUT -->
    <div v-if="step === 'input'" class="mt-6 max-w-2xl mx-auto">
      <VCard title="عنوان مقاله را وارد کنید">
        <div class="space-y-4">
          <VSelect v-model="selectedSiteId" label="سایت" :options="p.sites.map(s => ({ label: s.name, value: String(s.id) }))" placeholder="انتخاب سایت" />
          <div>
            <label class="text-ink-strong text-sm font-semibold">عنوان مقاله</label>
            <input v-model="title" type="text" dir="auto" class="border-line mt-2 w-full rounded-xl border px-4 py-3 text-lg focus:ring-2 focus:ring-brand-500" placeholder="مثال: راهنمای جامع سئو برای سایت فروشگاهی" @keyup.enter="generateOutline" />
            <p class="text-ink-muted mt-1 text-xs">بعد از وارد کردن عنوان، سیستم ابتدا outline (ساختار) پیشنهادی را نمایش می‌دهد.</p>
          </div>
          <VSelect v-model="subtype" label="زیرنوع" :options="Object.entries(p.subtypes).map(([v,l]) => ({label:l, value:v}))" />
          <VButton @click="generateOutline" :loading="outlineLoading" :disabled="!selectedSiteId || !title.trim()" variant="primary" size="lg" class="w-full">
            <span v-if="!outlineLoading">تولید Outline</span>
            <span v-else>در حال تحلیل...</span>
          </VButton>
          <VAlert v-if="outlineError" tone="danger">{{ outlineError }}</VAlert>
        </div>
      </VCard>
    </div>

    <!-- STEP 2: OUTLINE EDITOR -->
    <div v-if="step === 'outline'" class="mt-6 max-w-3xl mx-auto">
      <VCard title="ساختار پیشنهادی مقاله">
        <div class="mb-4 flex items-center gap-3 text-sm text-ink-muted">
          <span>H2: {{ outlineH2Count }}</span>
          <span>H3: {{ outlineH3Count }}</span>
          <span>کل: {{ outline.length }}</span>
          <VBadge tone="info" size="sm">مدل: {{ outlineModel }}</VBadge>
        </div>
        <div class="space-y-2">
          <div v-for="(item, index) in outline" :key="index"
            class="group flex items-center gap-2 rounded-xl border border-surface-muted bg-surface p-3 transition-all hover:border-brand-300"
            :class="{ 'opacity-50': dragging === index }"
            :style="{ paddingLeft: item.level === 3 ? '2.5rem' : '1rem' }"
            draggable="true"
            @dragstart="startDrag(index)"
            @dragover="(e: DragEvent) => onDragOver(e, index)"
            @dragend="endDrag">
            <span class="cursor-grab text-ink-muted opacity-0 transition-opacity group-hover:opacity-100" title="جابجایی">⠿</span>
            <VBadge :tone="item.level === 2 ? 'brand' : 'info'" size="sm">H{{ item.level }}</VBadge>
            <input v-model="item.heading" dir="auto" class="flex-1 bg-transparent text-sm font-medium outline-none" :placeholder="item.level === 2 ? 'عنوان بخش اصلی...' : 'عنوان زیربخش...'" />
            <input v-model="item.note" dir="auto" class="w-48 bg-transparent text-xs text-ink-muted outline-none placeholder:text-ink-muted/50" placeholder="توضیح (اختیاری)" />
            <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
              <button type="button" class="rounded p-1 text-ink-muted hover:bg-surface-muted" @click="moveOutlineItem(index, -1)" title="بالا">↑</button>
              <button type="button" class="rounded p-1 text-ink-muted hover:bg-surface-muted" @click="moveOutlineItem(index, 1)" title="پایین">↓</button>
              <button type="button" class="rounded p-1 text-ink-muted hover:bg-red-100 hover:text-red-600" @click="removeOutlineItem(index)" title="حذف">✕</button>
            </div>
          </div>
        </div>
        <div class="mt-4 flex gap-2">
          <VButton variant="secondary" size="sm" @click="addOutlineItem(2)">+ افزودن H2</VButton>
          <VButton variant="secondary" size="sm" @click="addOutlineItem(3)">+ افزودن H3</VButton>
        </div>
        <div class="mt-6 flex items-center justify-between border-t border-surface-muted pt-4">
          <div class="flex items-center gap-2">
            <VButton variant="secondary" @click="goToInput">بازگشت</VButton>
            <VButton variant="secondary" size="sm" @click="analyzeSerp" :loading="serpLoading" :disabled="!title.trim()">
              🔍 تحلیل رقبا (SERP)
            </VButton>
          </div>
          <VButton variant="primary" size="lg" :disabled="outline.length === 0 || outline.some(i => !i.heading.trim())" :loading="generatingLoading" @click="generateWithOutline">
            <span v-if="!generatingLoading">تولید مقاله بر اساس Outline</span>
            <span v-else>در حال تولید...</span>
          </VButton>
        </div>
      </VCard>

      <!-- SERP Intelligence Panel -->
      <VCard v-if="showSerpPanel && serpAnalysis" class="mt-4">
        <template #title>
          <div class="flex items-center justify-between">
            <span>🔍 تحلیل رقبا (SERP Intelligence)</span>
            <VBadge tone="info" size="sm">مدل: {{ serpAnalysis.model }}</VBadge>
          </div>
        </template>

        <!-- Competitors -->
        <div class="space-y-3">
          <h4 class="text-ink-strong text-sm font-semibold">صفحات برتر رقبا:</h4>
          <div v-for="(comp, i) in serpAnalysis.competitors" :key="i" class="rounded-xl border border-surface-muted bg-surface p-3">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-ink-strong text-sm font-medium">{{ comp.title }}</p>
                <p class="text-ink-muted text-xs">{{ comp.url }} · {{ comp.word_count }} کلمه</p>
              </div>
            </div>
            <p class="text-ink-muted mt-1 text-xs">{{ comp.snippet }}</p>
            <div class="mt-2 flex flex-wrap gap-1">
              <VBadge v-for="h in comp.headings.slice(0, 6)" :key="h" tone="brand" size="sm">{{ h }}</VBadge>
            </div>
          </div>
        </div>

        <!-- Common Headings -->
        <div class="mt-4">
          <h4 class="text-ink-strong text-sm font-semibold">عنوان‌های مشترک رقبا:</h4>
          <div class="mt-2 flex flex-wrap gap-1">
            <button v-for="h in serpAnalysis.common_headings" :key="h" type="button"
              class="rounded-full border border-brand-300 bg-brand-50 px-3 py-1 text-xs text-brand-700 hover:bg-brand-100 transition-colors"
              @click="addSerpHeading(h)">
              + {{ h }}
            </button>
          </div>
        </div>

        <!-- Content Gaps -->
        <div v-if="serpAnalysis.content_gaps.length > 0" class="mt-4">
          <h4 class="text-ink-strong text-sm font-semibold">⚠️ شکاف‌های محتوایی:</h4>
          <ul class="mt-2 space-y-1">
            <li v-for="gap in serpAnalysis.content_gaps" :key="gap" class="text-yellow-600 text-xs">• {{ gap }}</li>
          </ul>
        </div>

        <!-- Recommendations -->
        <div v-if="serpAnalysis.recommendations.length > 0" class="mt-4">
          <h4 class="text-ink-strong text-sm font-semibold">💡 پیشنهادات:</h4>
          <ul class="mt-2 space-y-1">
            <li v-for="rec in serpAnalysis.recommendations" :key="rec" class="text-green-600 text-xs">✓ {{ rec }}</li>
          </ul>
        </div>

        <!-- Avg word count -->
        <div class="mt-4 text-sm text-ink-muted">
          میانگین کلمات رقبا: <span class="font-bold text-ink-strong">{{ serpAnalysis.avg_word_count }}</span> کلمه
        </div>
      </VCard>

      <VAlert v-if="serpError" tone="danger" class="mt-4">{{ serpError }}</VAlert>

      <VCard class="mt-4" title="نکات">
        <ul class="space-y-1 text-xs text-ink-muted">
          <li>عنوان‌ها را می‌توانید ویرایش، حذف یا جابجا کنید.</li>
          <li>H2 برای بخش‌های اصلی و H3 برای زیربخش‌هاست.</li>
          <li>کشیدن و رها کردن (drag and drop) برای جابجایی سریع.</li>
          <li>بعد از تایید، مقاله دقیقاً بر اساس این ساختار تولید می‌شود.</li>
        </ul>
      </VCard>
    </div>

    <!-- STEP 3: GENERATING -->
    <div v-if="step === 'generating'" class="mt-6 max-w-2xl mx-auto text-center py-16">
      <div class="text-6xl mb-4 animate-pulse">🤖</div>
      <h2 class="text-xl font-bold text-ink-strong">در حال تولید مقاله...</h2>
      <p class="text-ink-muted mt-2">{{ generatingStatus }}</p>
      <div class="mt-8 space-y-2 text-sm text-ink-muted">
        <p>✅ تحلیل outline پیشنهادی</p>
        <p>✅ اعمال گاردرایل‌ها و استانداردها</p>
        <p>✅ تحلیل داده GSC</p>
        <p class="animate-pulse">در انتظار تولید مقاله با AI...</p>
      </div>
    </div>

    <!-- STEP 4: RESULT -->
    <div v-if="step === 'result' && result" class="mt-6">
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
            <VBadge :tone="seoScore >= 70 ? 'success' : seoScore >= 40 ? 'warning' : 'danger'" size="lg">امتیاز: {{ seoScore }}/100</VBadge>
            <VButton @click="goToOutline" variant="secondary">بازگشت به Outline</VButton>
            <VButton @click="regenerate" variant="secondary">🔄 تولید مجدد</VButton>
          </div>
        </div>
      </VCard>
      <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
          <div class="border-line flex gap-1 border-b">
            <button v-for="tab in [{id:'content',label:'✏️ محتوا'}, {id:'meta',label:'🏷️ Meta'}, {id:'seo',label:'📊 امتیاز'}, {id:'schema',label:'📊 اسکیما'}]" :key="tab.id" type="button" class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors" :class="activeResultTab === tab.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-muted hover:text-ink-strong'" @click="activeResultTab = tab.id">{{ tab.label }}</button>
          </div>
          <VCard v-if="activeResultTab === 'content'">
            <div class="prose prose-sm max-w-none" dir="auto" v-html="result.content" />
          </VCard>
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
          <VCard v-if="activeResultTab === 'seo'">
            <div class="space-y-3">
              <div v-for="check in seoChecks" :key="check.label" class="flex items-center justify-between">
                <span class="text-ink-strong text-sm">{{ check.label }}</span>
                <VBadge :tone="check.passed ? 'success' : 'danger'">{{ check.value }}</VBadge>
              </div>
              <div v-if="result.quality?.failures?.length" class="mt-4">
                <p class="text-red-600 text-sm font-semibold">مشکلات:</p>
                <ul class="mt-1 space-y-1"><li v-for="f in result.quality.failures" :key="f" class="text-red-500 text-xs">• {{ f }}</li></ul>
              </div>
              <div v-if="result.quality?.warnings?.length" class="mt-4">
                <p class="text-yellow-600 text-sm font-semibold">نکات:</p>
                <ul class="mt-1 space-y-1"><li v-for="w in result.quality.warnings" :key="w" class="text-yellow-500 text-xs">• {{ w }}</li></ul>
              </div>
            </div>
          </VCard>
          <VCard v-if="activeResultTab === 'schema'">
            <div class="space-y-2">
              <div v-for="(schema, i) in (result.schemas || [])" :key="i">
                <pre class="bg-surface-muted rounded-xl p-4 text-xs overflow-x-auto" dir="ltr">{{ JSON.stringify(schema, null, 2) }}</pre>
              </div>
            </div>
          </VCard>
        </div>
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
