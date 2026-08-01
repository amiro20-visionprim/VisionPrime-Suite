<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import ProjectForm from '@/app/components/ProjectForm.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
defineProps<{
  project: { id: number; name: string; objective?: string | null; clientId: number }
  clients: { id: number; name: string }[]
}>()
</script>
<template>
  <Head :title="`ویرایش ${project.name}`" /><AppLayout
    ><VPageHeader
      :title="`ویرایش ${project.name}`"
      description="اطلاعات پروژه را به‌روزرسانی کنید." /><VCard class="mt-8"
      ><ProjectForm :project="project" :clients="clients" /></VCard
  ></AppLayout>
</template>
