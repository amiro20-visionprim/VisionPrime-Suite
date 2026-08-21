<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface Draft {
  id: number
  title: string
  slug: string
  quality_score: number
  subtype: string
  model_used: string
  status: string
  created_at: string
  site: { id: number; name: string }
}

const props = defineProps<{
  drafts: { data: Draft[]; last_page: number; current_page: number; total: number }
  filters: { status: string; search: string }
}>()

const search = ref(props.filters.search)
const statusFilter = ref(props.filters.status)

function applyFilters() {
  router.get('/app/ai-drafts', { status: statusFilter.value, search: search.value }, { preserveState: true })
}

const statusLabels: Record<string, string> = {
  draft: 'پیش‌نویس',
  review: 'در حال بررسی',
  published: 'منتشر شده',
  archived: 'بایگانی شده',
}

const statusTones: Record<string, string> = {
  draft: 'warning',
  review: 'info',
  published: 'success',
  archived: 'neutral',
}
</script>

<template>
  <Head title="پیش‌نویس‌های محتوا" />
  <AppLayout>
    <VPageHeader title="پیش‌نویس‌های محتوا" description="مقاله‌ها و محتواهای تولید شده با هوش مصنوعی.">
      <template #actions>
        <Link href="/app/ai-drafts/article/create">
          <VButton variant="primary">تولید مقاله جدید</VButton>
        </Link>
      </template>
    </VPageHeader>

    <VCard class="mt-6">
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <select v-model="statusFilter" class="border-line rounded-xl border px-3 py-2 text-sm" @change="applyFilters">
          <option value="">همه وضعیت‌ها</option>
          <option value="draft">پیش‌نویس</option>
          <option value="review">در حال بررسی</option>
          <option value="published">منتشر شده</option>
          <option value="archived">بایگانی</option>
        </select>
        <input v-model="search" type="text" placeholder="جستجوی عنوان..." class="border-line rounded-xl border px-3 py-2 text-sm" @keyup.enter="applyFilters" />
        <VButton variant="secondary" size="sm" @click="applyFilters">جستجو</VButton>
      </div>

      <div v-if="drafts.data.length === 0" class="text-center py-12">
        <p class="text-ink-muted">هنوز پیش‌نویسی تولید نشده.</p>
        <Link href="/app/ai-drafts/article/create" class="text-brand-700 text-sm mt-2 inline-block">تولید اولین مقاله</Link>
      </div>

      <div v-else class="space-y-3">
        <div v-for="draft in drafts.data" :key="draft.id" class="border-line flex items-center justify-between gap-4 rounded-xl border p-4 hover:bg-surface-muted transition-colors">
          <div class="min-w-0 flex-1">
            <Link :href="`/app/ai-drafts/${draft.id}/edit`" class="text-ink-strong font-semibold text-sm hover:text-brand-700">{{ draft.title }}</Link>
            <div class="flex items-center gap-3 mt-1 text-xs text-ink-muted">
              <span>{{ draft.site?.name }}</span>
              <span>{{ draft.model_used }}</span>
              <span>{{ new Date(draft.created_at).toLocaleDateString('fa-IR') }}</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <VBadge :tone="statusTones[draft.status] || 'neutral'">{{ statusLabels[draft.status] || draft.status }}</VBadge>
            <VBadge :tone="draft.quality_score >= 70 ? 'success' : draft.quality_score >= 40 ? 'warning' : 'danger'">{{ draft.quality_score }}/100</VBadge>
            <Link :href="`/app/ai-drafts/${draft.id}/edit`">
              <VButton variant="secondary" size="sm">ویرایش</VButton>
            </Link>
          </div>
        </div>
      </div>

      <div v-if="drafts.last_page > 1" class="mt-4 flex justify-center gap-2">
        <Link v-for="page in drafts.last_page" :key="page" :href="`/app/ai-drafts?page=${page}&status=${statusFilter}&search=${search}`" class="px-3 py-1 rounded-lg text-sm" :class="page === drafts.current_page ? 'bg-brand-600 text-white' : 'border border-line'">
          {{ page }}
        </Link>
      </div>
    </VCard>
  </AppLayout>
</template>
