<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
interface Site {
  id: number
  name: string
  canonicalUrl: string
}
defineProps<{ site: Site }>()
</script>
<template>
  <Head :title="site.name" /><AppLayout
    ><VPageHeader :title="site.name" :description="site.canonicalUrl"
      ><template #actions
        ><VButton :href="`/app/sites/${site.id}/edit`" variant="secondary"
          >ویرایش</VButton
        ></template
      ></VPageHeader
    >
    <div class="mt-8 grid gap-5 md:grid-cols-2">
      <VCard title="آماده اتصال سرچ کنسول"
        ><VButton variant="secondary" disabled>به‌زودی در فاز GSC</VButton></VCard
      ><VCard title="آماده اتصال وردپرس"
        ><VButton variant="secondary" disabled>به‌زودی در فاز Connector</VButton></VCard
      >
    </div></AppLayout
  >
</template>
