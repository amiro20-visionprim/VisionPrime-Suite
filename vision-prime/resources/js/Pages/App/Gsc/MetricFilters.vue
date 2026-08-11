<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import VButton from '@/shared/ui/VButton.vue'

const props = defineProps<{
  baseUrl: string
  sites: { id: number; name: string }[]
  filters: { site_id: string | null; date_from: string | null; date_to: string | null }
}>()

const siteId = ref(props.filters.site_id ?? '')
const dateFrom = ref(props.filters.date_from ?? '')
const dateTo = ref(props.filters.date_to ?? '')

watch(
  () => props.filters,
  (filters) => {
    siteId.value = filters.site_id ?? ''
    dateFrom.value = filters.date_from ?? ''
    dateTo.value = filters.date_to ?? ''
  },
)

function apply(): void {
  router.get(
    props.baseUrl,
    {
      ...(siteId.value ? { site_id: siteId.value } : {}),
      ...(dateFrom.value ? { date_from: dateFrom.value } : {}),
      ...(dateTo.value ? { date_to: dateTo.value } : {}),
    },
    { preserveState: true, replace: true },
  )
}

function reset(): void {
  siteId.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  router.get(props.baseUrl, {}, { preserveState: true, replace: true })
}
</script>
<template>
  <div class="flex flex-wrap items-end gap-3">
    <label class="text-ink-strong block text-sm font-semibold">
      سایت
      <select
        v-model="siteId"
        class="border-line rounded-ui mt-1 min-h-10 w-48 border bg-surface px-3 text-sm"
      >
        <option value="">همهٔ سایت‌ها</option>
        <option v-for="site in sites" :key="site.id" :value="String(site.id)">
          {{ site.name }}
        </option>
      </select>
    </label>
    <label class="text-ink-strong block text-sm font-semibold">
      از تاریخ
      <input
        v-model="dateFrom"
        type="date"
        class="border-line rounded-ui mt-1 min-h-10 w-40 border bg-surface px-3 text-sm"
      />
    </label>
    <label class="text-ink-strong block text-sm font-semibold">
      تا تاریخ
      <input
        v-model="dateTo"
        type="date"
        class="border-line rounded-ui mt-1 min-h-10 w-40 border bg-surface px-3 text-sm"
      />
    </label>
    <VButton size="sm" @click="apply">اعمال فیلتر</VButton>
    <VButton size="sm" variant="secondary" @click="reset">حذف فیلترها</VButton>
  </div>
</template>
