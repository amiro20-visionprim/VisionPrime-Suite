<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface SiteOption { id: number; name: string }
interface Guardrail {
  id: number | null; organization_id: number; site_id: number | null
  content_type: string; subtype: string
  max_characters: number; min_words: number; max_words: number
  allowed_tone: string; allowed_tags: string[]
  require_cta: boolean; require_faq: boolean; require_internal_links: boolean
  min_internal_links: number; require_brand_mention: boolean
  forbidden_words: string[]; system_prompt: string | null
  user_prompt_template: string | null; is_active: boolean
}
interface PerformanceSummary { content_type: string; clicks: number; impressions: number; ctr: number; position: number; pages: number }
interface PerformanceTrend { date: string; clicks: number; impressions: number; ctr: number }
interface TopPage { url: string; title: string; content_type: string; clicks: number; position: number }
interface SerpAnalysis { id: number; keyword: string; target_url: string | null; results: string; status: string; created_at: string }
interface KeywordCluster { id: number; pillar_keyword: string; cluster_keywords: string; total_clicks: number; total_impressions: number; avg_position: number; priority: string }
interface CalendarItem { id: number; title: string; planned_date: string; content_type: string; subtype: string; priority_score: number; status: string; notes: string }
interface Suggestion { id: number; suggestion_type: string; title: string; description: string; priority: string; status: string; seo_context: string; estimated_impact: string; created_at: string }

const p = defineProps<{ sites: SiteOption[]; isSuperAdmin: boolean }>()
const page = usePage<{ flash?: { status?: string; error?: string } }>()

const selectedSiteId = ref('')
const selectedContentType = ref('article')
const selectedSubtype = ref('general')
const activeTab = ref<'performance' | 'serp' | 'keywords' | 'calendar' | 'guardrails' | 'suggestions'>('performance')
const loading = ref(false)
const saving = ref(false)

const perfSummary = ref<PerformanceSummary[]>([])
const perfTrend = ref<PerformanceTrend[]>([])
const perfTopPages = ref<TopPage[]>([])
const perfDays = ref(28)

const serpAnalyses = ref<SerpAnalysis[]>([])
const serpKeyword = ref('')
const serpTargetUrl = ref('')
const serpAnalyzing = ref(false)

const keywordClusters = ref<KeywordCluster[]>([])

const calendarItems = ref<CalendarItem[]>([])
const showCalendarForm = ref(false)
const calendarForm = ref({ title: '', planned_date: '', content_type: 'article', subtype: 'general', priority_score: 5, notes: '' })

const guardrails = ref<Guardrail[]>([])
const currentGuardrail = ref<Guardrail | null>(null)
const isDefault = ref(true)
const guardrailForm = ref({
  max_characters: 8000, min_words: 400, max_words: 2000, allowed_tone: 'informative',
  allowed_tags: ['h1', 'h2', 'h3', 'p', 'ul', 'ol', 'table', 'strong', 'a'],
  require_cta: true, require_faq: false, require_internal_links: true, min_internal_links: 2,
  require_brand_mention: true, forbidden_words: [] as string[],
  system_prompt: '', user_prompt_template: '',
})
const testingGeneration = ref(false)
const testResult = ref<string | null>(null)
const newForbiddenWord = ref('')
const newAllowedTag = ref('')

const suggestions = ref<Suggestion[]>([])
const suggestionStats = ref<Record<string, number>>({})
const suggestionFilter = ref('pending')

