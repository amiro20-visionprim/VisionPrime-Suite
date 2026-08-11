<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/app/layouts/AppLayout.vue'
import { formatJalaliDate } from '@/lib/locale'
import {
  commandStatusLabels,
  commandTypeLabels,
  labelOf,
  riskTierLabels,
} from '@/lib/labels'
import VBadge from '@/shared/ui/VBadge.vue'
import VPageHeader from '@/shared/ui/VPageHeader.vue'
import type { Command, Paginated } from '@/types/automation'
defineProps<{ commands: Paginated<Command> }>()
</script>
<template>
  <Head title="تغییرات اجرایی" /><AppLayout
    ><VPageHeader
      title="تغییرات اجرایی"
      description="دستورهای کنترل‌شده، وضعیت تأیید و چرخه اجرای آن‌ها."
    />
    <div class="mt-8 space-y-3">
      <Link
        v-for="command in commands.data"
        :key="command.id"
        :href="`/app/commands/${command.id}`"
        class="rounded-card border-line bg-surface block border p-5"
        >        <div class="flex justify-between">
          <span>{{ labelOf(commandTypeLabels, command.type) }}</span
          ><VBadge
            :tone="
              command.status === 'executed'
                ? 'success'
                : command.status === 'failed'
                  ? 'danger'
                  : 'warning'
            "
            >{{ labelOf(commandStatusLabels, command.status) }}</VBadge
          >
        </div>
        <p class="text-ink-muted mt-2 text-sm">
          {{ labelOf(riskTierLabels, command.risk_tier) }} · انقضا:
          {{ formatJalaliDate(command.expires_at) }}
        </p></Link
      >
    </div></AppLayout
  >
</template>
