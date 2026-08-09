<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VAlert from '@/shared/ui/VAlert.vue'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type {
  Command,
  CommandApproval,
  CommandExecutionLog,
  RollbackSnapshot,
} from '@/types/automation'

const props = defineProps<{
  command: Command
  approvals: CommandApproval[]
  logs: CommandExecutionLog[]
  snapshots: RollbackSnapshot[]
}>()

const page = usePage<{ flash?: { status?: string; error?: string } }>()
const executing = computed(() => props.command.status === 'dispatched')

function dispatch() {
  router.post(`/app/commands/${props.command.id}/dispatch`, undefined, {
    preserveScroll: true,
  })
}

const toneFor = (status: string) =>
  status === 'executed' || status === 'approved' || status === 'completed'
    ? 'success'
    : status === 'failed' || status === 'cancelled' || status === 'rejected'
      ? 'danger'
      : 'warning'
</script>
<template>
  <Head title="جزئیات تغییر اجرایی" />
  <AppLayout>
    <VPageHeader title="جزئیات تغییر اجرایی" :description="command.type">
      <template #actions>
        <VButton
          v-if="command.status === 'approved'"
          :loading="executing"
          @click="dispatch"
        >
          اجرای تغییر
        </VButton>
      </template>
    </VPageHeader>

    <VAlert v-if="page.props.flash?.status" class="mt-5" tone="success">
      {{ page.props.flash.status }}
    </VAlert>
    <VAlert v-if="page.props.flash?.error" class="mt-5" tone="danger">
      {{ page.props.flash.error }}
    </VAlert>

    <VCard class="mt-8" title="وضعیت">
      <div class="flex items-center gap-3">
        <VBadge :tone="toneFor(command.status)">{{ command.status }}</VBadge>
        <span class="text-ink-muted text-sm">ریسک: {{ command.risk_tier }}</span>
      </div>
      <p v-if="command.expires_at" class="text-ink-muted mt-3 text-sm">
        انقضا: {{ command.expires_at }}
      </p>
    </VCard>

    <VCard class="mt-6" title="تاریخچه تأییدها">
      <div v-if="approvals.length" class="space-y-2">
        <div v-for="approval in approvals" :key="approval.id" class="flex items-center gap-3">
          <VBadge :tone="approval.decision === 'approved' ? 'success' : 'danger'">
            {{ approval.decision }}
          </VBadge>
          <span v-if="approval.note" class="text-ink-muted">{{ approval.note }}</span>
        </div>
      </div>
      <p v-else class="text-ink-muted">هنوز تأییدی ثبت نشده است.</p>
    </VCard>

    <VCard class="mt-6" title="گزارش اجرا">
      <div v-if="logs.length" class="space-y-2">
        <div v-for="log in logs" :key="log.id" class="flex items-center gap-3">
          <VBadge :tone="toneFor(log.status)">{{ log.status }}</VBadge>
          <span class="text-ink-muted text-sm">{{ log.executed_at }}</span>
        </div>
      </div>
      <p v-else class="text-ink-muted">هنوز اجرایی انجام نشده است.</p>
    </VCard>

    <VCard class="mt-6" title="عکس‌های بازگشت">
      <div v-if="snapshots.length" class="space-y-2">
        <div v-for="snapshot in snapshots" :key="snapshot.id">
          {{ snapshot.target_ref }} — {{ snapshot.status }}
        </div>
      </div>
      <p v-else class="text-ink-muted">عکسی ثبت نشده است.</p>
    </VCard>
  </AppLayout>
</template>
