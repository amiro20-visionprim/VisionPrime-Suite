<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import SiteForm from '@/app/components/SiteForm.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
defineProps<{ projects: { id: number; name: string; clientName: string }[] }>()
</script>
<template>
  <Head title="افزودن سایت" /><AppLayout
    ><VPageHeader
      title="افزودن اولین سایت"
      description="پس از ثبت، سایت برای اتصال سرچ کنسول و وردپرس آماده می‌شود." /><VCard
      class="mt-8"
      ><SiteForm :projects="projects" /></VCard
  ></AppLayout>
</template>
