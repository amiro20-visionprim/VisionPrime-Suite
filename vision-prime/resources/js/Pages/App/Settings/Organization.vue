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
import VSelect from '@/shared/ui/VSelect.vue'
import VTable, { type TableColumn, type TableRow } from '@/shared/ui/VTable.vue'

interface MemberRow extends TableRow {
  id: number
  name: string | null
  email: string | null
  roleId: number
  roleName: string | null
  roleKey: string | null
  status: string
  isSelf: boolean
  joinedAt: string | null
}

interface RoleOption {
  id: number
  key: string
  name: string
  description: string | null
}

const props = defineProps<{
  organization: {
    id: number
    publicId: string | null
    name: string
    status: string
    createdAt: string | null
  }
  members: MemberRow[]
  roles: RoleOption[]
  canManage: boolean
}>()

const page = usePage<{ flash?: { status?: string } }>()
const memberToRemove = ref<MemberRow | null>(null)
const removing = ref(false)

const roleOptions = props.roles.map((role) => ({ value: String(role.id), label: role.name }))

const addForm = useForm({
  email: '',
  role_id: '',
})

function changeRole(row: TableRow, event: Event): void {
  const roleId = (event.target as HTMLSelectElement).value
  if (!roleId) return
  router.put(`/app/settings/organization/members/${row.id as number}`, { role_id: roleId })
}

function askRemove(row: TableRow): void {
  memberToRemove.value = row as MemberRow
}

function removeMember(): void {
  if (!memberToRemove.value) return
  removing.value = true
  router.delete(`/app/settings/organization/members/${memberToRemove.value.id}`, {
    onFinish: () => {
      removing.value = false
      memberToRemove.value = null
    },
  })
}

const columns = computed<TableColumn[]>(() => [
  { key: 'name', label: 'عضو' },
  { key: 'role', label: 'نقش', align: 'center' },
  { key: 'status', label: 'وضعیت' },
  { key: 'joinedAt', label: 'عضویت از', align: 'end' },
  ...(props.canManage
    ? [{ key: 'actions', label: 'عملیات', align: 'end' as const }]
    : []),
])
</script>

<template>
  <Head title="سازمان و اعضا" />
  <AppLayout>
    <VPageHeader
      title="سازمان و اعضا"
      description="تنظیمات سازمان، اعضای تیم و سطح دسترسی هر یک را مدیریت کنید."
    />
    <VAlert v-if="page.props.flash?.status" class="mt-5" tone="success">{{
      page.props.flash.status
    }}</VAlert>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
      <VCard class="lg:col-span-2" title="اعضای تیم" description="نقش هر عضو، سطح دسترسی او را در سازمان مشخص می‌کند.">
        <div v-if="members.length">
          <VTable :columns="columns" :rows="members" row-key="id" mobile-mode="cards">
            <template #cell-name="{ row }">
              <div>
                <p class="text-ink-strong font-semibold">
                  {{ row.name ?? '—' }}
                  <span v-if="row.isSelf" class="text-ink-muted text-xs font-normal"> (خودتان)</span>
                </p>
                <p class="text-ink-muted text-sm" dir="ltr">{{ row.email ?? '—' }}</p>
              </div>
            </template>
            <template #cell-role="{ row }">
              <select
                v-if="canManage && !row.isSelf"
                class="transition-ui rounded-ui border-line hover:border-line-strong bg-surface text-ink-strong min-h-9 cursor-pointer rounded-lg border px-2 text-sm focus:outline-none"
                :value="String(row.roleId)"
                :aria-label="`تغییر نقش ${row.name ?? row.email}`"
                @change="changeRole(row, $event)"
              >
                <option v-for="role in roles" :key="role.id" :value="String(role.id)">
                  {{ role.name }}
                </option>
              </select>
              <VBadge v-else>{{ row.roleName ?? row.roleKey ?? '—' }}</VBadge>
            </template>
            <template #cell-status="{ value }">
              <VBadge :tone="value === 'active' ? 'success' : 'neutral'">{{
                value === 'active' ? 'فعال' : value
              }}</VBadge>
            </template>
            <template #cell-joinedAt="{ value }">
              <span v-if="value" class="text-ink-muted text-sm">{{
                formatLocalizedDate(value, 'fa')
              }}</span>
              <span v-else>—</span>
            </template>
            <template #cell-actions="{ row }">
              <VButton
                v-if="canManage && !row.isSelf"
                variant="ghost"
                size="sm"
                @click="askRemove(row)"
                >حذف</VButton
              >
              <span v-else>—</span>
            </template>
          </VTable>
        </div>
        <VEmptyState
          v-else
          title="هنوز عضوی ثبت نشده است"
          description="برای همکاری اعضای تیم، آن‌ها را با ایمیل خودشان اضافه کنید."
        />
      </VCard>

      <div class="space-y-6">
        <VCard title="سازمان">
          <dl class="space-y-4 text-sm">
            <div class="flex items-center justify-between gap-4">
              <dt class="text-ink-muted">نام</dt>
              <dd class="text-ink-strong font-semibold">{{ organization.name }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-ink-muted">شناسه</dt>
              <dd class="text-ink-strong font-semibold" dir="ltr">{{ organization.publicId }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-ink-muted">وضعیت</dt>
              <dd>
                <VBadge :tone="organization.status === 'active' ? 'success' : 'neutral'">{{
                  organization.status === 'active' ? 'فعال' : organization.status
                }}</VBadge>
              </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-ink-muted">تأسیس</dt>
              <dd v-if="organization.createdAt" class="text-ink-strong">
                {{ formatLocalizedDate(organization.createdAt, 'fa') }}
              </dd>
              <dd v-else>—</dd>
            </div>
          </dl>
        </VCard>

        <VCard v-if="canManage" title="افزودن عضو" description="کاربر باید قبلاً در سامانه ثبت‌نام کرده باشد.">
          <form class="space-y-4" @submit.prevent="addForm.post('/app/settings/organization/members')">
            <VInput
              v-model="addForm.email"
              label="ایمیل کاربر"
              type="email"
              dir="ltr"
              required
              placeholder="member@example.ir"
              :error="addForm.errors.email"
            />
            <VSelect
              v-model="addForm.role_id"
              label="نقش"
              required
              :options="roleOptions"
              :error="addForm.errors.role_id"
            />
            <VButton type="submit" :loading="addForm.processing" class="w-full"
              >افزودن عضو</VButton
            >
          </form>
        </VCard>
      </div>
    </div>

    <VConfirmDialog
      :model-value="memberToRemove !== null"
      title="حذف عضو از سازمان"
      :description="memberToRemove ? `${memberToRemove.name ?? memberToRemove.email} از این سازمان حذف شود؟ دسترسی او به تمام بخش‌ها قطع خواهد شد.` : ''"
      confirm-label="حذف عضو"
      tone="danger"
      :loading="removing"
      @update:model-value="(open) => {
        if (!open) memberToRemove = null
      }"
      @confirm="removeMember"
    />
  </AppLayout>
</template>
