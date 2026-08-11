<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VInput from '@/shared/ui/VInput.vue'
import VModal from '@/shared/ui/VModal.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect from '@/shared/ui/VSelect.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'

interface RecommendationRow {
  id: number
  title: string
  body: string
  priority: string
  status: string
  dueAt: string | null
  createdAt: string | null
  site: { id: number; name: string } | null
  owner: { id: number; name: string } | null
  targetUrl: string | null
  commandId: number | null
}

const props = defineProps<{
  recommendations: { data: RecommendationRow[] }
  members: { id: number; name: string }[]
}>()

const priorityMeta: Record<string, { label: string; tone: 'danger' | 'warning' | 'info' }> = {
  high: { label: 'اولویت بالا', tone: 'danger' },
  medium: { label: 'اولویت متوسط', tone: 'warning' },
  low: { label: 'اولویت کم', tone: 'info' },
}

const statusMeta: Record<
  string,
  { label: string; tone: 'success' | 'info' | 'neutral' | 'danger' }
> = {
  active: { label: 'فعال', tone: 'success' },
  draft: { label: 'پیش‌نویس', tone: 'info' },
  done: { label: 'انجام‌شده', tone: 'neutral' },
  cancelled: { label: 'لغو شده', tone: 'danger' },
}

const priorityOptions = [
  { label: 'اولویت کم', value: 'low' },
  { label: 'اولویت متوسط', value: 'medium' },
  { label: 'اولویت بالا', value: 'high' },
]
const statusOptions = [
  { label: 'فعال', value: 'active' },
  { label: 'پیش‌نویس', value: 'draft' },
  { label: 'انجام‌شده', value: 'done' },
  { label: 'لغو شده', value: 'cancelled' },
]
const memberOptions = props.members.map((member) => ({
  label: member.name,
  value: String(member.id),
}))

const commandTypeOptions = [
  { label: 'به‌روزرسانی عنوان متا', value: 'update_meta_title' },
  { label: 'به‌روزرسانی توضیحات متا', value: 'update_meta_description' },
]

const editOpen = ref(false)
const editForm = useForm({
  owner_id: '',
  due_at: '',
  priority: 'medium',
  status: 'active',
})
let editing: RecommendationRow | null = null

function openEdit(recommendation: RecommendationRow): void {
  editing = recommendation
  editForm.clearErrors()
  editForm.reset()
  editForm.owner_id = recommendation.owner ? String(recommendation.owner.id) : ''
  editForm.due_at = recommendation.dueAt ? recommendation.dueAt.slice(0, 10) : ''
  editForm.priority = recommendation.priority
  editForm.status = recommendation.status
  editOpen.value = true
}

