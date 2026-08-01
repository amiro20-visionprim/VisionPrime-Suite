<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VConfirmDialog from '@/shared/ui/VConfirmDialog.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { AppPageProps } from '@/types/app'
const props = defineProps<{
  project: {
    id: number
    name: string
    objective?: string | null
    clientName: string
    sitesCount: number
  }
}>()
const page = usePage<AppPageProps & { flash?: { status?: string } }>()
const open = ref(false)
function archive() {
  router.delete(`/app/projects/${props.project.id}`)
}
</script>
<template>
  <Head :title="project.name" /><AppLayout
    ><VPageHeader
      :title="project.name"
      :description="project.objective || 'هدف پروژه هنوز ثبت نشده است.'"
      :breadcrumbs="[{ label: 'پروژه‌ها', href: '/app/projects' }, { label: project.name }]"
      ><template #actions
        ><VButton :href="`/app/projects/${project.id}/edit`" variant="secondary">ویرایش</VButton
        ><VButton variant="danger" @click="open = true">بایگانی</VButton></template
      ></VPageHeader
    ><VAlert v-if="page.props.flash?.status" class="mt-6" tone="success">{{
      page.props.flash.status
    }}</VAlert>
    <section class="mt-8 grid gap-5 md:grid-cols-2">
      <VCard title="مشتری"
        ><p class="text-ink-strong font-semibold">{{ project.clientName }}</p></VCard
      ><VCard title="سایت‌ها"
        ><p class="text-ink-strong text-2xl font-bold">{{ project.sitesCount }}</p></VCard
      >
    </section>
    <VCard class="mt-8" title="سایت‌های پروژه"
      ><VEmptyState
        title="هنوز سایتی به این پروژه افزوده نشده است"
        description="در مرحله بعدی می‌توانید اولین سایت وردپرسی پروژه را اضافه کنید." /></VCard
    ><VConfirmDialog
      v-model="open"
      title="بایگانی پروژه"
      :description="`پروژه «${project.name}» از فهرست فعال خارج می‌شود.`"
      confirm-label="بایگانی"
      tone="danger"
      @confirm="archive"
  /></AppLayout>
</template>
