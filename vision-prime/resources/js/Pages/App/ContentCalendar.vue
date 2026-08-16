<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { jalaaliMonthLength, toGregorian, toJalaali } from 'jalaali-js'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { commandStatusLabels, contentScopeLabels, labelOf } from '@/lib/labels'
import { formatJalaliDate } from '@/lib/locale'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'
import VModal from '@/shared/ui/VModal.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'

interface CalendarItem {
  id: number
  site_id: number
  title: string
  content_type: string
  status: string
  scheduled_for: string | null
  published_at: string | null
  created_at: string | null
  confidence_score: number | null
  risk_tier: string
}

interface UrlProfile {
  id: number
  site_id: number
  canonical_url: string
  content_type: string
}

interface PublishSlot {
  weekday: number
  label: string
  datetime: string
  avg_clicks: number
  samples: number
}

interface CalendarProps {
  items: CalendarItem[]
  itemsByDate: Record<string, CalendarItem[]>
  sites: { id: number; name: string }[]
  profiles: UrlProfile[]
  subtypes: Record<string, string>
  suggestions: Record<string, PublishSlot>
  from: string
  to: string
  siteFilter: number
}

const props = defineProps<CalendarProps>()

// ——— حالت تقویم: ماه جلالی یا هفته ———
type ViewMode = 'month' | 'week'
const mode = ref<ViewMode>('month')

const today = toJalaali(new Date())
const jy = ref(today.jy)
const jm = ref(today.jm)

// شنبهٔ هفتهٔ جاری (گرگوری) به‌عنوان لنگر نمای هفتگی
function saturdayOf(date: Date): Date {
  const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()))
  const offset = (d.getUTCDay() + 1) % 7
  d.setUTCDate(d.getUTCDate() - offset)
  return d
}
const weekStart = ref<string>(toDateString(saturdayOf(new Date())))

const siteFilter = ref<string>(String(props.siteFilter || 0))

const weekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']
const weekDayFull = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه']

