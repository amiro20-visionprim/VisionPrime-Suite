<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VIcon from '@/shared/ui/VIcon.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'

defineProps<{
  sites: { data: { id: number; name: string; canonicalUrl: string; projectName: string; status?: string }[] }
}>()
</script>
<template>
  <Head title="سایت‌ها" />
  <AppLayout>
    <VPageHeader title="سایت‌ها" description="سایت‌های عملیاتی و آماده اتصال به منابع داده.">
      <template #actions>
        <VButton href="/app/sites/create">افزودن سایت</VButton>
      </template>
    </VPageHeader>

    <div v-if="sites.data.length" class="mt-8 space-y-3">
      <Link
        v-for="site in sites.data"
        :key="site.id"
        :href="`/app/sites/${site.id}`"
        class="transition-ui rounded-card border-line bg-surface shadow-card block border p-5 hover:-translate-y-0.5 hover:shadow-lg"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="bg-brand-50 text-brand-700 flex size-9 shrink-0 items-center justify-center rounded-lg">
                <VIcon name="activity" size="sm" />
              </span>
              <div>
                <p class="text-ink-strong font-bold">{{ site.name }}</p>
                <p class="font-latin text-ink-muted mt-0.5 text-xs" dir="ltr">{{ site.canonicalUrl }}</p>
              </div>
            </div>
            <p class="text-ink-muted mt-2 text-sm">{{ site.projectName }}</p>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <VBadge :tone="site.status === 'active' ? 'success' : 'neutral'">
              {{ site.status === 'active' ? 'فعال' : 'غیرفعال' }}
            </VBadge>
            <span class="text-ink-muted">←</span>
          </div>
        </div>
      </Link>
    </div>

    <VEmptyState
      v-else
      class="mt-8"
      title="هنوز سایتی ثبت نشده است"
      description="با افزودن اولین سایت، مسیر اتصال سرچ کنسول و وردپرس را شروع کنید."
      action-label="افزودن سایت"
      @action="$inertia.visit('/app/sites/create')"
    />
  </AppLayout>
</template>
