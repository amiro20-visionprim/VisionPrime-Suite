<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import ContentScoreCard from '@/Pages/App/Shared/ContentScoreCard.vue'
import InternalLinkSuggestions from '@/Pages/App/Shared/InternalLinkSuggestions.vue'
import SchemaPreview from '@/Pages/App/Shared/SchemaPreview.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import VIcon from '@/shared/ui/VIcon.vue'

interface SiteOption { id: number; name: string; canonical_url: string }
interface Topic { type: string; keyword: string; score: number; explanation: string; suggested_type?: string; suggested_subtype?: string; intent?: string }
interface LinkSuggestion { url: string; title: string; anchor: string; relevance_score: number }
interface SchemaItem { '@type': string; [key: string]: unknown }
interface QualityResult { passed: boolean; score: number; failures: string[]; warnings: string[]; rankmath_score: number }
interface GeneratedResult { content: string; model: string; source: string; meta_title: string; meta_description: string; schemas: SchemaItem[]; links: LinkSuggestion[]; quality: QualityResult }

const p = defineProps<{ sites: SiteOption[]; subtypes: Record<string, string>; isSuperAdmin: boolean }>()
const page = usePage<{ flash?: { status?: string; error?: string } }>()
const activeTab = ref<'editor' | 'meta' | 'specs' | 'links' | 'schema'>('editor')
const topics = ref<Topic[]>([])
const loadingResearch = ref(false)
const loadingGenerate = ref(false)
const generatedContent = ref<GeneratedResult | null>(null)
const liveLinks = ref<LinkSuggestion[]>([])
const liveSchema = ref<SchemaItem[]>([])

const f = useForm({
  site_id: '', keyword: '', title: '', subtype: 'short_desc',
  meta_title: '', meta_description: '', slug: '',
  short_description: '', content_body: '',
  price: '', sale_price: '', stock_status: 'in_stock',
})

const titleLength = computed(() => f.title.length)
const metaTitleLength = computed(() => f.meta_title.length)
const metaDescLength = computed(() => f.meta_description.length)
const contentWordCount = computed(() => { const plain = f.content_body.replace(/<[^>]+>/g, ' ').trim(); return plain ? plain.split(/\s+/).filter(w => w.length > 0).length : 0 })
const keywordDensity = computed(() => { if (!f.keyword || contentWordCount.value === 0) return 0; return (f.content_body.replace(/<[^>]+>/g, ' ').toLowerCase().split(f.keyword.toLowerCase()).length - 1) / contentWordCount.value * 100 })
const headingCount = computed(() => { const m = f.content_body.match(/<h[2-6][^>]*>/gi); return m ? m.length : 0 })

const seoChecks = computed(() => {
  const checks: Array<{ label: string; passed: boolean; detail?: string }> = []
  checks.push({ label: 'عنوان محصول', passed: f.title.trim().length > 0 })
  checks.push({ label: 'توضیح کوتاه', passed: f.short_description.trim().length > 0 })
  checks.push({ label: `طول عنوان متا (${metaTitleLength.value})`, passed: metaTitleLength.value >= 30 && metaTitleLength.value <= 60, detail: '30-60' })
  checks.push({ label: `طول توضیح متا (${metaDescLength.value})`, passed: metaDescLength.value >= 120 && metaDescLength.value <= 160, detail: '120-160' })
  checks.push({ label: 'کلیدواژه در عنوان متا', passed: f.keyword.trim() === '' || f.meta_title.toLowerCase().includes(f.keyword.toLowerCase()) })
  checks.push({ label: `تراکم کلیدواژه (${keywordDensity.value.toFixed(1)}٪)`, passed: keywordDensity.value >= 1.0 && keywordDensity.value <= 3.0, detail: '1.0-3.0' })
  checks.push({ label: `تعداد کلمات (${contentWordCount.value})`, passed: contentWordCount.value >= 80, detail: 'حداقل 80' })
  checks.push({ label: 'قیمت محصول', passed: f.price.trim().length > 0 })
  return checks
})

const seoScore = computed(() => { if (seoChecks.value.length === 0) return 0; return Math.round((seoChecks.value.filter(c => c.passed).length / seoChecks.value.length) * 100) })