function toDateString(d: Date): string {
  return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${String(d.getUTCDate()).padStart(2, '0')}`
}

function addDays(dateStr: string, days: number): string {
  const d = new Date(`${dateStr}T00:00:00Z`)
  d.setUTCDate(d.getUTCDate() + days)
  return toDateString(d)
}

/** سلول‌های ماه جلالی: هر سلول = { jd, gregorian } یا null برای جای خالی */
const monthGrid = computed<(null | { jd: number; gregorian: string })[]>(() => {
  const length = jalaaliMonthLength(jy.value, jm.value)
  const first = toGregorian(jy.value, jm.value, 1)
  const firstWeekday = new Date(Date.UTC(first.gy, first.gm - 1, first.gd)).getUTCDay()
  const offset = (firstWeekday + 1) % 7

  const cells: (null | { jd: number; gregorian: string })[] = Array.from({ length: offset }, () => null)
  for (let day = 1; day <= length; day++) {
    const g = toGregorian(jy.value, jm.value, day)
    cells.push({ jd: day, gregorian: `${g.gy}-${String(g.gm).padStart(2, '0')}-${String(g.gd).padStart(2, '0')}` })
  }
  return cells
})

/** سلول‌های هفته: ۷ روز از شنبه تا جمعه */
const weekCells = computed(() =>
  Array.from({ length: 7 }, (_, i) => ({ day: weekDayFull[i], gregorian: addDays(weekStart.value, i) })),
)

const monthLabel = computed(() => `${jm.value} / ${jy.value}`)

const weekLabel = computed(() => {
  const start = new Date(`${weekStart.value}T00:00:00Z`)
  const j = toJalaali(start)
  return `هفتهٔ ${j.jd} ${j.jm}/${j.jy}`
})

function moveMonth(delta: number): void {
  let m = jm.value + delta
  let y = jy.value
  if (m < 1) {
    m = 12
    y -= 1
  } else if (m > 12) {
    m = 1
    y += 1
  }
  jy.value = y
  jm.value = m
  fetchRange()
}

function moveWeek(delta: number): void {
  weekStart.value = addDays(weekStart.value, delta * 7)
  fetchRange()
}

function goToday(): void {
  const t = toJalaali(new Date())
  jy.value = t.jy
  jm.value = t.jm
  weekStart.value = toDateString(saturdayOf(new Date()))
  fetchRange()
}

function switchMode(next: ViewMode): void {
  mode.value = next
  fetchRange()
}

function fetchRange(): void {
  const pad = (n: number): string => String(n).padStart(2, '0')
  let from: string
  let to: string
  if (mode.value === 'week') {
    from = weekStart.value
    to = addDays(weekStart.value, 6)
  } else {
    const first = toGregorian(jy.value, jm.value, 1)
    const last = toGregorian(jy.value, jm.value, jalaaliMonthLength(jy.value, jm.value))
    from = `${first.gy}-${pad(first.gm)}-${pad(first.gd)}`
    to = `${last.gy}-${pad(last.gm)}-${pad(last.gd)}`
  }
  router.get(
    '/app/content-calendar',
    {
      from,
      to,
      site: siteFilter.value && siteFilter.value !== '0' ? siteFilter.value : undefined,
    },
    { preserveState: true, preserveScroll: true, replace: true },
  )
}

function applySiteFilter(): void {
  fetchRange()
}

// ——— دیالوگ زمان‌بندی ———
const selected = ref<CalendarItem | null>(null)
const dialogOpen = ref(false)
const scheduledValue = ref('')
const saving = ref(false)

const selectedItem = computed(() => props.items.find((i) => i.id === selected.value?.id) ?? selected.value)

function suggestionFor(item: CalendarItem): PublishSlot | null {
  return props.suggestions[item.site_id] ?? null
}

function openDialog(item: CalendarItem): void {
  selected.value = item
  const suggestion = suggestionFor(item)
  scheduledValue.value = toLocalInput(item.scheduled_for ?? item.created_at ?? '') || (suggestion ? toLocalInput(suggestion.datetime) : '')
  dialogOpen.value = true
}

function toLocalInput(value: string | null): string {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n: number): string => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function canSchedule(item: CalendarItem): boolean {
  return item.status === 'pending_approval' || item.status === 'scheduled'
}

function saveSchedule(): void {
  if (!selected.value || !scheduledValue.value) return
  saving.value = true
  router.post(
    `/app/content-calendar/commands/${selected.value.id}/schedule`,
    { action: 'schedule', scheduled_for: scheduledValue.value },
    {
      preserveScroll: true,
      onFinish: () => {
        saving.value = false
        dialogOpen.value = false
      },
    },
  )
}

function cancelSchedule(): void {
  if (!selected.value) return
  saving.value = true
  router.post(
    `/app/content-calendar/commands/${selected.value.id}/schedule`,
    { action: 'cancel' },
    {
      preserveScroll: true,
      onFinish: () => {
        saving.value = false
        dialogOpen.value = false
      },
    },
  )
}

function publishNow(item: CalendarItem): void {
  if (!item) return
  saving.value = true
  router.post(
    `/app/content-calendar/commands/${item.id}/schedule`,
    { action: 'publish_now' },
    {
      preserveScroll: true,
      onFinish: () => {
        saving.value = false
        dialogOpen.value = false
      },
    },
  )
}

// ——— ساخت پیش‌نویس زمان‌بندی‌شده ———
const createOpen = ref(false)
const createSaving = ref(false)
const createSite = ref<string>('')
const createProfile = ref<string>('')
const createTitle = ref('')
const createSubtype = ref<string>('')
const createScheduledFor = ref('')
const createError = ref('')

const filteredProfiles = computed(() =>
  props.profiles.filter((p) => (createSite.value ? p.site_id === Number(createSite.value) : true)),
)

const filteredSubtypes = computed(() => {
  const profile = props.profiles.find((p) => p.id === Number(createProfile.value))
  const type = profile?.content_type === 'product' ? 'product' : 'article'
  return Object.entries(props.subtypes).filter(([key]) => {
    if (type === 'product') return ['short_desc', 'long_desc', 'comparison', 'technical'].includes(key)
    return !['short_desc', 'long_desc', 'technical'].includes(key)
  })
})

function openCreate(): void {
  createSite.value = props.sites.length ? String(props.sites[0].id) : ''
  createProfile.value = ''
  createTitle.value = ''
  createSubtype.value = ''
  createError.value = ''
  createScheduledFor.value = suggestionForSite(createSite.value)
  createOpen.value = true
}

function suggestionForSite(siteId: string): string {
  const s = props.suggestions[Number(siteId)]
  return s ? toLocalInput(s.datetime) : ''
}

function onCreateSiteChange(): void {
  createProfile.value = ''
  createSubtype.value = ''
  createScheduledFor.value = suggestionForSite(createSite.value)
}

function submitDraft(): void {
  if (!createSite.value || !createProfile.value || !createScheduledFor.value) {
    createError.value = 'سایت، URL و زمان انتشار را کامل کنید.'
    return
  }
  createError.value = ''
  createSaving.value = true
  router.post(
    '/app/content-calendar/drafts',
    {
      site_id: createSite.value,
      url_profile_id: createProfile.value,
      title: createTitle.value,
      subtype: createSubtype.value || undefined,
      scheduled_for: createScheduledFor.value,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        createSaving.value = false
        createOpen.value = false
      },
    },
  )
}

const statusTone = (status: string): 'success' | 'warning' | 'info' | 'neutral' =>
  status === 'executed'
    ? 'success'
    : status === 'rolled_back'
      ? 'warning'
      : status === 'scheduled'
        ? 'info'
        : status === 'pending_approval'
          ? 'warning'
          : 'neutral'

const siteName = (id: number): string => props.sites.find((s) => s.id === id)?.name ?? '—'

// ——— دراگ‌اندروپ: جابجایی پیش‌نویس بین روزها ———
const draggingId = ref<number | null>(null)
const dragOverDate = ref<string>('')

function onDragStart(item: CalendarItem, event: DragEvent): void {
  if (!canSchedule(item)) return
  draggingId.value = item.id
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(item.id))
  }
}

function onDragEnd(): void {
  draggingId.value = null
  dragOverDate.value = ''
}

function onDrop(gregorianDate: string): void {
  dragOverDate.value = ''
  if (draggingId.value === null) return
  const item = props.items.find((i) => i.id === draggingId.value)
  draggingId.value = null
  if (!item || !canSchedule(item)) return

  // زمان کنونی پیش‌نویس (ساعت:دقیقه از موعد یا تاریخ ساخت) را نگه می‌داریم و فقط روز را عوض می‌کنیم
  const source = item.scheduled_for ?? item.created_at
  let hour = 10
  let minute = 0
  if (source) {
    const d = new Date(source)
    if (!Number.isNaN(d.getTime())) {
      hour = d.getHours()
      minute = d.getMinutes()
    }
  }
  const pad = (n: number): string => String(n).padStart(2, '0')
  const [y, m, day] = gregorianDate.split('-')
  const newValue = `${y}-${m}-${day}T${pad(hour)}:${pad(minute)}`

  saving.value = true
  router.post(
    `/app/content-calendar/commands/${item.id}/schedule`,
    { action: 'schedule', scheduled_for: newValue },
    { preserveScroll: true, onFinish: () => (saving.value = false) },
  )
}

const isDragOver = (gregorian: string): boolean => dragOverDate.value === gregorian

function onCellDragOver(gregorian: string, event: DragEvent): void {
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  dragOverDate.value = gregorian
}

function onCellDragLeave(): void {
  dragOverDate.value = ''
}
</script>

<template>
  <Head title="تقویم محتوایی" />
  <AppLayout>
    <VPageHeader
      title="تقویم محتوایی"
      description="برنامه‌ریزی انتشار پیش‌نویس‌های مقاله/محصول — پیش‌نویس‌های زمان‌بندی‌شده در موعد مقرر از pipeline انتشار خودکار عبور می‌کنند."
    />

    <div class="mt-8 space-y-6">
      <!-- نوار ابزار -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <VButton size="sm" variant="secondary" @click="mode === 'week' ? moveWeek(-1) : moveMonth(-1)">
            قبلی
          </VButton>
          <VButton size="sm" variant="secondary" @click="goToday">امروز</VButton>
          <VButton size="sm" variant="secondary" @click="mode === 'week' ? moveWeek(1) : moveMonth(1)">
            بعدی
          </VButton>
          <span class="text-ink-strong text-sm font-bold">{{ mode === 'week' ? weekLabel : monthLabel }}</span>
          <div class="border-line flex overflow-hidden rounded-md border">
            <button
              type="button"
              class="px-3 py-1.5 text-xs font-semibold transition-colors"
              :class="mode === 'month' ? 'bg-brand-50 text-brand-700' : 'text-ink-muted hover:text-ink-strong'"
              @click="switchMode('month')"
            >
              ماه
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-xs font-semibold transition-colors"
              :class="mode === 'week' ? 'bg-brand-50 text-brand-700' : 'text-ink-muted hover:text-ink-strong'"
              @click="switchMode('week')"
            >
              هفته
            </button>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-56">
            <VSelect
              v-model="siteFilter"
              label="سایت"
              :options="[
                { label: 'همهٔ سایت‌ها', value: '0' },
                ...sites.map((s) => ({ label: s.name, value: String(s.id) })),
              ]"
              @change="applySiteFilter"
            />
          </div>
          <VButton size="sm" @click="openCreate">پیش‌نویس زمان‌بندی‌شده</VButton>
        </div>
      </div>

      <!-- شبکهٔ ماه -->
      <div v-if="mode === 'month'" class="rounded-card border-line overflow-hidden border bg-surface">
        <div class="border-line grid grid-cols-7 border-b bg-surface-muted">
          <div v-for="(day, i) in weekDays" :key="i" class="px-2 py-2 text-center text-xs font-bold">
            {{ day }}
          </div>
        </div>
        <div class="grid grid-cols-7">
          <template v-for="(cell, i) in monthGrid" :key="i">
            <div
              v-if="cell"
              class="border-line min-h-28 border-b border-l p-2 last:border-l-0 transition-colors"
              :class="isDragOver(cell.gregorian) ? 'bg-brand-50/60 ring-2 ring-inset ring-brand-300' : ''"
              @dragover="onCellDragOver(cell.gregorian, $event)"
              @dragleave="onCellDragLeave"
              @drop="onDrop(cell.gregorian)"
            >
              <p class="text-ink-muted mb-1.5 text-xs font-semibold">{{ cell.jd }}</p>
              <div class="space-y-1">
                <button
                  v-for="item in itemsByDate[cell.gregorian] ?? []"
                  :key="item.id"
                  type="button"
                  class="bg-brand-50 text-brand-800 hover:bg-brand-100 block w-full truncate rounded px-1.5 py-1 text-left text-[11px] font-medium"
                  :class="{ 'cursor-grab': canSchedule(item), 'opacity-60': draggingId === item.id }"
                  :title="item.title"
                  :draggable="canSchedule(item)"
                  @click="openDialog(item)"
                  @dragstart="onDragStart(item, $event)"
                  @dragend="onDragEnd"
                >
                  {{ item.title }}
                </button>
              </div>
            </div>
            <div v-else class="bg-surface-muted/50 min-h-28 border-line border-b" />
          </template>
        </div>
      </div>

      <!-- شبکهٔ هفته -->
      <div v-else class="rounded-card border-line overflow-hidden border bg-surface">
        <div class="grid grid-cols-7">
          <div
            v-for="cell in weekCells"
            :key="cell.gregorian"
            class="border-line min-h-40 border-b border-l p-2 last:border-l-0 transition-colors"
            :class="isDragOver(cell.gregorian) ? 'bg-brand-50/60 ring-2 ring-inset ring-brand-300' : ''"
            @dragover="onCellDragOver(cell.gregorian, $event)"
            @dragleave="onCellDragLeave"
            @drop="onDrop(cell.gregorian)"
          >
            <p class="text-ink-muted mb-1.5 text-xs font-semibold">{{ cell.day }}</p>
            <div class="space-y-1">
              <button
                v-for="item in itemsByDate[cell.gregorian] ?? []"
                :key="item.id"
                type="button"
                class="bg-brand-50 text-brand-800 hover:bg-brand-100 block w-full truncate rounded px-1.5 py-1 text-left text-[11px] font-medium"
                :class="{ 'cursor-grab': canSchedule(item), 'opacity-60': draggingId === item.id }"
                :title="item.title"
                :draggable="canSchedule(item)"
                @click="openDialog(item)"
                @dragstart="onDragStart(item, $event)"
                @dragend="onDragEnd"
              >
                {{ item.title }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- لیست جزئیات -->
      <div v-if="items.length" class="rounded-card border-line border bg-surface p-5">
        <p class="text-ink-strong mb-3 text-sm font-semibold">پیش‌نویس‌های این محدوده</p>
        <ul class="divide-line divide-y">
          <li
            v-for="item in items"
            :key="item.id"
            class="flex flex-wrap items-center justify-between gap-3 py-3"
          >
            <div class="min-w-0">
              <p class="text-ink-strong truncate text-sm font-medium">{{ item.title }}</p>
              <p class="text-ink-muted mt-0.5 text-xs">
                {{ siteName(item.site_id) }}
                <template v-if="item.scheduled_for">
                  · زمان‌بندی: {{ formatJalaliDate(item.scheduled_for) }}
                </template>
                <template v-else-if="item.published_at">
                  · منتشر: {{ formatJalaliDate(item.published_at) }}
                </template>
                <template v-if="item.confidence_score !== null">
                  · اطمینان: {{ item.confidence_score }}
                </template>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <VBadge tone="info">{{ labelOf(contentScopeLabels, item.content_type) }}</VBadge>
              <VBadge :tone="statusTone(item.status)">
                {{ labelOf(commandStatusLabels, item.status) }}
              </VBadge>
              <VButton v-if="canSchedule(item)" size="sm" variant="secondary" @click="openDialog(item)">
                {{ item.status === 'scheduled' ? 'تغییر زمان' : 'زمان‌بندی' }}
              </VButton>
              <VButton
                v-if="item.status === 'scheduled'"
                size="sm"
                variant="primary"
                @click="publishNow(item)"
              >
                انتشار فوری
              </VButton>
            </div>
          </li>
        </ul>
      </div>

      <p v-else class="text-ink-muted rounded-card border-line border bg-surface p-6 text-center text-sm">
        در این محدوده پیش‌نویس مقاله/محصولی وجود ندارد.
      </p>
    </div>

    <!-- دیالوگ زمان‌بندی -->
    <VModal v-model="dialogOpen" title="زمان‌بندی انتشار" size="sm">
      <div v-if="selectedItem" class="space-y-4">
        <div>
          <p class="text-ink-strong text-sm font-semibold">{{ selectedItem.title }}</p>
          <p class="text-ink-muted mt-1 text-xs">
            {{ siteName(selectedItem.site_id) }} ·
            {{ labelOf(contentScopeLabels, selectedItem.content_type) }} ·
            {{ labelOf(commandStatusLabels, selectedItem.status) }}
          </p>
        </div>
        <template v-if="selectedItem.status === 'scheduled'">
          <div class="rounded-ui bg-surface-muted px-3 py-2 text-xs">
            <p class="text-ink-strong font-semibold">⏱ انتشار فوری</p>
            <p class="text-ink-muted mt-0.5">
              موعد را به همین لحظه می‌رساند و پیش‌نویس بلافاصله از گیت‌های انتشار خودکار عبور می‌کند.
            </p>
          </div>
          <div class="flex flex-wrap justify-end gap-2">
            <VButton variant="danger" :loading="saving" @click="cancelSchedule">لغو زمان‌بندی</VButton>
            <VButton :loading="saving" @click="publishNow(selectedItem)">انتشار فوری</VButton>
          </div>
        </template>
        <template v-else>
          <div v-if="suggestionFor(selectedItem)" class="rounded-ui bg-brand-50 px-3 py-2 text-xs">
            <p class="text-brand-800 font-semibold">✨ پیشنهاد سیستم</p>
            <p class="text-brand-700 mt-0.5">
              {{ suggestionFor(selectedItem)!.label }} (میانگین {{ suggestionFor(selectedItem)!.avg_clicks }} کلیک در
              {{ suggestionFor(selectedItem)!.samples }} روز) — پیشنهاد به‌صورت خودکار پر شده است.
            </p>
          </div>
          <VInput
            v-model="scheduledValue"
            label="تاریخ و ساعت انتشار"
            type="datetime-local"
            hint="در موعد تعیین‌شده، پیش‌نویس از گیت‌های انتشار خودکار عبور می‌کند."
          />
          <div class="flex flex-wrap justify-end gap-2">
            <VButton variant="secondary" @click="dialogOpen = false">بستن</VButton>
            <VButton :loading="saving" :disabled="!scheduledValue" @click="saveSchedule">ثبت زمان</VButton>
          </div>
        </template>
      </div>
    </VModal>

    <!-- دیالوگ ساخت پیش‌نویس زمان‌بندی‌شده -->
    <VModal v-model="createOpen" title="پیش‌نویس زمان‌بندی‌شده" size="md">
      <div class="space-y-4">
        <p class="text-ink-muted text-xs">
          پیش‌نویس مقاله/محصول ساخته می‌شود و به صف بازبینی می‌رود؛ پس از تأیید، در موعد تعیین‌شده از
          گیت‌های انتشار خودکار عبور می‌کند.
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
          <VSelect
            v-model="createSite"
            label="سایت"
            :options="sites.map((s) => ({ label: s.name, value: String(s.id) }))"
            @change="onCreateSiteChange"
          />
          <VSelect
            v-model="createProfile"
            label="URL / صفحهٔ هدف"
            :options="filteredProfiles.map((p) => ({ label: p.canonical_url, value: String(p.id) }))"
          />
        </div>
        <VInput v-model="createTitle" label="عنوان (اختیاری)" placeholder="مثلاً: راهنمای جامع سئو تکنیکال" />
        <VSelect
          v-model="createSubtype"
          label="زیرنوع"
          :options="filteredSubtypes.map(([key, label]) => ({ label, value: key }))"
        />
        <VInput
          v-model="createScheduledFor"
          label="تاریخ و ساعت انتشار"
          type="datetime-local"
          hint="پیشنهاد هوشمند بر اساس بهترین روز هفته از دادهٔ GSC پر شده است."
        />
        <p v-if="createError" class="text-sm font-medium text-red-600">{{ createError }}</p>
        <div class="flex flex-wrap justify-end gap-2">
          <VButton variant="secondary" @click="createOpen = false">بستن</VButton>
          <VButton :loading="createSaving" @click="submitDraft">ساخت پیش‌نویس</VButton>
        </div>
      </div>
    </VModal>
  </AppLayout>
</template>
