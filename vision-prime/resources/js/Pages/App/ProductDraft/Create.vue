<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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
interface QualityResult { passed: boolean; score: number; failures: string[]; warnings: string[]; readability?: any }
interface LinkSuggestion { url: string; title: string; anchor: string; relevance_score: number }
interface GeneratedResult {
  content: string; model: string; source: string;
  meta_title: string; meta_description: string;
  schemas: SchemaItem[]; links: LinkSuggestion[];
  quality: QualityResult; profile: any; draft_id?: number; expert_analysis?: any
}
interface PromptTemplate { id: number; title: string; content_type: string; tone: string; is_user_created: boolean; tags: string[]; usage_count: number; avg_quality_score: number; is_featured: boolean }
interface SectionItem { heading: string; level: number; content: string; regenerating: boolean }

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
const price = ref('')
const salePrice = ref('')
const stockStatus = ref('in_stock')
const shortDesc = ref('')

// Templates
const templates = ref<PromptTemplate[]>([])
const selectedTemplateId = ref<number | null>(null)
const customPrompt = ref('')
const showCustomPrompt = ref(false)
const templatesLoading = ref(false)

// Outline
const outline = ref<OutlineItem[]>([])
const outlineLoading = ref(false)
const outlineError = ref('')
const outlineModel = ref('')

// Generation
const generatingLoading = ref(false)
const generatingStatus = ref('')
const result = ref<GeneratedResult | null>(null)
const activeResultTab = ref<'content' | 'meta' | 'seo' | 'schema' | 'sections'>('content')
const errorMsg = ref('')

// Draft
const currentDraftId = ref<number | null>(null)
const draftSaved = ref(false)

// Publish
const wpUrl = ref('')
const wpUser = ref('')
const wpPass = ref('')
const publishing = ref(false)
const publishResult = ref<any>(null)
const showPublishDialog = ref(false)
const copyStatus = ref('')

// Sections
const sections = ref<SectionItem[]>([])
const showSectionEdit = ref(false)
const editSectionIndex = ref<number | null>(null)
const editSectionText = ref('')

// Auto detect
const autoDetectedSubtype = ref('')
const autoDetectedTone = ref('')

// Keyword
const keywordInput = ref('')
const applyingSuggestions = ref(false)

// Computed
const wordCount = computed(() => {
  if (!result.value?.content) return 0
  const plain = result.value.content.replace(/<[^>]+>/g, ' ').trim()
  return plain ? plain.split(/\s+/).filter((w: string) => w.length > 0).length : 0
})
const seoScore = computed(() => result.value?.quality?.score ?? 0)
const readability = computed(() => result.value?.quality?.readability ?? null)

const keywordDensity = computed(() => {
  const kw = keywordInput.value.trim()
  if (!kw || !result.value?.content) return { count: 0, density: 0 }
  const plain = result.value.content.replace(/<[^>]+>/g, ' ').trim().toLowerCase()
  const kwLower = kw.toLowerCase()
  let count = 0, pos = 0
  while ((pos = plain.indexOf(kwLower, pos)) !== -1) { count++; pos += kwLower.length }
  const words = plain.split(/\s+/).filter((w: string) => w.length > 0)
  return { count, density: words.length > 0 ? Math.round(count / words.length * 10000) / 100 : 0 }
})

const seoChecks = computed(() => {
  if (!result.value) return []
  const r = result.value
  return [
    { label: 'طول محتوا', value: wordCount.value + ' کلمه', passed: wordCount.value >= 80 },
    { label: 'Meta Title', value: (r.meta_title?.length ?? 0) + '/60', passed: (r.meta_title?.length ?? 0) >= 30 && (r.meta_title?.length ?? 0) <= 60 },
    { label: 'Meta Description', value: (r.meta_description?.length ?? 0) + '/160', passed: (r.meta_description?.length ?? 0) >= 120 && (r.meta_description?.length ?? 0) <= 160 },
    { label: 'اسکیما Product', value: (r.schemas?.length ?? 0) + ' عدد', passed: (r.schemas?.length ?? 0) >= 1 },
    { label: 'امتیاز کیفیت', value: (r.quality?.score ?? 0) + '/100', passed: (r.quality?.score ?? 0) >= 70 },
    { label: 'قیمت', value: price.value ? price.value + ' ریال' : 'ناموجود', passed: !!price.value },
    { label: 'موجودی', value: stockStatus.value === 'in_stock' ? 'در انبار' : 'ناموجود', passed: true },
  ]
})