const schemaPreview = computed(() => {
  if (generatedContent.value?.schemas) return generatedContent.value.schemas
  if (liveSchema.value.length > 0) return liveSchema.value
  return [{ '@context': 'https://schema.org', '@type': 'Product', name: f.title || 'محصول', description: (f.short_description + ' ' + f.content_body.replace(/<[^>]+>/g, ' ').trim()).substring(0, 500), brand: { '@type': 'Brand', name: p.sites.find(s => String(s.id) === f.site_id)?.name ?? '' }, offers: f.price ? { '@type': 'Offer', price: f.price, priceCurrency: 'IRR', availability: f.stock_status === 'in_stock' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' } : undefined }]
})

async function loadResearch() {
  if (!f.site_id) return
  loadingResearch.value = true; topics.value = []
  try { const res = await fetch(`/api/content/research?site_id=${f.site_id}`); const data = await res.json(); topics.value = data.topics || [] } catch { topics.value = [] }
  loadingResearch.value = false
}

function selectTopic(t: Topic) { f.keyword = t.keyword; f.title = t.keyword; if (t.suggested_subtype) f.subtype = t.suggested_subtype }

async function generateWithAI() {
  if (!f.site_id || !f.keyword) return
  loadingGenerate.value = true; generatedContent.value = null
  try {
    const res = await fetch('/api/content/generate', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ site_id: f.site_id, keyword: f.keyword, title: f.title || undefined, subtype: f.subtype || undefined }) })
    const data = await res.json()
    if (data.error) { alert(data.error); return }
    generatedContent.value = data; f.content_body = data.content || ''; f.meta_title = data.meta_title || ''; f.meta_description = data.meta_description || ''
    liveLinks.value = data.links || []; liveSchema.value = data.schemas || []
  } catch (e: unknown) { alert('خطا: ' + (e instanceof Error ? e.message : String(e))) }
  loadingGenerate.value = false
}

async function loadLinks() {
  if (!f.site_id || !f.keyword) return
  try { const res = await fetch('/api/content/links', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ site_id: f.site_id, title: f.title || f.keyword, keyword: f.keyword }) }); const data = await res.json(); liveLinks.value = data.suggestions || [] } catch {}
}

