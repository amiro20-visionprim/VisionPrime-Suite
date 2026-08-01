<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatLocalizedDate } from '@/lib/locale'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VConfirmDialog from '@/shared/ui/VConfirmDialog.vue'
import VEmptyState from '@/shared/ui/VEmptyState.vue'
import VInput from '@/shared/ui/VInput.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import VSelect, { type SelectOption } from '@/shared/ui/VSelect.vue'
import type { AppPageProps } from '@/types/app'

interface ClientData {
  id: number
  name: string
  status: string
  contactName: string | null
  contactEmail: string | null
  contactPhone: string | null
  projectsCount: number
  createdAt: string | null
}

interface Assignment {
  id: number
  userName: string
  userEmail: string
  portalRole: 'viewer' | 'approver'
}

const props = defineProps<{ client: ClientData; assignments: Assignment[]; canManage: boolean }>()
const page = usePage<AppPageProps & { flash?: { status?: string } }>()
const archiveDialogOpen = ref(false)
const assignmentForm = useForm({ email: '', portal_role: 'viewer' })
const roleOptions: SelectOption[] = [
  { label: 'مشاهده‌گر مشتری', value: 'viewer' },
  { label: 'تأییدکننده مشتری', value: 'approver' },
]
const contactRows = computed(() => [
  { label: 'نام شخص تماس', value: props.client.contactName },
  { label: 'ایمیل تماس', value: props.client.contactEmail, technical: true },
  { label: 'شماره تماس', value: props.client.contactPhone, technical: true },
])

function assignUser(): void {
  assignmentForm.post(`/app/clients/${props.client.id}/assignments`, {
    preserveScroll: true,
    onSuccess: () => assignmentForm.reset(),
  })
}

function removeAssignment(assignment: Assignment): void {
  router.delete(`/app/clients/${props.client.id}/assignments/${assignment.id}`, {
    preserveScroll: true,
  })
}

function archiveClient(): void {
  router.delete(`/app/clients/${props.client.id}`)
}
</script>

<template>
  <Head :title="client.name" />
  <AppLayout
    ><VPageHeader
      :title="client.name"
      description="نمای کلی مشتری، اطلاعات تماس، پروژه‌ها و دسترسی پرتال."
      :breadcrumbs="[{ label: 'مشتریان', href: '/app/clients' }, { label: client.name }]"
      :status="{
        label: client.status === 'active' ? 'فعال' : client.status,
        tone: client.status === 'active' ? 'success' : 'neutral',
      }"
      ><template #actions
        ><VButton v-if="canManage" :href="`/app/clients/${client.id}/edit`" variant="secondary"
          >ویرایش</VButton
        ><VButton v-if="canManage" variant="danger" @click="archiveDialogOpen = true"
          >بایگانی مشتری</VButton
        ></template
      ></VPageHeader
    >
    <VAlert v-if="page.props.flash?.status" class="mt-6" tone="success">{{
      page.props.flash.status
    }}</VAlert>
    <section class="mt-8 grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
      <VCard title="اطلاعات مشتری"
        ><dl class="divide-line divide-y">
          <div
            v-for="row in contactRows"
            :key="row.label"
            class="flex items-start justify-between gap-4 py-3"
          >
            <dt class="text-ink-muted text-sm">{{ row.label }}</dt>
            <dd
              :class="['text-ink-strong text-sm font-medium', row.technical ? 'font-latin' : '']"
              :dir="row.technical ? 'ltr' : undefined"
            >
              {{ row.value || '—' }}
            </dd>
          </div>
          <div class="flex items-start justify-between gap-4 py-3">
            <dt class="text-ink-muted text-sm">پروژه‌های فعال</dt>
            <dd class="text-ink-strong text-sm font-bold">{{ client.projectsCount }}</dd>
          </div>
          <div class="flex items-start justify-between gap-4 py-3">
            <dt class="text-ink-muted text-sm">تاریخ ایجاد</dt>
            <dd class="text-ink-strong text-sm">
              {{ client.createdAt ? formatLocalizedDate(client.createdAt, 'fa') : '—' }}
            </dd>
          </div>
        </dl></VCard
      >
      <VCard
        title="دسترسی پرتال مشتری"
        description="فقط اعضای فعال سازمان با نقش Client Viewer یا Client Approver قابل تخصیص هستند."
        ><form
          v-if="canManage"
          class="grid gap-4 sm:grid-cols-[1fr_12rem_auto]"
          @submit.prevent="assignUser"
        >
          <VInput
            v-model="assignmentForm.email"
            label="ایمیل عضو سازمان"
            type="email"
            dir="ltr"
            placeholder="client@example.ir"
            :error="assignmentForm.errors.email"
          /><VSelect
            v-model="assignmentForm.portal_role"
            label="سطح دسترسی"
            :options="roleOptions"
            :error="assignmentForm.errors.portal_role"
          />
          <div class="flex items-end">
            <VButton type="submit" :loading="assignmentForm.processing">افزودن</VButton>
          </div>
        </form>
        <div v-if="assignments.length" class="divide-line mt-6 divide-y">
          <div
            v-for="assignment in assignments"
            :key="assignment.id"
            class="flex items-center justify-between gap-4 py-3"
          >
            <div class="min-w-0">
              <p class="text-ink-strong truncate text-sm font-semibold">
                {{ assignment.userName }}
              </p>
              <p class="font-latin text-ink-muted mt-1 truncate text-xs" dir="ltr">
                {{ assignment.userEmail }}
              </p>
            </div>
            <div class="flex items-center gap-3">
              <VBadge :tone="assignment.portalRole === 'approver' ? 'warning' : 'info'">{{
                assignment.portalRole === 'approver' ? 'تأییدکننده' : 'مشاهده‌گر'
              }}</VBadge
              ><button
                v-if="canManage"
                type="button"
                class="text-danger-600 hover:text-danger-700 text-sm font-semibold"
                @click="removeAssignment(assignment)"
              >
                حذف
              </button>
            </div>
          </div>
        </div>
        <VEmptyState
          v-else
          class="mt-6"
          title="کاربری به پرتال تخصیص داده نشده است"
          description="پس از تخصیص، کاربر فقط داده‌های همین مشتری را در Client Portal مشاهده می‌کند."
      /></VCard>
    </section>
    <section class="mt-8">
      <VCard
        title="پروژه‌ها"
        description="پروژه‌های این مشتری در مرحله بعدی Workspace از همین صفحه قابل مدیریت خواهند بود."
        ><VEmptyState
          title="هنوز پروژه‌ای ثبت نشده است"
          description="برای شروع عملیات SEO، یک پروژه برای این مشتری ایجاد کنید."
      /></VCard>
    </section>
    <VConfirmDialog
      v-model="archiveDialogOpen"
      title="بایگانی مشتری"
      :description="`مشتری «${client.name}» بایگانی می‌شود و از فهرست فعال خارج خواهد شد. پروژه‌ها و سایت‌های آن حذف فیزیکی نمی‌شوند.`"
      confirm-label="بایگانی مشتری"
      tone="danger"
      @confirm="archiveClient"
    />
  </AppLayout>
</template>
