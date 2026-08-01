<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface OrganizationOption {
  id: number
  publicId: string
  name: string
  slug: string
}

interface CurrentOrganization {
  publicId: string
  name: string
  slug: string
}

const page = usePage<{
  currentOrganization: CurrentOrganization
  availableOrganizations: OrganizationOption[]
}>()
const options = computed(() =>
  page.props.availableOrganizations.map((organization) => ({
    label: organization.name,
    value: String(organization.id),
  })),
)
const currentId = computed(() =>
  String(
    page.props.availableOrganizations.find(
      (organization) => organization.publicId === page.props.currentOrganization.publicId,
    )?.id ?? '',
  ),
)

function updateOrganization(organizationId: string): void {
  if (!organizationId || organizationId === currentId.value) return
  router.put(`/app/current-organization/${organizationId}`, {}, { preserveScroll: true })
}
</script>

<template>
  <div class="rounded-card border-line bg-surface-muted border p-3">
    <p class="text-ink-muted px-1 text-xs font-medium">فضای کاری فعال</p>
    <select
      :value="currentId"
      class="text-ink-strong mt-1 w-full appearance-none bg-transparent px-1 py-1 text-sm font-bold focus:outline-none"
      aria-label="انتخاب فضای کاری"
      @change="updateOrganization(($event.target as HTMLSelectElement).value)"
    >
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
  </div>
</template>
