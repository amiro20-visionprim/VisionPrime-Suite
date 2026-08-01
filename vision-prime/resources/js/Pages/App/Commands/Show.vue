<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import VCard from '@/shared/ui/VCard.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type {
  Command,
  CommandApproval,
  CommandExecutionLog,
  RollbackSnapshot,
} from '@/types/automation'
defineProps<{
  command: Command
  approvals: CommandApproval[]
  logs: CommandExecutionLog[]
  snapshots: RollbackSnapshot[]
}>()
</script>
<template>
  <Head title="Command Detail" /><AppLayout
    ><VPageHeader title="جزئیات تغییر اجرایی" :description="command.type" /><VCard
      class="mt-8"
      title="وضعیت"
      ><p>{{ command.status }} · {{ command.risk_tier }}</p></VCard
    ><VCard class="mt-6" title="Approval History"
      ><div v-for="approval in approvals" :key="approval.id">
        {{ approval.decision }} — {{ approval.note }}
      </div></VCard
    ><VCard class="mt-6" title="Execution Logs"
      ><div v-for="log in logs" :key="log.id">{{ log.status }} — {{ log.executed_at }}</div></VCard
    ><VCard class="mt-6" title="Rollback Snapshots"
      ><div v-for="snapshot in snapshots" :key="snapshot.id">
        {{ snapshot.target_ref }} — {{ snapshot.status }}
      </div></VCard
    ></AppLayout
  >
</template>
