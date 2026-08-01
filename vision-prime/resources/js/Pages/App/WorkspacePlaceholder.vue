<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

defineProps<{
  title: string
  description: string
}>()
</script>

<template>
  <Head :title="title" />
  <AppLayout>
    <VPageHeader :title="title" :description="description" />
    <div class="mt-8">
      <VEmptyState
        :title="`${title} هنوز داده‌ای ندارند`"
        :description="'این بخش در فاز مربوط به خود با داده واقعی و جریان عملیاتی کامل می‌شود.'"
      />
    </div>
  </AppLayout>
</template>
