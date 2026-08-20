<script setup lang="ts">
import { ref, watch, computed } from 'vue'
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

const p = defineProps<{ sites: SiteOption[]; isSuperAdmin: boolean }>()
const page = usePage<{ flash?: { status?: string; error?: string } }>()

const selectedSiteId = ref('')
const selectedContentType = ref('article')
const selectedSubtype = ref('general')
const activeTab = ref<'guardrails' | 'prompts' | 'history'>('guardrails')
const loading = ref(false)
const saving = ref(false)
const testingGeneration = ref(false)
const testResult = ref<string | null>(null)
const guardrails = ref<Guardrail[]>([])
const currentGuardrail = ref<Guardrail | null>(null)
const isDefault = ref(true)

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

const form = ref({
  max_characters: 8000, min_words: 400, max_words: 2000, allowed_tone: 'informative',
  allowed_tags: ['h1', 'h2', 'h3', 'p', 'ul', 'ol', 'table', 'strong', 'a'],
  require_cta: true, require_faq: false, require_internal_links: true, min_internal_links: 2,
  require_brand_mention: true, forbidden_words: [] as string[],
  system_prompt: '', user_prompt_template: '',
})

const newForbiddenWord = ref('')
const newAllowedTag = ref('')

async function loadGuardrails() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (selectedSiteId.value) params.set('site_id', selectedSiteId.value)
    const res = await fetch('/api/content/guardrails?' + params)
    const data = await res.json()
    guardrails.value = data.guardrails || []
    resolveCurrent()
  } catch { /* ignore */ }
  loading.value = false
}

async function resolveCurrent() {
  if (!selectedSiteId.value) { currentGuardrail.value = null; isDefault.value = true; return }
  try {
    const params = new URLSearchParams({ site_id: selectedSiteId.value, content_type: selectedContentType.value, subtype: selectedSubtype.value })
    const res = await fetch('/api/content/guardrails/resolve?' + params)
    const data = await res.json()
    currentGuardrail.value = data.guardrail
    isDefault.value = data.is_default
    if (data.guardrail) {
      form.value = {
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
    const res = await fetch('/api/content/guardrails', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), content_type: selectedContentType.value, subtype: selectedSubtype.value, ...form.value }),
    })
    const data = await res.json()
    if (data.guardrail) { currentGuardrail.value = data.guardrail; isDefault.value = false; await loadGuardrails() }
  } catch (e: unknown) { alert(String(e)) }
  saving.value = false
}

async function seedDefaults() {
  if (!selectedSiteId.value) return
  loading.value = true
  try {
    await fetch('/api/content/guardrails/seed', {
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
    const res = await fetch('/api/content/generate', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ site_id: Number(selectedSiteId.value), keyword: 'test', title: '\u0645\u0642\u0627\u0644\u0647 \u062a\u0633\u062a\u06cc', subtype: selectedSubtype.value }),
    })
    const data = await res.json()
    testResult.value = data.error ? '\u274c ' + data.error : '\u2705 ' + data.model + ' | ' + data.source
  } catch (e: unknown) { testResult.value = '\u274c ' + String(e) }
  testingGeneration.value = false
}

function addForbiddenWord() { if (newForbiddenWord.value.trim() && !form.value.forbidden_words.includes(newForbiddenWord.value.trim())) { form.value.forbidden_words.push(newForbiddenWord.value.trim()); newForbiddenWord.value = '' } }
function removeForbiddenWord(word: string) { form.value.forbidden_words = form.value.forbidden_words.filter(w => w !== word) }
function addAllowedTag() { if (newAllowedTag.value.trim() && !form.value.allowed_tags.includes(newAllowedTag.value.trim())) { form.value.allowed_tags.push(newAllowedTag.value.trim()); newAllowedTag.value = '' } }
function removeAllowedTag(tag: string) { form.value.allowed_tags = form.value.allowed_tags.filter(t => t !== tag) }

watch([selectedSiteId, selectedContentType, selectedSubtype], () => { resolveCurrent() })
watch(selectedSiteId, () => { loadGuardrails() })
if (p.sites.length > 0 && !selectedSiteId.value) { selectedSiteId.value = String(p.sites[0].id) }
</script>

