<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import AppLayout from '@/app/layouts/AppLayout.vue'
import TrainingCatalogView from '@/shared/training/TrainingCatalogView.vue'
</script>

<template>
  <Head title="مرکز آموزش | Vision Prime SUITE" />

  <AppLayout>
    <TrainingCatalogView audience="agency" />
  </AppLayout>
</template>
