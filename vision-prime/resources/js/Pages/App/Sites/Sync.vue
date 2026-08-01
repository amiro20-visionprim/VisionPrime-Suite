<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
const p = defineProps<{
  site: { id: number; name: string }
  run: null | {
    status: string
    summary: { items?: number } | null
    error: { message?: string } | null
    startedAt: string | null
    finishedAt: string | null
    failedItems: { url: string | null; error: { message?: string } | null }[]
  }
}>()
function sync() {
  router.post(`/app/sites/${p.site.id}/sync`)
}
</script>
<template>
  <Head title="همگام‌سازی محتوا" /><AppLayout
    ><VPageHeader title="همگام‌سازی محتوا" :description="site.name" /><VCard
      class="mt-8"
      title="آخرین Sync"
      ><template #action><VButton @click="sync">شروع همگام‌سازی</VButton></template>
      <div v-if="run">
        <VBadge
          :tone="
            run.status === 'completed' ? 'success' : run.status === 'failed' ? 'danger' : 'warning'
          "
          >{{ run.status }}</VBadge
        >
        <p class="text-ink mt-4">آیتم‌های پردازش‌شده: {{ run.summary?.items ?? '—' }}</p>
        <p v-if="run.error?.message" class="text-danger-600 mt-3">{{ run.error.message }}</p>
        <div v-if="run.failedItems.length" class="mt-5">
          <p class="font-semibold">Itemهای ناموفق</p>
          <div
            v-for="(item, index) in run.failedItems"
            :key="item.url ?? index"
            class="rounded-ui bg-danger-50 mt-2 p-3"
          >
            <span class="font-latin" dir="ltr">{{ item.url }}</span>
          </div>
        </div>
      </div>
      <p v-else class="text-ink-muted">هنوز Syncی اجرا نشده است.</p></VCard
    ></AppLayout
  >
</template>
