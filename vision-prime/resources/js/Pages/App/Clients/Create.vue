<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientForm from '@/app/components/ClientForm.vue'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
</script>

<template>
  <Head title="افزودن مشتری" />
  <AppLayout
    ><VPageHeader
      title="افزودن مشتری"
      description="ابتدا کسب‌وکار مشتری را ثبت کنید؛ سپس پروژه‌ها، سایت‌ها و اعضای پرتال او را اضافه خواهید کرد."
      :breadcrumbs="[
        { label: 'مشتریان', href: '/app/clients' },
        { label: 'افزودن مشتری' },
      ]" /><VCard class="mt-8" title="مشتری جدید"><ClientForm /></VCard
  ></AppLayout>
</template>