const articleSubtypes = ['general', 'how_to', 'review', 'comparison', 'list', 'news', 'guide', 'pillar']
const productSubtypes = ['general', 'simple', 'variable', 'grouped', 'digital']
const contentTypes = [{ label: '\u{1f4dd} \u0645\u0642\u0627\u0644\u0647', value: 'article' }, { label: '\u{1f6d2} \u0645\u062d\u0635\u0648\u0644', value: 'product' }]
const toneOptions = [
  { label: '\u0631\u0633\u0645\u06cc (Informative)', value: 'informative' },
  { label: '\u0635\u0645\u06cc\u0645\u06cc (Casual)', value: 'casual' },
  { label: '\u062d\u0631\u0641\u0647\u200c\u0627\u06cc (Professional)', value: 'professional' },
  { label: '\u0622\u0645\u0648\u0632\u0634\u06cc (Educational)', value: 'educational' },
  { label: '\u062a\u0628\u0644\u06cc\u063a\u0627\u062a\u06cc (Promotional)', value: 'promotional' },
]
const subtypeLabels: Record<string, string> = {
  general: '\u0639\u0645\u0648\u0645\u06cc', how_to: '\u0686\u0637\u0648\u0631', review: '\u0628\u0631\u0631\u0633\u06cc',
  comparison: '\u0645\u0642\u0627\u06cc\u0633\u0647\u200c\u0627\u06cc', list: '\u0644\u06cc\u0633\u062a\u06cc',
  news: '\u062e\u0628\u0631\u06cc', guide: '\u0631\u0627\u0647\u0646\u0645\u0627\u06cc', pillar: 'Pillar',
  simple: '\u0633\u0627\u062f\u0647', variable: '\u0645\u062a\u063a\u06cc\u0631', grouped: '\u06af\u0631\u0648\u0647\u06cc', digital: '\u062f\u06cc\u062c\u06cc\u062a\u0627\u0644',
}
const availableSubtypes = computed(() => selectedContentType.value === 'article' ? articleSubtypes : productSubtypes)

function apiParams() {
  const params = new URLSearchParams()
  if (selectedSiteId.value) params.set('site_id', selectedSiteId.value)
  return params
}
async function fetchJson(url: string, opts?: RequestInit) {
  const res = await fetch(url, opts)
  return res.json()
}

async function loadPerformance() {
  loading.value = true
  try {
    const params = apiParams(); params.set('days', String(perfDays.value))
    const data = await fetchJson('/api/command-center/performance?' + params)
    perfSummary.value = data.summary || []; perfTrend.value = data.trend || []; perfTopPages.value = data.top_pages || []
  } catch { /* ignore */ }
  loading.value = false
}

async function loadSerp() {
  loading.value = true
  try { const data = await fetchJson('/api/command-center/serp?' + apiParams()); serpAnalyses.value = data.analyses || [] } catch { /* ignore */ }
  loading.value = false
}
async function analyzeSerp() {
  if (!serpKeyword.value.trim()) return
  serpAnalyzing.value = true
  try {
    await fetchJson('/api/command-center/serp/analyze', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), keyword: serpKeyword.value, target_url: serpTargetUrl.value || null }),
    })
    serpKeyword.value = ''; serpTargetUrl.value = ''; await loadSerp()
  } catch { /* ignore */ }
  serpAnalyzing.value = false
}

async function loadKeywords() {
  loading.value = true
  try { const data = await fetchJson('/api/command-center/keywords?' + apiParams()); keywordClusters.value = data.clusters || [] } catch { /* ignore */ }
  loading.value = false
}

async function loadCalendar() {
  loading.value = true
  try { const data = await fetchJson('/api/command-center/calendar?' + apiParams()); calendarItems.value = data.items || [] } catch { /* ignore */ }
  loading.value = false
}
async function addCalendarItem() {
  if (!calendarForm.value.title || !calendarForm.value.planned_date) return
  saving.value = true
  try {
    await fetchJson('/api/command-center/calendar', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), ...calendarForm.value }),
    })
    showCalendarForm.value = false
    calendarForm.value = { title: '', planned_date: '', content_type: 'article', subtype: 'general', priority_score: 5, notes: '' }
    await loadCalendar()
  } catch { /* ignore */ }
  saving.value = false
}