// Template functions
async function fetchTemplates() {
  templatesLoading.value = true
  try {
    const res = await fetch('/api/content/prompt-templates?content_type=product', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    if (res.ok) templates.value = await res.json()
  } catch {}
  templatesLoading.value = false
}
fetchTemplates()

function selectTemplate(t: PromptTemplate) {
  selectedTemplateId.value = selectedTemplateId.value === t.id ? null : t.id
}

// Auto detect subtype from title
function autoDetect(t: string) {
  const l = t.toLowerCase()
  if (/compare|comparison|مقایسه|vs|بهترین/.test(l)) { autoDetectedSubtype.value = 'comparison'; autoDetectedTone.value = 'neutral' }
  else if (/review|نقد|بررسی/.test(l)) { autoDetectedSubtype.value = 'review'; autoDetectedTone.value = 'professional' }
  else if (/buy|price|sale|خرید|قیمت|فروش/.test(l)) { autoDetectedSubtype.value = 'long_desc'; autoDetectedTone.value = 'persuasive' }
  else if (/spec|مشخصات|فنی/.test(l)) { autoDetectedSubtype.value = 'specs'; autoDetectedTone.value = 'neutral' }
  else { autoDetectedSubtype.value = 'short_desc'; autoDetectedTone.value = 'persuasive' }
}

watch(title, (v) => { if (v && v.trim().length > 5) autoDetect(v) })

// Generate Outline
async function generateOutline() {
  if (!selectedSiteId.value || !title.value.trim()) return
  outlineLoading.value = true; outlineError.value = ''
  try {
    const res = await fetch('/api/content/outline', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), title: title.value.trim(), subtype: autoDetectedSubtype.value || undefined, template_id: selectedTemplateId.value || undefined, custom_prompt: customPrompt.value || undefined, tone: autoDetectedTone.value || undefined })
    })
    const data = await res.json()
    if (data.error) { outlineError.value = data.error; outlineLoading.value = false; return }
    outline.value = data.outline ?? []
    outlineModel.value = data.model ?? ''
    if (outline.value.length === 0) { outlineError.value = 'Outline خالی برگشت'; outlineLoading.value = false; return }
    step.value = 'outline'
  } catch (e: any) { outlineError.value = 'خطا: ' + e.message }
  outlineLoading.value = false
}

// Generate Article (from outline)
async function generateFromOutline() {
  if (!selectedSiteId.value || !title.value.trim()) return
  generatingLoading.value = true; generatingStatus.value = 'تولید محتوای محصول...';
  step.value = 'generating'
  try {
    const res = await fetch('/api/content/generate', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), keyword: title.value.trim(), title: title.value.trim(), subtype: autoDetectedSubtype.value || undefined, template_id: selectedTemplateId.value || undefined, custom_prompt: customPrompt.value || undefined, tone: autoDetectedTone.value || undefined })
    })
    const d = await res.json()
    if (d.error) { errorMsg.value = d.error; step.value = 'input'; generatingLoading.value = false; return }
    result.value = d; currentDraftId.value = d.draft_id || null; activeResultTab.value = 'content'; step.value = 'result'; parseSections(d.content)
  } catch (e: any) { errorMsg.value = 'خطا: ' + e.message; step.value = 'input' }
  generatingLoading.value = false
}

// Quick Generate (skip outline)
async function quickGenerate() {
  if (!selectedSiteId.value || !title.value.trim()) return
  generatingLoading.value = true; generatingStatus.value = 'تولید سریع محصول...';
  step.value = 'generating'
  try {
    const res = await fetch('/api/content/generate', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), keyword: title.value.trim(), title: title.value.trim(), subtype: autoDetectedSubtype.value || undefined, template_id: selectedTemplateId.value || undefined, custom_prompt: customPrompt.value || undefined, tone: autoDetectedTone.value || undefined })
    })
    const d = await res.json()
    if (d.error) { errorMsg.value = d.error; step.value = 'input'; generatingLoading.value = false; return }
    result.value = d; currentDraftId.value = d.draft_id || null; activeResultTab.value = 'content'; step.value = 'result'; parseSections(d.content)
  } catch (e: any) { errorMsg.value = 'خطا: ' + e.message; step.value = 'input' }
  generatingLoading.value = false
}