function onSiteChange() { f.keyword = ''; topics.value = []; generatedContent.value = null }
function autoGenerateMetaTitle() { if (f.title) f.meta_title = f.title.substring(0, 55) + ' | ' + (p.sites.find(s => String(s.id) === f.site_id)?.name ?? '') }
function autoGenerateMetaDesc() { if (f.short_description) f.meta_description = f.short_description.substring(0, 155) }
function autoGenerateSlug() { f.slug = f.title.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').substring(0, 75) }
function submit() { f.post('/app/ai-drafts/product', { preserveScroll: true, onSuccess: () => { f.reset(); generatedContent.value = null } }) }

watch(() => f.keyword, () => { if (f.keyword && f.site_id) loadLinks() })
</script>

<template>
  <Head title="تولید محصول هوشمند" />
  <AppLayout>
    <VPageHeader title="تولید محصول هوشمند" description="توضیح محصول با Meta Title/Description، اسکیمای Product با قیمت/موجودی واقعی و لینک‌سازی داخلی." />
    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">{{ page.props.flash.status }}</VAlert>

    <!-- Research -->
    <VCard class="mt-6" title="مرحله ۱ — تحقیق محصول">
      <div class="grid gap-4 md:grid-cols-3">
        <VSelect v-model="f.site_id" label="سایت" :options="p.sites.map(s => ({ label: s.name, value: String(s.id) }))" placeholder="انتخاب سایت" @update:model-value="onSiteChange" />
        <div class="flex items-end gap-2">
          <VInput v-model="f.keyword" label="کلیدواژه" placeholder="مثال: هدفون بی‌سیم" class="flex-1" />
          <VButton @click="loadResearch" :loading="loadingResearch" variant="secondary" class="mb-0.5">🔍 تحقیق</VButton>
        </div>
        <VSelect v-model="f.subtype" label="زیرنوع" :options="Object.entries(p.subtypes).map(([v,l]) => ({label:l, value:v}))" />
      </div>
      <div v-if="topics.length > 0" class="mt-4 max-h-60 overflow-y-auto space-y-2">
        <button v-for="(t, i) in topics.slice(0, 10)" :key="i" type="button" class="w-full text-right border rounded-xl p-3 hover:bg-brand-50 transition-colors" @click="selectTopic(t)">
          <div class="flex items-center justify-between"><span class="font-medium text-sm">{{ t.keyword }}</span><VBadge :tone="t.score >= 70 ? 'success' : 'neutral'">امتیاز: {{ t.score }}</VBadge></div>
          <p class="text-ink-muted text-xs mt-1">{{ t.explanation }}</p>
        </button>
      </div>
    </VCard>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <div class="space-y-6">
        <VCard title="مرحله ۲ — مشخصات محصول">
          <form class="space-y-4" @submit.prevent="submit">
            <VInput v-model="f.title" label="عنوان محصول" placeholder="مثال: هدفون بی‌سیم پرو" />
            <div class="flex items-center gap-3"><VBadge :tone="titleLength > 30 && titleLength <= 60 ? 'success' : 'neutral'">{{ titleLength }}/۶۰</VBadge><span class="text-ink-muted text-xs">طول عنوان</span></div>
            <div class="flex items-center gap-3">
              <VButton type="button" @click="generateWithAI" :loading="loadingGenerate" variant="secondary"><VIcon name="sparkles" size="sm" class="ms-1" />تولید با AI</VButton>
              <VButton type="submit">ذخیره پیش‌نویس</VButton>
            </div>
          </form>
        </VCard>

        <VCard>
          <div class="border-line flex gap-1 border-b">
            <button v-for="tab in [{id:'editor' as const, label:'✏️ ویرایشگر'}, {id:'meta' as const, label:'🏷️ Meta'}, {id:'specs' as const, label:'🛒 مشخصات'}, {id:'links' as const, label:'🔗 لینک'}, {id:'schema' as const, label:'📊 اسکیما'}]" :key="tab.id" type="button" class="border-b-2 px-4 py-2.5 text-sm font-medium transition-colors" :class="activeTab === tab.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-muted'" @click="activeTab = tab.id">{{ tab.label }}</button>
          </div>
          <div v-if="activeTab === 'editor'" class="p-5">
            <VInput v-model="f.short_description" label="توضیح کوتاه محصول" placeholder="حداکثر ۱۵۰ کاراکتر" />
            <textarea v-model="f.content_body" rows="16" dir="auto" class="border-line mt-3 w-full rounded-xl border p-4 text-sm leading-7 focus:ring-2 focus:ring-brand-500" placeholder="<h2>ویژگی‌ها</h2><ul><li>ویژگی ۱</li></ul>" />
            <div class="mt-2"><VBadge :tone="contentWordCount >= 80 ? 'success' : 'danger'">{{ contentWordCount }} کلمه</VBadge></div>
          </div>
          <div v-if="activeTab === 'meta'" class="space-y-4 p-5">
            <div><div class="flex items-center justify-between"><label class="text-ink-strong text-sm font-semibold">Meta Title</label><button type="button" class="text-brand-700 text-xs" @click="autoGenerateMetaTitle">تولید خودکار</button></div><input v-model="f.meta_title" dir="auto" maxlength="70" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /><div class="mt-1 flex items-center gap-2"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full transition-all" :class="metaTitleLength >= 30 && metaTitleLength <= 60 ? 'bg-green-500' : 'bg-red-500'" :style="{ width: Math.min(100, (metaTitleLength / 60) * 100) + '%' }" /></div><span class="text-ink-muted text-xs">{{ metaTitleLength }}/60</span></div></div>
            <div><div class="flex items-center justify-between"><label class="text-ink-strong text-sm font-semibold">Meta Description</label><button type="button" class="text-brand-700 text-xs" @click="autoGenerateMetaDesc">تولید خودکار</button></div><textarea v-model="f.meta_description" dir="auto" rows="3" maxlength="200" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /><div class="mt-1 flex items-center gap-2"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full transition-all" :class="metaDescLength >= 120 && metaDescLength <= 160 ? 'bg-green-500' : 'bg-red-500'" :style="{ width: Math.min(100, (metaDescLength / 160) * 100) + '%' }" /></div><span class="text-ink-muted text-xs">{{ metaDescLength }}/160</span></div></div>
            <div><div class="flex items-center justify-between"><label class="text-ink-strong text-sm font-semibold">Slug</label><button type="button" class="text-brand-700 text-xs" @click="autoGenerateSlug">تولید خودکار</button></div><input v-model="f.slug" dir="ltr" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /></div>
          </div>
          <div v-if="activeTab === 'specs'" class="space-y-4 p-5">
            <VInput v-model="f.price" label="قیمت (ریال)" placeholder="مثال: 2500000" dir="ltr" />
            <VInput v-model="f.sale_price" label="قیمت تخفیفی" placeholder="اختیاری" dir="ltr" />
            <VSelect v-model="f.stock_status" label="وضعیت موجودی" :options="[{label:'در انبار',value:'in_stock'},{label:'ناموجود',value:'out_of_stock'}]" />
          </div>
          <div v-if="activeTab === 'links'" class="p-5"><InternalLinkSuggestions :suggestions="liveLinks" /></div>
          <div v-if="activeTab === 'schema'" class="p-5"><SchemaPreview :schemas="schemaPreview" /></div>
        </VCard>
      </div>

      <div class="space-y-6">
        <ContentScoreCard :score="seoScore" :checks="seoChecks" :meta-title-length="metaTitleLength" :meta-title-range="[30, 60]" :meta-desc-length="metaDescLength" :meta-desc-range="[120, 160]" :keyword-density="keywordDensity" :keyword-range="[1.0, 3.0]" :word-count="contentWordCount" :word-range="[80]" :heading-count="headingCount" :heading-min="1" />
        <VCard v-if="generatedContent" title="🤖 نتیجه AI">
          <div class="space-y-2 text-xs">
            <div><span class="text-ink-muted">مدل:</span> <VBadge tone="info">{{ generatedContent.model }}</VBadge></div>
            <div><span class="text-ink-muted">امتیاز:</span> <VBadge :tone="generatedContent.quality?.passed ? 'success' : 'danger'">{{ generatedContent.quality?.score }}/100</VBadge></div>
          </div>
        </VCard>
        <Link href="/app/reviews" class="text-brand-700 text-sm font-medium">← مشاهده صف بررسی</Link>
      </div>
    </div>
  </AppLayout>
</template>