<template>
  <Head title="اتاق فرماندهی محتوا" />
  <AppLayout>
    <VPageHeader title="🎯 اتاق فرماندهی محتوا" description="مدیریت گاردرایلس و پرامپت‌ها برای هر سایت." />
    <VAlert v-if="page.props.flash?.status" tone="success" class="mt-6">{{ page.props.flash.status }}</VAlert>

    <VCard class="mt-6">
      <div class="grid gap-4 md:grid-cols-4">
        <VSelect v-model="selectedSiteId" label="سایت" :options="p.sites.map(s => ({ label: s.name, value: String(s.id) }))" />
        <VSelect v-model="selectedContentType" label="نوع محتوا" :options="contentTypes" />
        <VSelect v-model="selectedSubtype" label="زیرنوع" :options="availableSubtypes.map(s => ({ label: (subtypeLabels[s] || s), value: s }))" />
        <div class="flex items-end gap-2">
          <VButton @click="resolveCurrent" :loading="loading" variant="secondary" class="flex-1">بارگذاری</VButton>
          <VButton @click="seedDefaults" variant="secondary">🌱</VButton>
        </div>
      </div>
      <div class="mt-3 flex items-center gap-3">
        <VBadge :tone="isDefault ? 'warning' : 'success'">{{ isDefault ? 'پیش‌دفعت' : 'سفارشیشده' }}</VBadge>
      </div>
    </VCard>

    <div class="mt-6 border-line flex gap-1 border-b">
      <button v-for="tab in [{id:'guardrails' as const, label:'🛡️ Guardrails'}, {id:'prompts' as const, label:'📝 Prompts'}, {id:'history' as const, label:'📊 History'}]" :key="tab.id" type="button" class="border-b-2 px-5 py-3 text-sm font-medium transition-colors" :class="activeTab === tab.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-muted hover:text-ink-strong'" @click="activeTab = tab.id">{{ tab.label }}</button>
    </div>

    <!-- Guardrails Tab -->
    <div v-if="activeTab === 'guardrails'" class="mt-6 grid gap-6 lg:grid-cols-2">
      <VCard title="📏 محدودیت‌های ساختاری">
        <div class="space-y-4">
          <div><label class="text-ink-strong text-sm font-semibold">حداکثر کاراکتر</label><input v-model.number="form.max_characters" type="number" min="500" max="50000" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /></div>
          <div class="grid grid-cols-2 gap-4">
            <div><label class="text-ink-strong text-sm font-semibold">حدالل کلمات</label><input v-model.number="form.min_words" type="number" min="50" max="10000" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /></div>
            <div><label class="text-ink-strong text-sm font-semibold">حداکثر کلمات</label><input v-model.number="form.max_words" type="number" min="100" max="50000" class="border-line mt-1 w-full rounded-xl border px-4 py-2.5 text-sm" /></div>
          </div>
          <VSelect v-model="form.allowed_tone" label="لحن" :options="toneOptions" />
          <div>
            <label class="text-ink-strong text-sm font-semibold">تگ‌های HTML مجاز</label>
            <div class="mt-2 flex flex-wrap gap-1.5"><VBadge v-for="tag in form.allowed_tags" :key="tag" tone="info" class="cursor-pointer" @click="removeAllowedTag(tag)">{{ tag }} ✕</VBadge></div>
            <div class="mt-2 flex gap-2"><input v-model="newAllowedTag" placeholder="تگ جدید" class="border-line flex-1 rounded-xl border px-3 py-1.5 text-sm" @keyup.enter="addAllowedTag" /><VButton size="sm" @click="addAllowedTag" variant="secondary">+</VButton></div>
          </div>
        </div>
      </VCard>

      <VCard title="✅ الزامات محتوایی">
        <div class="space-y-4">
          <label class="flex items-center gap-3 cursor-pointer"><input v-model="form.require_cta" type="checkbox" class="h-4 w-4 rounded" /><span class="text-ink-strong text-sm">CTA اجباری</span></label>
          <label class="flex items-center gap-3 cursor-pointer"><input v-model="form.require_faq" type="checkbox" class="h-4 w-4 rounded" /><span class="text-ink-strong text-sm">FAQ اجباری</span></label>
          <label class="flex items-center gap-3 cursor-pointer"><input v-model="form.require_internal_links" type="checkbox" class="h-4 w-4 rounded" /><span class="text-ink-strong text-sm">لینکسازی داخلی اجباری</span></label>
          <div v-if="form.require_internal_links"><label class="text-ink-muted text-xs">حدالل تعداد لینک</label><input v-model.number="form.min_internal_links" type="number" min="0" max="20" class="border-line mt-1 w-32 rounded-xl border px-3 py-1.5 text-sm" /></div>
          <label class="flex items-center gap-3 cursor-pointer"><input v-model="form.require_brand_mention" type="checkbox" class="h-4 w-4 rounded" /><span class="text-ink-strong text-sm">ذکر نام برند اجباری</span></label>
          <div>
            <label class="text-ink-strong text-sm font-semibold">کلمات ممنوعه</label>
            <div class="mt-2 flex flex-wrap gap-1.5"><VBadge v-for="word in form.forbidden_words" :key="word" tone="danger" class="cursor-pointer" @click="removeForbiddenWord(word)">{{ word }} ✕</VBadge></div>
            <div class="mt-2 flex gap-2"><input v-model="newForbiddenWord" placeholder="کلمه ممنوعه" class="border-line flex-1 rounded-xl border px-3 py-1.5 text-sm" @keyup.enter="addForbiddenWord" /><VButton size="sm" @click="addForbiddenWord" variant="secondary">+</VButton></div>
          </div>
        </div>
      </VCard>

      <div class="lg:col-span-2 flex items-center gap-3">
        <VButton @click="saveGuardrail" :loading="saving" variant="primary">💾 ذخیره</VButton>
        <VButton @click="testGenerate" :loading="testingGeneration" variant="secondary">🧪 تست تولید</VButton>
        <VBadge v-if="testResult" :tone="testResult.includes('OK') ? 'success' : 'danger'" class="text-xs">{{ testResult }}</VBadge>
      </div>
    </div>

    <!-- Prompts Tab -->
    <div v-if="activeTab === 'prompts'" class="mt-6 space-y-6">
      <VCard title="📝 پرامپت سیستم">
        <textarea v-model="form.system_prompt" rows="12" dir="auto" class="border-line w-full rounded-xl border p-4 text-sm leading-7 font-mono" placeholder="تو یک متخصص سئو..." />
      </VCard>
      <VCard title="📋 قالب پرامپت کاربر">
        <textarea v-model="form.user_prompt_template" rows="12" dir="auto" class="border-line w-full rounded-xl border p-4 text-sm leading-7 font-mono" placeholder="{title} {keyword} {siteName}" />
      </VCard>
      <VButton @click="saveGuardrail" :loading="saving" variant="primary">💾 ذخیره</VButton>
    </div>

    <!-- History Tab -->
    <div v-if="activeTab === 'history'" class="mt-6">
      <VCard title="📊 گاردرایلس">
        <div v-if="guardrails.length === 0" class="text-ink-muted text-sm py-8 text-center">هنوز گاردرایلسی تنظیم نشده.</div>
        <div v-else class="space-y-2">
          <div v-for="g in guardrails" :key="g.id ?? 'x'" class="border-line flex items-center justify-between rounded-xl border p-3 hover:bg-brand-50 cursor-pointer" @click="selectedContentType = g.content_type; selectedSubtype = g.subtype; resolveCurrent()">
            <div class="flex items-center gap-3"><VBadge :tone="g.site_id ? 'info' : 'warning'">{{ g.site_id ? 'سایت' : 'سازمان' }}</VBadge><span class="text-sm font-medium">{{ g.content_type }} / {{ subtypeLabels[g.subtype] || g.subtype }}</span></div>
            <div class="flex items-center gap-2 text-xs text-ink-muted"><span>{{ g.min_words }}-{{ g.max_words }}</span><VBadge :tone="g.is_active ? 'success' : 'danger'" size="sm">{{ g.is_active ? 'فعال' : 'غیرفعال' }}</VBadge></div>
          </div>
        </div>
      </VCard>
    </div>
  </AppLayout>
</template>