function regenerate() { step.value = 'input'; result.value = null; currentDraftId.value = null }
function goToOutline() { step.value = 'outline' }

// Section parsing
function parseSections(html: string) {
  const re = /<h([2-3])[^>]*>(.*?)<\/h\1>/gi
  const parts: SectionItem[] = []
  let lastIdx = 0
  let m: RegExpExecArray | null
  while ((m = re.exec(html)) !== null) {
    if (parts.length > 0) parts[parts.length - 1].content = html.substring(lastIdx, m.index)
    parts.push({ heading: m[2].replace(/<[^>]+>/g, ''), level: parseInt(m[1]), content: '', regenerating: false })
    lastIdx = re.lastIndex
  }
  if (parts.length > 0) parts[parts.length - 1].content = html.substring(lastIdx)
  sections.value = parts
}

function editSection(i: number) { editSectionIndex.value = i; editSectionText.value = sections.value[i].content; showSectionEdit.value = true }
function saveSectionEdit() {
  if (editSectionIndex.value === null || !result.value) return
  const s = sections.value[editSectionIndex.value]
  s.content = editSectionText.value
  // Rebuild content
  let html = result.value.content
  const re = new RegExp(`(<h${s.level}[^>]*>${s.heading.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}<\\/h${s.level}>)[\\s\\S]*?(?=<h[2-3]|$)`, 'i')
  result.value.content = html.replace(re, `$1\n${editSectionText.value}`)
  parseSections(result.value.content)
  showSectionEdit.value = false
}

async function regenerateSection(i: number) {
  const s = sections.value[i]
  s.regenerating = true
  try {
    const res = await fetch('/api/content/regenerate-section', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ content: result.value?.content || '', heading: s.heading, keyword: title.value })
    })
    const d = await res.json()
    if (d.content) { result.value!.content = d.content; parseSections(d.content) }
  } catch {}
  s.regenerating = false
}

// Save / Publish
async function saveCurrentDraft() {
  if (!result.value || !selectedSiteId.value) return
  try {
    const r = await fetch('/api/content/drafts', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ draft_id: currentDraftId.value || undefined, site_id: Number(selectedSiteId.value), title: title.value, content: result.value.content, meta_title: result.value.meta_title, meta_description: result.value.meta_description, subtype: autoDetectedSubtype.value || 'short_desc', quality_score: result.value.quality?.score || 0 })
    })
    if (r.ok) { const d = await r.json(); currentDraftId.value = d.id; draftSaved.value = true }
  } catch {}
}

async function copyHtml() {
  if (!result.value?.content) return
  try { await navigator.clipboard.writeText(result.value.content); copyStatus.value = 'HTML'; setTimeout(() => copyStatus.value = '', 2000) } catch {}
}
async function copyPlainText() {
  if (!result.value?.content) return
  const text = result.value.content.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
  try { await navigator.clipboard.writeText(text); copyStatus.value = 'TEXT'; setTimeout(() => copyStatus.value = '', 2000) } catch {}
}

async function publishToWordPress(status: string) {
  if (!result.value || !wpUrl.value || !wpUser.value || !wpPass.value) return
  publishing.value = true; publishResult.value = null
  try {
    let draftId = currentDraftId.value
    if (!draftId) {
      const dr = await fetch('/api/content/drafts?search=' + encodeURIComponent(title.value), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      const dd = await dr.json(); draftId = dd.drafts?.[0]?.id
    }
    if (!draftId) { publishResult.value = { success: false, error: 'Draft یافت نشد' }; publishing.value = false; return }
    const res = await fetch('/api/content/publish', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ draft_id: draftId, status, wp_url: wpUrl.value, wp_username: wpUser.value, wp_app_password: wpPass.value })
    })
    publishResult.value = await res.json()
  } catch (e: any) { publishResult.value = { success: false, error: e.message } }
  publishing.value = false
}

