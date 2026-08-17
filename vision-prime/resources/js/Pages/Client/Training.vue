<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientPortalLayout from '@/client/layouts/ClientPortalLayout.vue'
import TrainingCatalogView from '@/shared/training/TrainingCatalogView.vue'
</script>

<template>
  <Head title="مرکز آموزش | پرتال رشد شما" />

  <ClientPortalLayout>
    <TrainingCatalogView audience="client" />
  </ClientPortalLayout>
</template>