async function loadGuardrails() {
  loading.value = true
  try { const data = await fetchJson('/api/content/guardrails?' + apiParams()); guardrails.value = data.guardrails || []; await resolveCurrent() } catch { /* ignore */ }
  loading.value = false
}
async function resolveCurrent() {
  if (!selectedSiteId.value) { currentGuardrail.value = null; isDefault.value = true; return }
  try {
    const params = apiParams(); params.set('content_type', selectedContentType.value); params.set('subtype', selectedSubtype.value)
    const data = await fetchJson('/api/content/guardrails/resolve?' + params)
    currentGuardrail.value = data.guardrail; isDefault.value = data.is_default
    if (data.guardrail) {
      guardrailForm.value = {
        max_characters: data.guardrail.max_characters ?? 8000, min_words: data.guardrail.min_words ?? 400,
        max_words: data.guardrail.max_words ?? 2000, allowed_tone: data.guardrail.allowed_tone ?? 'informative',
        allowed_tags: data.guardrail.allowed_tags ?? ['h1','h2','h3','p','ul','ol','table','strong','a'],
        require_cta: data.guardrail.require_cta ?? true, require_faq: data.guardrail.require_faq ?? false,
        require_internal_links: data.guardrail.require_internal_links ?? true, min_internal_links: data.guardrail.min_internal_links ?? 2,
        require_brand_mention: data.guardrail.require_brand_mention ?? true, forbidden_words: data.guardrail.forbidden_words ?? [],
        system_prompt: data.guardrail.system_prompt ?? '', user_prompt_template: data.guardrail.user_prompt_template ?? '',
      }
    }
  } catch { /* ignore */ }
}
async function saveGuardrail() {
  if (!selectedSiteId.value) return
  saving.value = true
  try {
    await fetchJson('/api/content/guardrails', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), content_type: selectedContentType.value, subtype: selectedSubtype.value, ...guardrailForm.value }),
    })
    await loadGuardrails()
  } catch (e: unknown) { alert(String(e)) }
  saving.value = false
}
async function seedDefaults() {
  if (!selectedSiteId.value) return
  loading.value = true
  try {
    await fetchJson('/api/content/guardrails/seed', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), content_type: selectedContentType.value }),
    })
    await loadGuardrails()
  } catch { /* ignore */ }
  loading.value = false
}
async function testGenerate() {
  if (!selectedSiteId.value) return
  testingGeneration.value = true; testResult.value = null
  try {
    const data = await fetchJson('/api/content/generate', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), keyword: 'test', title: '\u0645\u0642\u0627\u0644\u0647 \u062a\u0633\u062a\u06cc', subtype: selectedSubtype.value }),
    })
    testResult.value = data.error ? '\u274c ' + data.error : '\u2705 ' + data.model + ' | ' + data.source
  } catch (e: unknown) { testResult.value = '\u274c ' + String(e) }
  testingGeneration.value = false
}

async function loadSuggestions() {
  loading.value = true
  try {
    const params = apiParams(); params.set('status', suggestionFilter.value)
    const data = await fetchJson('/api/command-center/suggestions?' + params)
    suggestions.value = data.suggestions || []; suggestionStats.value = data.stats || {}
  } catch { /* ignore */ }
  loading.value = false
}
async function actOnSuggestion(id: number, status: string) {
  try {
    await fetchJson('/api/command-center/suggestions/' + id + '/action', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ status }),
    })
    await loadSuggestions()
  } catch { /* ignore */ }
}

function addForbiddenWord() { if (newForbiddenWord.value.trim() && !guardrailForm.value.forbidden_words.includes(newForbiddenWord.value.trim())) { guardrailForm.value.forbidden_words.push(newForbiddenWord.value.trim()); newForbiddenWord.value = '' } }
function removeForbiddenWord(word: string) { guardrailForm.value.forbidden_words = guardrailForm.value.forbidden_words.filter(w => w !== word) }
function addAllowedTag() { if (newAllowedTag.value.trim() && !guardrailForm.value.allowed_tags.includes(newAllowedTag.value.trim())) { guardrailForm.value.allowed_tags.push(newAllowedTag.value.trim()); newAllowedTag.value = '' } }
function removeAllowedTag(tag: string) { guardrailForm.value.allowed_tags = guardrailForm.value.allowed_tags.filter(t => t !== tag) }
function priorityColor(p: string) { return p === 'critical' ? 'danger' : p === 'high' ? 'warning' : p === 'medium' ? 'info' : 'neutral' }
function statusColor(s: string) { return s === 'completed' ? 'success' : s === 'running' ? 'info' : s === 'pending' ? 'warning' : s === 'cancelled' ? 'danger' : 'neutral' }
function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('fa-IR') : '' }
function formatNum(n: number) { return n?.toLocaleString('fa-IR') ?? '0' }

