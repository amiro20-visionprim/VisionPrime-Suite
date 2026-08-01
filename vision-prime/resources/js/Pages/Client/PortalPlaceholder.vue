<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

defineProps<{ title: string; description: string }>()
</script>

<template>
  <Head :title="title" />
  <ClientPortalLayout
    ><VPageHeader :title="title" :description="description" />
    <div class="mt-8">
      <VEmptyState
        :title="`${title} در حال آماده‌سازی است`"
        description="این بخش پس از آماده‌شدن داده‌ها، با زبان ساده و بدون جزئیات عملیاتی غیرضروری برای شما نمایش داده می‌شود."
      /></div
  ></ClientPortalLayout>
</template>
