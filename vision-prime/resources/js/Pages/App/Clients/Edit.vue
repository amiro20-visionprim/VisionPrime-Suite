<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ClientForm from '@/app/components/ClientForm.vue'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'

interface ClientData {
  id: number
  name: string
  contactName: string | null
  contactEmail: string | null
  contactPhone: string | null
}

defineProps<{ client: ClientData }>()
</script>

<template>
  <Head :title="`ویرایش ${client.name}`" />
  <AppLayout
    ><VPageHeader
      :title="`ویرایش ${client.name}`"
      description="اطلاعات اصلی و تماس مشتری را به‌روزرسانی کنید."
      :breadcrumbs="[
        { label: 'مشتریان', href: '/app/clients' },
        { label: client.name, href: `/app/clients/${client.id}` },
        { label: 'ویرایش' },
      ]" /><VCard class="mt-8" title="اطلاعات مشتری"><ClientForm :client="client" /></VCard
  ></AppLayout>
</template>