function loadTabData() {
  const loaders: Record<string, () => Promise<void>> = {
    performance: loadPerformance, serp: loadSerp, keywords: loadKeywords,
    calendar: loadCalendar, guardrails: () => { loadGuardrails(); resolveCurrent() }, suggestions: loadSuggestions,
  }
  loaders[activeTab.value]?.()
}

watch([selectedSiteId, selectedContentType, selectedSubtype], () => {
  if (activeTab.value === 'guardrails') resolveCurrent()
})
watch(selectedSiteId, () => { loadTabData() })
watch(activeTab, () => { loadTabData() })

onMounted(() => {
  if (p.sites.length > 0 && !selectedSiteId.value) selectedSiteId.value = String(p.sites[0].id)
  loadPerformance()
})
</script>

<template>
  <Head title="اتاق فرماندهی محتوا" />
  <AppLayout>
    <VPageHeader title="🎯 اتاق فرماندهی محتوا" description="مدیریت جامع ابزار های SEO توضع محتوا." />
    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">{{ page.props.flash.status }}</VAlert>

    <!-- Site Selector -->
    <VCard class="mt-6">
      <div class="grid gap-4 md:grid-cols-4">
        <VSelect v-model="selectedSiteId" label="سایت" :options="p.sites.map(s => ({ label: s.name, value: String(s.id) }))" />
        <VSelect v-model="selectedContentType" label="نوع محتوا" :options="contentTypes" />
        <VSelect v-model="selectedSubtype" label="زیرنوع" :options="availableSubtypes.map(s => ({ label: (subtypeLabels[s] || s), value: s }))" />
        <div class="flex items-end gap-2">
          <VButton @click="loadTabData" :loading="loading" variant="secondary" class="flex-1">بارگذاری</VButton>
        </div>
      </div>
    </VCard>

    <!-- Tabs -->
    <div class="mt-6 border-line flex gap-1 overflow-x-auto border-b">
      <button v-for="tab in [{id:'performance',label:'📊 داشبورد عملکرد'},{id:'serp',label:'🔍 تحلیل SERP'},{id:'keywords',label:'🗺🏻 آبر کلیدوازه'},{id:'calendar',label:'📅 تقویم محتوایی'},{id:'guardrails',label:'🛡🏻 گاردرایل'},{id:'suggestions',label:'💡 پیشنهادها'}]"
        :key="tab.id" type="button"
        class="border-b-2 whitespace-nowrap px-5 py-3 text-sm font-medium transition-colors"
        :class="activeTab === tab.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-muted hover:text-ink-strong'"
        @click="activeTab = tab.id">{{ tab.label }}</button>
    </div>

    <!-- TAB 1: Performance Hub -->
    <div v-if="activeTab === 'performance'" class="mt-6 space-y-6">
      <div class="flex items-center gap-3">
        <VSelect v-model="perfDays" label="بزه تاریخ" :options="[{label:'7 روز',value:7},{label:'28 روز',value:28},{label:'90 روز',value:90}]" @change="loadPerformance" />
      </div>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <VCard v-for="s in perfSummary" :key="s.content_type">
          <div class="text-ink-muted text-xs font-medium uppercase">{{ s.content_type === 'article' ? 'مقاله' : 'محصول' }}</div>
          <div class="mt-2 text-2xl font-bold text-ink-strong">{{ formatNum(s.clicks) }}</div>
          <div class="text-ink-muted text-xs">کلیک</div>
          <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
            <div><span class="text-ink-muted">نمایش:</span> {{ formatNum(s.impressions) }}</div>
            <div><span class="text-ink-muted">CTR:</span> {{ (s.ctr ?? 0).toFixed(1) }}%</div>
            <div><span class="text-ink-muted">رتبه:</span> {{ (s.position ?? 0).toFixed(1) }}</div>
            <div><span class="text-ink-muted">صفحه:</span> {{ s.pages }}</div>
          </div>
        </VCard>
      </div>
      <VCard title="نمودار رشد" v-if="perfTrend.length">
        <div class="max-h-64 overflow-auto">
          <table class="w-full text-xs">
            <thead><tr class="text-ink-muted border-b"><th class="p-2 text-right">تاریخ</th><th class="p-2 text-right">کلیک</th><th class="p-2 text-right">نمایش</th><th class="p-2 text-right">CTR</th></tr></thead>
            <tbody><tr v-for="t in perfTrend.slice(-14)" :key="t.date" class="border-b hover:bg-brand-50"><td class="p-2">{{ formatDate(t.date) }}</td><td class="p-2 font-medium">{{ formatNum(t.clicks) }}</td><td class="p-2">{{ formatNum(t.impressions) }}</td><td class="p-2">{{ (t.ctr ?? 0).toFixed(2) }}%</td></tr></tbody>
          </table>
        </div>
      </VCard>
      <VCard title="صفحات برترین" v-if="perfTopPages.length">
        <div class="max-h-80 overflow-auto">
          <table class="w-full text-xs">
            <thead><tr class="text-ink-muted border-b"><th class="p-2 text-right">صفحه</th><th class="p-2 text-right">کلیک</th><th class="p-2 text-right">رتبه</th><th class="p-2 text-right">نوع</th></tr></thead>
            <tbody><tr v-for="pg in perfTopPages" :key="pg.url" class="border-b hover:bg-brand-50"><td class="p-2 max-w-xs truncate" :title="pg.url">{{ pg.title || pg.url }}</td><td class="p-2 font-medium">{{ formatNum(pg.clicks) }}</td><td class="p-2">{{ (pg.position ?? 0).toFixed(1) }}</td><td class="p-2"><VBadge size="sm" :tone="pg.content_type === 'article' ? 'info' : 'success'">{{ pg.content_type }}</VBadge></td></tr></tbody>
          </table>
        </div>
      </VCard>
      <div v-if="!perfSummary.length && !loading" class="text-ink-muted py-12 text-center text-sm">داده ای برای این بازه زمان به دستور سطر صفحه ها اصلاح شده است.</div>
    </div>

    <!-- TAB 2: SERP Intelligence -->
    <div v-if="activeTab === 'serp'" class="mt-6 space-y-6">
      <VCard title="🔍 تحلیل SERP جدید">
        <div class="grid gap-4 md:grid-cols-3">
          <input v-model="serpKeyword" placeholder="کلیدوازه هدف" class="border-line rounded-xl border px-4 py-2.5 text-sm" />
          <input v-model="serpTargetUrl" placeholder="آدرس هدف (اختیاری)" class="border-line rounded-xl border px-4 py-2.5 text-sm" />
          <VButton @click="analyzeSerp" :loading="serpAnalyzing" variant="primary">تحلیل کن</VButton>
        </div>
      </VCard>
      <VCard title="تاریخچه تحلیلها">
        <div v-if="serpAnalyses.length === 0" class="text-ink-muted py-8 text-center text-sm">هنوز تحلیلی ثبت نشده است.</div>
        <div v-else class="space-y-2">
          <div v-for="a in serpAnalyses" :key="a.id" class="border-line flex items-center justify-between rounded-xl border p-4">
            <div><div class="text-sm font-medium text-ink-strong">{{ a.keyword }}</div><div class="text-ink-muted text-xs">{{ formatDate(a.created_at) }} به عنواصر {{ a.target_url || '-' }}</div></div>
            <VBadge :tone="statusColor(a.status)" size="sm">{{ a.status }}</VBadge>
          </div>
        </div>
      </VCard>
    </div>

    <!-- TAB 3: Keyword Architecture -->
    <div v-if="activeTab === 'keywords'" class="mt-6 space-y-6">
      <VCard title="نقشه کلیدوازه و سخته‌بندی">
        <div v-if="keywordClusters.length === 0" class="text-ink-muted py-8 text-center text-sm">هنوز سطر کلیدوازه ای تعیین نشده است.</div>
        <div v-else class="space-y-3">
          <div v-for="c in keywordClusters" :key="c.id" class="border-line rounded-xl border p-4">
            <div class="flex items-center justify-between">
              <div><div class="text-sm font-bold text-ink-strong">🎯 {{ c.pillar_keyword }}</div><div class="text-ink-muted mt-1 text-xs">کلیک: {{ formatNum(c.total_clicks) }} | نمایش: {{ formatNum(c.total_impressions) }} | رتبه: {{ (c.avg_position ?? 0).toFixed(1) }}</div></div>
              <VBadge :tone="priorityColor(c.priority)" size="sm">{{ c.priority }}</VBadge>
            </div>
            <div class="mt-2 flex flex-wrap gap-1.5"><VBadge v-for="kw in (c.cluster_keywords || '').split(',').filter(Boolean)" :key="kw" tone="info" size="sm">{{ kw.trim() }}</VBadge></div>
          </div>
        </div>
      </VCard>
    </div>

    <!-- TAB 4: Content Calendar -->
    <div v-if="activeTab === 'calendar'" class="mt-6 space-y-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-ink-strong">تقویم محتوایی</h3>
        <VButton @click="showCalendarForm = !showCalendarForm" variant="primary" size="sm">{{ showCalendarForm ? 'بستن' : '+ افزودن قطر' }}</VButton>
      </div>
      <VCard v-if="showCalendarForm" title="قطره جدید">
        <div class="grid gap-4 md:grid-cols-2">
          <input v-model="calendarForm.title" placeholder="عنواصر مقاله" class="border-line rounded-xl border px-4 py-2.5 text-sm" />
          <input v-model="calendarForm.planned_date" type="date" class="border-line rounded-xl border px-4 py-2.5 text-sm" />
          <VSelect v-model="calendarForm.content_type" label="نوع" :options="contentTypes" />
          <VSelect v-model="calendarForm.subtype" label="زیرنوع" :options="availableSubtypes.map(s => ({ label: (subtypeLabels[s] || s), value: s }))" />
          <div><label class="text-ink-strong text-sm font-semibold">اولویت</label><input v-model.number="calendarForm.priority_score" type="range" min="1" max="10" class="mt-2 w-full" /></div>
          <input v-model="calendarForm.notes" placeholder="یاداوشتها" class="border-line rounded-xl border px-4 py-2.5 text-sm" />
        </div>
        <div class="mt-4"><VButton @click="addCalendarItem" :loading="saving" variant="primary">ذخیره</VButton></div>
      </VCard>
      <VCard title="قطرهای برنامه‌ریزی">
        <div v-if="calendarItems.length === 0" class="text-ink-muted py-8 text-center text-sm">هنوز قطره ای تعیین نشده است.</div>
        <div v-else class="space-y-2">
          <div v-for="item in calendarItems" :key="item.id" class="border-line flex items-center justify-between rounded-xl border p-4 hover:bg-brand-50">
            <div class="flex items-center gap-4">
              <div class="bg-brand-100 text-brand-700 flex h-12 w-12 flex-col items-center justify-center rounded-xl text-xs font-bold">
                <span>{{ formatDate(item.planned_date).split('/')[1] }}</span>
                <span class="text-[10px]">{{ formatDate(item.planned_date).split('/')[0] }}</span>
              </div>
              <div><div class="text-sm font-medium text-ink-strong">{{ item.title }}</div><div class="text-ink-muted text-xs">{{ item.content_type }} / {{ subtypeLabels[item.subtype] || item.subtype }} به اولویت {{ item.priority_score }}/10</div></div>
            </div>
            <VBadge :tone="statusColor(item.status)" size="sm">{{ item.status }}</VBadge>
          </div>
        </div>
      </VCard>
    </div>

    <!-- TAB 5: Guardrails & Prompts -->
    <div v-if="activeTab === 'guardrails'" class="mt-6 space-y-6">
      <div class="flex items-center gap-3">
        <VBadge :tone="isDefault ? 'warning' : 'success'">{{ isDefault ? 'پیشدفت' : 'سفارشی' }}</VBadge>
        <VButton @click="seedDefaults" variant="secondary" size="sm">🌱 پیشدفت اصلی</VButton>
      </div>
      <div class="grid gap-6 lg:grid-cols-2">
        <VCard title="📏 محدودیت ها">
          <div class="space-y-4">
            <div><label class="text-ink-strong text-sm font-semibold">حداکثر کاراکتر</label><input v-model.number="guardrailForm.max_characters" type="number" min="500" max="50000" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /></div>
            <div class="grid grid-cols-2 gap-4">
              <div><label class="text-ink-strong text-sm font-semibold">حدالل کلمات</label><input v-model.number="guardrailForm.min_words" type="number" min="50" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /></div>
              <div><label class="text-ink-strong text-sm font-semibold">حداکثر کلمات</label><input v-model.number="guardrailForm.max_words" type="number" min="100" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /></div>
            </div>
            <VSelect v-model="guardrailForm.allowed_tone" label="لحن" :options="toneOptions" />
            <div>
              <label class="text-ink-strong text-sm font-semibold">تگ های HTML مجاز</label>
              <div class="mt-2 flex flex-wrap gap-1.5"><VBadge v-for="tag in guardrailForm.allowed_tags" :key="tag" tone="info" class="cursor-pointer" @click="removeAllowedTag(tag)">{{ tag }} ✕</VBadge></div>
              <div class="mt-2 flex gap-2"><input v-model="newAllowedTag" placeholder="تگ جدید" class="border-line flex-1 rounded-xl border px-3 py-1.5 text-sm" @keyup.enter="addAllowedTag" /><VButton size="sm" @click="addAllowedTag" variant="secondary">+</VButton></div>
            </div>
          </div>
        </VCard>
        <VCard title="✅ الزاممات محتوایی">
          <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer"><input v-model="guardrailForm.require_cta" type="checkbox" class="h-4 w-4 rounded" /><span class="text-ink-strong text-sm">CTA اجباری</span></label>
            <label class="flex items-center gap-3 cursor-pointer"><input v-model="guardrailForm.require_faq" type="checkbox" class="h-4 w-4 rounded" /><span class="text-ink-strong text-sm">FAQ اجباری</span></label>
            <label class="flex items-center gap-3 cursor-pointer"><input v-model="guardrailForm.require_internal_links" type="checkbox" class="h-4 w-4 rounded" /><span class="text-ink-strong text-sm">لینکسازی داخلی اجباری</span></label>
            <div v-if="guardrailForm.require_internal_links"><label class="text-ink-muted text-xs">حدالل تعداد لینک</label><input v-model.number="guardrailForm.min_internal_links" type="number" min="0" max="20" class="border-line mt-1 w-32 rounded-xl border px-3 py-1.5 text-sm" /></div>
            <label class="flex items-center gap-3 cursor-pointer"><input v-model="guardrailForm.require_brand_mention" type="checkbox" class="h-4 w-4 rounded" /><span class="text-ink-strong text-sm">ذکر نام برند اجباری</span></label>
            <div>
              <label class="text-ink-strong text-sm font-semibold">کلمات ممنوعه</label>
              <div class="mt-2 flex flex-wrap gap-1.5"><VBadge v-for="word in guardrailForm.forbidden_words" :key="word" tone="danger" class="cursor-pointer" @click="removeForbiddenWord(word)">{{ word }} ✕</VBadge></div>
              <div class="mt-2 flex gap-2"><input v-model="newForbiddenWord" placeholder="کلمه ممنوعه" class="border-line flex-1 rounded-xl border px-3 py-1.5 text-sm" @keyup.enter="addForbiddenWord" /><VButton size="sm" @click="addForbiddenWord" variant="secondary">+</VButton></div>
            </div>
          </div>
        </VCard>
      </div>
      <VCard title="📝 پرامپت سیستم">
        <textarea v-model="guardrailForm.system_prompt" rows="8" dir="auto" class="border-line w-full rounded-xl border p-4 text-sm leading-7 font-mono" placeholder="تو یک متخصص SEO..." />
      </VCard>
      <VCard title="📋 قالب پرامپت کاربر">
        <textarea v-model="guardrailForm.user_prompt_template" rows="8" dir="auto" class="border-line w-full rounded-xl border p-4 text-sm leading-7 font-mono" placeholder="{title} {keyword} {siteName}" />
      </VCard>
      <div class="flex items-center gap-3">
        <VButton @click="saveGuardrail" :loading="saving" variant="primary">💾 ذخیره</VButton>
        <VButton @click="testGenerate" :loading="testingGeneration" variant="secondary">🧪 تست تولید</VButton>
        <VBadge v-if="testResult" :tone="testResult.includes('✅') ? 'success' : 'danger'" class="text-xs">{{ testResult }}</VBadge>
      </div>
      <VCard title="گاردرایل های موجود" v-if="guardrails.length">
        <div class="space-y-2">
          <div v-for="g in guardrails" :key="g.id ?? 'x'" class="border-line flex items-center justify-between rounded-xl border p-3 hover:bg-brand-50 cursor-pointer" @click="selectedContentType = g.content_type; selectedSubtype = g.subtype; resolveCurrent()">
            <div class="flex items-center gap-3"><VBadge :tone="g.site_id ? 'info' : 'warning'" size="sm">{{ g.site_id ? 'سایت' : 'سازمان' }}</VBadge><span class="text-sm font-medium">{{ g.content_type }} / {{ subtypeLabels[g.subtype] || g.subtype }}</span></div>
            <div class="flex items-center gap-2 text-xs text-ink-muted"><span>{{ g.min_words }}-{{ g.max_words }}</span><VBadge :tone="g.is_active ? 'success' : 'danger'" size="sm">{{ g.is_active ? 'فعال' : 'غیرفعال' }}</VBadge></div>
          </div>
        </div>
      </VCard>
    </div>

    <!-- TAB 6: Smart Suggestions -->
    <div v-if="activeTab === 'suggestions'" class="mt-6 space-y-6">
      <div class="flex gap-3 flex-wrap">
        <VBadge v-for="(count, status) in suggestionStats" :key="status" :tone="statusColor(String(status))" class="cursor-pointer px-3 py-1" @click="suggestionFilter = String(status); loadSuggestions()">{{ status }}: {{ count }}</VBadge>
      </div>
      <VCard title="💡 پیشنهادهای هوشمند">
        <div v-if="suggestions.length === 0" class="text-ink-muted py-8 text-center text-sm">پیشنهاده ای برای این وضعیت به دستور صفحه ها اصلاح شده است.</div>
        <div v-else class="space-y-3">
          <div v-for="s in suggestions" :key="s.id" class="border-line rounded-xl border p-4">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1">
                <div class="flex items-center gap-2"><VBadge :tone="priorityColor(s.priority)" size="sm">{{ s.priority }}</VBadge><VBadge tone="neutral" size="sm">{{ s.suggestion_type }}</VBadge></div>
                <div class="mt-2 text-sm font-medium text-ink-strong">{{ s.title }}</div>
                <div class="text-ink-muted mt-1 text-xs">{{ s.description }}</div>
                <div v-if="s.estimated_impact" class="mt-2 text-xs"><span class="text-success-600 font-medium">تأظیر:</span> {{ s.estimated_impact }}</div>
              </div>
              <div v-if="s.status === 'pending'" class="flex gap-1.5">
                <VButton size="sm" variant="primary" @click="actOnSuggestion(s.id, 'accepted')">✅</VButton>
                <VButton size="sm" variant="danger" @click="actOnSuggestion(s.id, 'rejected')">❌</VButton>
              </div>
              <VBadge v-else :tone="statusColor(s.status)" size="sm">{{ s.status }}</VBadge>
            </div>
          </div>
        </div>
      </VCard>
    </div>

  </AppLayout>
</template>