async function applySuggestions(suggestions: string[]) {
  if (!result.value || suggestions.length === 0) return; applyingSuggestions.value = true
  try {
    const r = await fetch('/api/content/apply-suggestions', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ content: result.value.content, suggestions, title: title.value, keyword: title.value })
    })
    const d = await r.json(); if (d.content) { result.value.content = d.content; parseSections(d.content) }
  } catch {}
  applyingSuggestions.value = false
}
</script>

<template>
  <Head title="تولید محصول هوشمند" />
  <AppLayout>
    <VPageHeader title="تولید محصول هوشمند" description="توضیح محصول با Meta Title/Description، اسکیمای Product و لینک‌سازی داخلی." />

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
      <VCard title="مشخصات محصول را وارد کنید">
        <div class="space-y-4">
          <VSelect v-model="selectedSiteId" label="سایت" :options="p.sites.map(s => ({ label: s.name, value: String(s.id) }))" placeholder="انتخاب سایت" />

          <div>
            <label class="text-ink-strong text-sm font-semibold">عنوان محصول</label>
            <input v-model="title" dir="auto" placeholder="مثال: هدفون بی‌سیم پرو مکس" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
            <p class="text-ink-muted mt-1 text-xs">بعد از وارد کردن عنوان، سیستم خودکار زیرنوع و لحن را تشخیص می‌دهد.</p>
          </div>

          <!-- Auto detect badge -->
          <div v-if="autoDetectedSubtype" class="flex items-center gap-2 text-xs">
            <span class="text-ink-muted">تشخیص خودکار:</span>
            <VBadge tone="info" size="sm">{{ autoDetectedSubtype }}</VBadge>
            <VBadge tone="success" size="sm">{{ autoDetectedTone }}</VBadge>
          </div>

          <!-- Product-specific fields -->
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="text-ink-strong text-sm font-semibold">قیمت (ریال)</label>
              <input v-model="price" dir="ltr" placeholder="2500000" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="text-ink-strong text-sm font-semibold">قیمت تخفیفی</label>
              <input v-model="salePrice" dir="ltr" placeholder="اختیاری" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="text-ink-strong text-sm font-semibold">وضعیت موجودی</label>
              <select v-model="stockStatus" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm">
                <option value="in_stock">در انبار</option>
                <option value="out_of_stock">ناموجود</option>
              </select>
            </div>
          </div>

          <!-- Prompt Template -->
          <div>
            <label class="text-ink-strong text-sm font-semibold">قالب پرامپت (اختیاری)</label>
            <p class="text-ink-muted text-xs mb-2">یک قالب حرفه‌ای انتخاب کنید یا خودتان پرامپت بنویسید.</p>
            <div class="grid grid-cols-2 gap-2">
              <button v-for="t in templates" :key="t.id" type="button"
                class="rounded-xl border p-3 text-right text-sm transition-all"
                :class="selectedTemplateId === t.id ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-200' : 'border-surface-muted hover:border-brand-300'"
                @click="selectTemplate(t)">
                <div class="flex items-center justify-between">
                  <span class="font-medium text-ink-strong">{{ t.title }}</span>
                  <VBadge v-if="t.is_featured" tone="warning" size="sm">⭐</VBadge>
                </div>
                <div class="mt-1 text-xs text-ink-muted">{{ t.tone }}</div>
              </button>
            </div>
            <button type="button" class="mt-2 text-brand-700 text-xs" @click="showCustomPrompt = !showCustomPrompt">
              {{ showCustomPrompt ? 'بستن پرامپت اختیاری' : '✏️ نوشتن پرامپت اختیاری' }}
            </button>
            <textarea v-if="showCustomPrompt" v-model="customPrompt" dir="auto" rows="4" class="border-line mt-2 w-full rounded-xl border px-4 py-2.5 text-sm" placeholder="پرامپت اختیاری خود را اینجا بنویسید..." />
          </div>

          <div class="flex gap-2">
            <VButton @click="quickGenerate" :loading="generatingLoading" :disabled="!selectedSiteId || !title.trim()" variant="primary" size="lg" class="flex-1">
              {{ generatingLoading ? 'در حال تولید...' : '⚡ تولید سریع' }}
            </VButton>
            <VButton @click="generateOutline" :loading="outlineLoading" :disabled="!selectedSiteId || !title.trim()" variant="secondary" size="lg" class="flex-1">
              {{ outlineLoading ? 'در حال تحلیل...' : '📋 با Outline' }}
            </VButton>
          </div>
          <VAlert v-if="outlineError" tone="danger">{{ outlineError }}</VAlert>
        </div>
      </VCard>
    </div>

    <!-- STEP 2: OUTLINE -->
    <div v-if="step === 'outline'" class="mt-6 max-w-3xl mx-auto">
      <VCard title="ساختار پیشنهادی محصول">
        <div class="mb-4 flex items-center gap-3 text-sm text-ink-muted">
          <span>مدل: <VBadge tone="info" size="sm">{{ outlineModel }}</VBadge></span>
          <span>{{ outline.length }} بخش</span>
        </div>
        <div class="space-y-2">
          <div v-for="(item, i) in outline" :key="i"
            class="rounded-xl border border-surface-muted bg-surface p-3 transition-all hover:border-brand-300"
            :class="item.level === 3 ? 'me-6' : ''">
            <div class="flex items-center gap-2">
              <VBadge :tone="item.level <= 2 ? 'brand' : 'info'" size="sm">H{{ item.level }}</VBadge>
              <span class="text-ink-strong text-sm font-medium">{{ item.heading }}</span>
            </div>
            <p v-if="item.note" class="mt-1 text-xs text-ink-muted">{{ item.note }}</p>
          </div>
        </div>
        <div class="mt-4 flex gap-2">
          <VButton @click="generateFromOutline" :loading="generatingLoading" variant="primary" size="lg">🚀 تولید محتوا</VButton>
          <VButton @click="goToOutline" variant="secondary">بازگشت</VButton>
        </div>
      </VCard>
    </div>

    <!-- STEP 3: GENERATING -->
    <div v-if="step === 'generating'" class="mt-6 max-w-2xl mx-auto text-center py-16">
      <div class="text-6xl mb-4 animate-pulse">🤖</div>
      <h2 class="text-xl font-bold text-ink-strong">در حال تولید توضیح محصول...</h2>
      <p class="text-ink-muted mt-2">{{ generatingStatus }}</p>
      <div class="mt-8 space-y-2 text-sm text-ink-muted">
        <p>✅ تحلیل ساختار پیشنهادی</p>
        <p>✅ اعمال گاردرایل‌ها و استانداردها</p>
        <p class="animate-pulse">در انتظار تولید محتوا با AI...</p>
      </div>
    </div>

    <!-- STEP 4: RESULT -->
    <div v-if="step === 'result' && result" class="mt-6">
      <VCard class="mb-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">✅</span>
            <div>
              <h3 class="font-bold text-ink-strong">توضیح محصول تولید شد!</h3>
              <p class="text-ink-muted text-sm">مدل: {{ result.model }} | منبع: {{ result.source }} | کلمات: {{ wordCount }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <VBadge :tone="seoScore >= 70 ? 'success' : seoScore >= 40 ? 'warning' : 'danger'" size="lg">امتیاز: {{ seoScore }}/100</VBadge>
            <VButton @click="goToOutline" variant="secondary">بازگشت</VButton>
            <VButton @click="regenerate" variant="secondary">🔄 تولید مجدد</VButton>
          </div>
        </div>
      </VCard>

      <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
          <div class="border-line flex gap-1 border-b">
            <button v-for="tab in [{id:'content',label:'✏️ محتوا'}, {id:'meta',label:'🏷️ Meta'}, {id:'seo',label:'📊 امتیاز'}, {id:'schema',label:'📊 اسکیما'}, {id:'sections',label:'📝 ویرایش بخش‌ها'}]" :key="tab.id" type="button" class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors" :class="activeResultTab === tab.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-muted hover:text-ink-strong'" @click="activeResultTab = tab.id">{{ tab.label }}</button>
          </div>

          <!-- Content Tab -->
          <VCard v-if="activeResultTab === 'content'">
            <div class="prose prose-sm max-w-none" dir="auto" v-html="result.content" />
          </VCard>

          <!-- Meta Tab -->
          <VCard v-if="activeResultTab === 'meta'">
            <div class="space-y-4">
              <div>
                <label class="text-ink-strong text-sm font-semibold">Meta Title</label>
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

          <!-- Schema Tab -->
          <VCard v-if="activeResultTab === 'schema'">
            <div class="space-y-2">
              <div v-for="(schema, i) in (result.schemas || [])" :key="i">
                <pre class="bg-surface-muted rounded-xl p-4 text-xs overflow-x-auto" dir="ltr">{{ JSON.stringify(schema, null, 2) }}</pre>
              </div>
            </div>
          </VCard>

          <!-- Section Edit Tab -->
          <VCard v-if="activeResultTab === 'sections'">
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <h4 class="text-ink-strong text-sm font-semibold">ویرایش بخش‌به‌بخش</h4>
                <span class="text-ink-muted text-xs">{{ sections.length }} بخش</span>
              </div>
              <div v-for="(sec, i) in sections" :key="i" class="rounded-xl border border-surface-muted bg-surface p-3 transition-all hover:border-brand-300">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <VBadge :tone="sec.level <= 2 ? 'brand' : 'info'" size="sm">H{{ sec.level }}</VBadge>
                    <span class="text-ink-strong text-sm font-medium">{{ sec.heading }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <button type="button" class="rounded p-1.5 text-ink-muted hover:bg-surface-muted text-xs" @click="editSection(i)">✏️</button>
                    <button type="button" class="rounded p-1.5 text-ink-muted hover:bg-blue-100 hover:text-blue-600 text-xs" @click="regenerateSection(i)" :disabled="sec.regenerating">
                      <span v-if="sec.regenerating" class="animate-pulse">⏳</span><span v-else>🔄</span>
                    </button>
                  </div>
                </div>
                <div class="mt-2 text-xs text-ink-muted max-h-20 overflow-hidden" dir="auto">{{ sec.content.replace(/<[^>]+>/g, ' ').substring(0, 200) }}...</div>
              </div>
            </div>
          </VCard>

          <!-- Section Edit Modal -->
          <div v-if="showSectionEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-auto p-6">
              <div class="flex items-center justify-between mb-4">
                <h4 class="font-bold text-ink-strong">ویرایش بخش: {{ editSectionIndex !== null ? sections[editSectionIndex]?.heading : '' }}</h4>
                <button type="button" class="text-ink-muted hover:text-ink-strong" @click="showSectionEdit = false">✕</button>
              </div>
              <textarea v-model="editSectionText" dir="auto" rows="12" class="border-line w-full rounded-xl border px-4 py-3 text-sm" />
              <div class="flex gap-2 mt-4 justify-end">
                <VButton variant="secondary" @click="showSectionEdit = false">لغو</VButton>
                <VButton variant="primary" @click="saveSectionEdit">ذخیره</VButton>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <VCard title="📊 خلاصه">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-ink-muted">کلمات:</span><span class="font-medium">{{ wordCount }}</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">امتیاز:</span><span class="font-medium">{{ seoScore }}/100</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">لینک‌ها:</span><span class="font-medium">{{ result.links?.length ?? 0 }}</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">اسکیما:</span><span class="font-medium">{{ result.schemas?.length ?? 0 }}</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">قیمت:</span><span class="font-medium">{{ price ? price + ' ریال' : '—' }}</span></div>
              <div class="flex justify-between"><span class="text-ink-muted">موجودی:</span><VBadge :tone="stockStatus === 'in_stock' ? 'success' : 'danger'" size="sm">{{ stockStatus === 'in_stock' ? 'در انبار' : 'ناموجود' }}</VBadge></div>
            </div>
          </VCard>

          <!-- Keyword Density -->
          <VCard title="🎯 تراکم کلیدواژه">
            <div class="space-y-3">
              <input v-model="keywordInput" dir="auto" class="border-line w-full rounded-xl border px-3 py-2 text-sm" placeholder="کلیدواژه را وارد کنید..." />
              <div v-if="keywordInput.trim() && result?.content" class="space-y-2">
                <div class="flex items-center justify-between"><span class="text-ink-muted text-xs">تعداد تکرار:</span><span class="font-bold text-ink-strong">{{ keywordDensity.count }}</span></div>
                <div class="flex items-center justify-between"><span class="text-ink-muted text-xs">تراکم:</span><VBadge :tone="keywordDensity.density >= 1 && keywordDensity.density <= 3 ? 'success' : keywordDensity.density > 3 ? 'danger' : 'warning'" size="sm">{{ keywordDensity.density }}%</VBadge></div>
              </div>
            </div>
          </VCard>

          <!-- Expert Analysis -->
          <VCard v-if="result.expert_analysis" title="🧠 تحلیل متخصص SEO">
            <div class="space-y-3">
              <p class="text-ink-strong text-sm font-medium">{{ result.expert_analysis.summary }}</p>
              <div v-if="result.expert_analysis.strengths?.length" class="space-y-1">
                <p class="text-green-600 text-xs font-semibold">✅ نقاط قوت:</p>
                <p v-for="s in result.expert_analysis.strengths" :key="s" class="text-green-500 text-xs">• {{ s }}</p>
              </div>
              <div v-if="result.expert_analysis.weaknesses?.length" class="space-y-1">
                <p class="text-red-600 text-xs font-semibold">⚠️ نقاط ضعف:</p>
                <p v-for="w in result.expert_analysis.weaknesses" :key="w" class="text-red-500 text-xs">• {{ w }}</p>
              </div>
              <div v-if="result.expert_analysis.recommendations?.length" class="space-y-1">
                <p class="text-blue-600 text-xs font-semibold">💡 توصیه‌ها:</p>
                <p v-for="r in result.expert_analysis.recommendations" :key="r" class="text-blue-500 text-xs">• {{ r }}</p>
              </div>
              <div class="flex gap-2 mt-3">
                <VButton size="sm" variant="primary" :loading="applyingSuggestions" @click="applySuggestions(result.expert_analysis.recommendations || [])">اعمال همه پیشنهادات</VButton>
              </div>
            </div>
          </VCard>

          <!-- Profile -->
          <VCard v-if="result.profile" title="🎯 پروفایل">
            <div class="space-y-1 text-xs">
              <div>نوع: {{ result.profile.content_type }}</div>
              <div>زیرنوع: {{ result.profile.subtype }}</div>
              <div>قصد: {{ result.profile.intent }}</div>
            </div>
          </VCard>

          <!-- Action Buttons -->
          <VCard title="🚀 اقدامات">
            <div class="space-y-3">
              <div class="flex flex-wrap gap-2">
                <VButton variant="primary" size="sm" @click="saveCurrentDraft">💾 ذخیره</VButton>
                <VButton variant="secondary" size="sm" @click="copyHtml">📋 کپی HTML</VButton>
                <VButton variant="secondary" size="sm" @click="copyPlainText">📝 کپی متن</VButton>
                <VButton variant="secondary" size="sm" @click="showPublishDialog = true">🚀 انتشار در وردپرس</VButton>
              </div>
              <p v-if="draftSaved" class="text-green-600 text-xs">✅ Draft ذخیره شد</p>
              <p v-if="publishResult?.success" class="text-green-600 text-xs">✅ منتشر شد! <a :href="publishResult.post_url" target="_blank" class="underline">مشاهده</a></p>
              <p v-if="publishResult?.error" class="text-red-600 text-xs">❌ {{ publishResult.error }}</p>
            </div>
          </VCard>
        </div>
      </div>
    </div>

    <!-- WP Publish Dialog -->
    <div v-if="showPublishDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-bold text-ink-strong">🚀 انتشار در وردپرس</h4>
          <button class="text-ink-muted hover:text-ink-strong" @click="showPublishDialog = false">✕</button>
        </div>
        <div class="space-y-3">
          <div>
            <label class="text-ink-strong text-sm font-semibold">آدرس سایت</label>
            <input v-model="wpUrl" placeholder="https://example.com" dir="ltr" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
          </div>
          <div>
            <label class="text-ink-strong text-sm font-semibold">نام کاربری</label>
            <input v-model="wpUser" placeholder="admin" dir="ltr" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
          </div>
          <div>
            <label class="text-ink-strong text-sm font-semibold">Application Password</label>
            <input v-model="wpPass" type="password" dir="ltr" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" />
          </div>
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <VButton variant="secondary" @click="showPublishDialog = false">لغو</VButton>
          <VButton variant="secondary" :loading="publishing" @click="publishToWordPress('draft')">پیش‌نویس</VButton>
          <VButton variant="primary" :loading="publishing" @click="publishToWordPress('publish')">انتشار</VButton>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

