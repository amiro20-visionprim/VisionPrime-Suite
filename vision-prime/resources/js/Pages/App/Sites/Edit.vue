<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import SiteForm from '@/app/components/SiteForm.vue'
interface Site {
  id: number
  name: string
  canonicalUrl: string
  projectId: number
  locale: string
  timezone: string
  businessImportance: number
}
interface Project {
  id: number
  name: string
  clientName: string
}
defineProps<{ site: Site; projects: Project[] }>()
</script>
<template>
  <Head title="ویرایش سایت" /><AppLayout><SiteForm :site="site" :projects="projects" /></AppLayout>
</template>
