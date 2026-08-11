<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { labelOf, reviewStatusLabels, reviewSubjectLabels } from '@/lib/labels'
import VBadge from '@/shared/ui/VBadge.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Paginated, ReviewItem } from '@/types/review'
defineProps<{ items: Paginated<ReviewItem> }>()
</script>
<template>
  <Head title="بررسی و تأییدها" /><AppLayout
    ><VPageHeader
      title="بررسی و تأییدها"
      description="خروجی‌های AI و توصیه‌های نیازمند تصمیم انسانی."
    />
    <div class="mt-8 space-y-3">
      <div
        v-for="item in items.data"
        :key="item.id"
        class="rounded-card border-line bg-surface border p-5"
      >
        <div class="flex justify-between">
          <span>{{ labelOf(reviewSubjectLabels, item.subject_type) }}</span
          ><VBadge
            :tone="
              item.status === 'approved'
                ? 'success'
                : item.status === 'rejected'
                  ? 'danger'
                  : 'warning'
            "
            >{{ labelOf(reviewStatusLabels, item.status) }}</VBadge
          >
        </div>
      </div>
    </div></AppLayout
  >
</template>
