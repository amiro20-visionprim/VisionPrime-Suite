<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import ProjectForm from '@/app/components/ProjectForm.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
defineProps<{ clients: { id: number; name: string }[] }>()
</script>
<template>
  <Head title="افزودن پروژه" /><AppLayout
    ><VPageHeader
      title="افزودن پروژه"
      description="پروژه را به مشتری مرتبط کرده و هدف اصلی آن را مشخص کنید." /><VCard
      class="mt-8"
      title="پروژه جدید"
      ><ProjectForm :clients="clients" /></VCard
  ></AppLayout>
</template>