function saveEdit(): void {
  if (!editing) return
  editForm.put(`/app/recommendations/${editing.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      editOpen.value = false
    },
  })
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    dateStyle: 'medium',
    timeZone: 'Asia/Tehran',
  }).format(new Date(value))
}

const convertOpen = ref(false)
const convertForm = useForm({
  type: 'update_meta_title',
  target_url: '',
  new_value: '',
})
let converting: RecommendationRow | null = null

function openConvert(recommendation: RecommendationRow): void {
  converting = recommendation
  convertForm.clearErrors()
  convertForm.reset()
  convertForm.type = 'update_meta_title'
  convertForm.target_url = recommendation.targetUrl ?? ''
  convertForm.new_value = ''
  convertOpen.value = true
}

function submitConvert(): void {
  if (!converting) return
  convertForm.post(`/app/recommendations/${converting.id}/command`, {
    preserveScroll: true,
    onSuccess: () => {
      convertOpen.value = false
    },
  })
}
</script>

<template>
  <Head title="پیشنهادها" />
  <AppLayout>
    <VPageHeader
      title="پیشنهادها"
      description="پیشنهادهای قابل پیگیری برای رشد، محتوا و بهینه‌سازی."
    >
      <template #actions>
        <VButton href="/app/recommendations/create">پیشنهاد جدید</VButton>
      </template>
    </VPageHeader>

    <div v-if="recommendations.data.length" class="mt-8 space-y-4">
      <article
        v-for="recommendation in recommendations.data"
        :key="recommendation.id"
        class="rounded-card border-line bg-surface shadow-card border p-5"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2">
            <VBadge :tone="statusMeta[recommendation.status]?.tone ?? 'neutral'">{{
              statusMeta[recommendation.status]?.label ?? recommendation.status
            }}</VBadge>
            <VBadge :tone="priorityMeta[recommendation.priority]?.tone ?? 'neutral'">{{
              priorityMeta[recommendation.priority]?.label ?? recommendation.priority
            }}</VBadge>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <p v-if="recommendation.site" class="text-ink-muted text-sm">
              سایت: {{ recommendation.site.name }}
            </p>
            <VButton
              v-if="recommendation.commandId"
              size="sm"
              variant="secondary"
              :href="`/app/commands/${recommendation.commandId}`"
            >
              مشاهدهٔ تغییر اجرایی
            </VButton>
            <VButton
              v-else-if="['active', 'draft'].includes(recommendation.status)"
              size="sm"
              @click="openConvert(recommendation)"
            >
              تبدیل به تغییر اجرایی
            </VButton>
            <VButton size="sm" variant="secondary" @click="openEdit(recommendation)">ویرایش</VButton>
          </div>
        </div>
        <h3 class="text-ink-strong mt-3 font-semibold">{{ recommendation.title }}</h3>
        <p v-if="recommendation.body" class="text-ink-muted mt-1 text-sm leading-7">
          {{ recommendation.body }}
        </p>
        <div class="text-ink-muted mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
          <span>مالک: {{ recommendation.owner?.name ?? '—' }}</span>
          <span>مهلت: {{ formatDate(recommendation.dueAt) }}</span>
          <span>تاریخ ثبت: {{ formatDate(recommendation.createdAt) }}</span>
        </div>
      </article>
    </div>

    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز پیشنهادی ثبت نشده است"
      description="اولین پیشنهاد را بسازید یا فرصت‌های رشد را مستقیماً به پیشنهاد تبدیل کنید."
      action-label="ساخت پیشنهاد"
      @action="$inertia.visit('/app/recommendations/create')"
    />

    <VModal v-model="convertOpen" title="تبدیل به تغییر اجرایی" size="md">
      <p class="text-ink-muted -mt-3 mb-5 text-sm leading-6">
        یک تغییر قابل اجرا (تأیید مشتری) از این پیشنهاد ساخته می‌شود.
      </p>
      <form class="space-y-5" @submit.prevent="submitConvert">
        <VSelect
          v-model="convertForm.type"
          label="نوع تغییر"
          :options="commandTypeOptions"
          :error="convertForm.errors.type"
        />
        <VInput
          v-model="convertForm.target_url"
          label="آدرس صفحهٔ هدف"
          dir="ltr"
          placeholder="https://example.ir/page"
          hint="اگر از فرصت/ریسک ساخته شده باشد، به‌صورت خودکار پر می‌شود."
          :error="convertForm.errors.target_url"
        />
        <VTextarea
          v-model="convertForm.new_value"
          label="محتوای جدید"
          :rows="3"
          :placeholder="convertForm.type === 'update_meta_title' ? 'عنوان متا جدید' : 'توضیحات متا جدید'"
          hint="مقداری که روی سایت اعمال می‌شود؛ مشتری قبل از تأیید آن را می‌بیند."
          :error="convertForm.errors.new_value"
        />
        <div class="flex items-center justify-end gap-3 border-t pt-4">
          <VButton type="button" variant="secondary" @click="convertOpen = false">انصراف</VButton>
          <VButton type="submit" :loading="convertForm.processing">ایجاد تغییر اجرایی</VButton>
        </div>
      </form>
    </VModal>

    <VModal v-model="editOpen" title="ویرایش پیشنهاد" size="sm">
      <form class="space-y-5" @submit.prevent="saveEdit">
        <VSelect
          v-model="editForm.owner_id"
          label="مالک (مسئول انجام)"
          :options="memberOptions"
          hint="در صورت انتخاب نشدن، بدون مالک ذخیره می‌شود."
          :error="editForm.errors.owner_id"
        />
        <VInput v-model="editForm.due_at" label="مهلت انجام" type="date" :error="editForm.errors.due_at" />
        <div class="grid gap-4 sm:grid-cols-2">
          <VSelect
            v-model="editForm.priority"
            label="اولویت"
            :options="priorityOptions"
            :error="editForm.errors.priority"
          />
          <VSelect
            v-model="editForm.status"
            label="وضعیت"
            :options="statusOptions"
            :error="editForm.errors.status"
          />
        </div>
        <div class="flex items-center justify-end gap-3 border-t pt-4">
          <VButton type="button" variant="secondary" @click="editOpen = false">انصراف</VButton>
          <VButton type="submit" :loading="editForm.processing">ذخیره تغییرات</VButton>
        </div>
      </form>
    </VModal>
  </AppLayout>
</template>
