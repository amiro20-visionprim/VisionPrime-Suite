<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'

interface ImpersonatingInfo {
  name: string
  email: string
}

const page = usePage<{ impersonating?: ImpersonatingInfo | null }>()

function stop(): void {
  router.post('/platform/impersonation/stop', {}, { preserveScroll: true })
}
</script>

<template>
  <div v-if="page.props.impersonating" class="relative z-50">
    <div class="bg-danger-600 flex items-center justify-between gap-3 px-4 py-2 text-sm font-semibold text-white">
      <p class="flex items-center gap-2">
        <span>🕵️</span>
        در حال مشاهده بهجای
        <span class="font-bold">{{ page.props.impersonating.name }}</span>
        <span class="font-normal opacity-80" dir="ltr">({{ page.props.impersonating.email }})</span>
      </p>
      <button
        type="button"
        class="rounded-lg bg-white/15 px-3 py-1 text-xs font-bold hover:bg-white/25"
        @click="stop"
      >
        خروج از حالت مشاهده
      </button>
    </div>
  </div>
</template>
