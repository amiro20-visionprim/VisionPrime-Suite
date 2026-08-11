<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import {
  commandStatusLabels,
  commandTypeLabels,
  labelOf,
  riskTierLabels,
} from '@/lib/labels'
import VBadge from '@/shared/ui/VBadge.vue'
import VButton from '@/shared/ui/VButton.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Command, Paginated } from '@/types/automation'
defineProps<{ commands: Paginated<Command> }>()

function decide(command: Command, decision: 'approved' | 'rejected'): void {
  router.post(`/app/commands/${command.id}/decision`, { decision }, { preserveScroll: true })
}
</script>
<template>
  <Head title="تغییرات اجرایی" /><AppLayout
    ><VPageHeader
      title="تغییرات اجرایی"
      description="دستورهای کنترل‌شده، وضعیت تأیید و چرخه اجرای آن‌ها."
    />
    <div class="mt-8 space-y-3">
      <div
        v-for="command in commands.data"
        :key="command.id"
        class="rounded-card border-line bg-surface border p-5"
      >
        <Link
          :href="`/app/commands/${command.id}`"
          class="block transition-colors hover:text-brand-700"
        >
          <div class="flex justify-between">
            <span>{{ labelOf(commandTypeLabels, command.type) }}</span
            ><VBadge
              :tone="
                command.status === 'executed'
                  ? 'success'
                  : command.status === 'failed'
                    ? 'danger'
                    : command.status === 'pending_approval'
                      ? 'warning'
                      : 'info'
              "
              >{{ labelOf(commandStatusLabels, command.status) }}</VBadge
            >
          </div>
          <p class="text-ink-muted mt-2 text-sm">
            {{ labelOf(riskTierLabels, command.risk_tier) }} · انقضا:
            {{ formatJalaliDate(command.expires_at) }}
          </p>
        </Link>
        <div
          v-if="command.status === 'pending_approval'"
          class="border-line mt-3 flex gap-2 border-t pt-3"
        >
          <VButton size="sm" @click="decide(command, 'approved')">تأیید</VButton>
          <VButton size="sm" variant="danger" @click="decide(command, 'rejected')">رد</VButton>
        </div>
      </div>
    </div></AppLayout
  >
</template>
