<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface ClientOption {
  id: number
  publicId: string
  name: string
}

interface CurrentClient {
  publicId: string
  name: string
}

const page = usePage<{ currentClient: CurrentClient | null; availableClients: ClientOption[] }>()
const options = computed(() =>
  page.props.availableClients.map((client) => ({ label: client.name, value: String(client.id) })),
)
const selectedId = computed(() =>
  String(
    page.props.availableClients.find(
      (client) => client.publicId === page.props.currentClient?.publicId,
    )?.id ?? '',
  ),
)

function selectClient(clientId: string): void {
  if (!clientId || clientId === selectedId.value) return
  router.put(`/client/current-client/${clientId}`, {}, { preserveScroll: true })
}
</script>

<template>
  <div v-if="options.length" class="rounded-card border-line bg-surface-muted border p-3">
    <p class="text-ink-muted px-1 text-xs font-medium">مشتری فعال</p>
    <select
      :value="selectedId"
      class="text-ink-strong mt-1 w-full appearance-none bg-transparent px-1 py-1 text-sm font-bold focus:outline-none"
      aria-label="انتخاب مشتری"
      @change="selectClient(($event.target as HTMLSelectElement).value)"
    >
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
  </div>
  <p v-else class="text-ink-muted px-3 text-xs leading-5">
    هنوز مشتری فعالی برای نمایش وجود ندارد.
  </p>
</template>
