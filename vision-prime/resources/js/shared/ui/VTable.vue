<script setup lang="ts">
import VSkeleton from './VSkeleton.vue'

export interface TableColumn {
  key: string
  label: string
  align?: 'start' | 'center' | 'end'
  technical?: boolean
}

export type TableRow = Record<string, unknown>

withDefaults(
  defineProps<{
    columns: TableColumn[]
    rows: TableRow[]
    rowKey: string
    loading?: boolean
    emptyTitle?: string
    emptyDescription?: string
    mobileMode?: 'scroll' | 'cards'
  }>(),
  {
    loading: false,
    emptyTitle: 'داده‌ای برای نمایش وجود ندارد',
    emptyDescription: '',
    mobileMode: 'scroll',
  },
)

function valueFor(row: TableRow, key: string): string {
  const value = row[key]
  return value === null || value === undefined ? '—' : String(value)
}

function alignClass(column: TableColumn): string {
  return column.align === 'end'
    ? 'text-end'
    : column.align === 'center'
      ? 'text-center'
      : 'text-start'
}
</script>

<template>
  <div>
    <div v-if="mobileMode === 'cards'" class="space-y-3 md:hidden">
      <article
        v-for="row in rows"
        :key="String(row[rowKey])"
        class="rounded-card border-line bg-surface shadow-card border p-4"
      >
        <dl class="space-y-3">
          <div
            v-for="column in columns"
            :key="column.key"
            class="flex items-start justify-between gap-4"
          >
            <dt class="text-ink-muted shrink-0 text-sm">{{ column.label }}</dt>
            <dd
              :class="[
                'text-ink-strong min-w-0 text-sm break-words',
                column.technical ? 'font-latin' : '',
                alignClass(column),
              ]"
              :dir="column.technical ? 'ltr' : undefined"
            >
              <slot :name="`cell-${column.key}`" :row="row" :value="valueFor(row, column.key)">{{
                valueFor(row, column.key)
              }}</slot>
            </dd>
          </div>
        </dl>
      </article>
    </div>

    <div :class="mobileMode === 'cards' ? 'hidden md:block' : 'overflow-x-auto'">
      <table class="w-full border-separate border-spacing-0 text-sm">
        <thead>
          <tr class="bg-surface-muted text-ink-muted">
            <th
              v-for="column in columns"
              :key="column.key"
              :class="[
                'border-line first:rounded-s-ui last:rounded-e-ui border-y px-4 py-3 font-medium first:border-s last:border-e',
                alignClass(column),
              ]"
              scope="col"
            >
              {{ column.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="index in 4" :key="index">
              <td
                v-for="column in columns"
                :key="column.key"
                class="border-line border-b px-4 py-4"
              >
                <VSkeleton height="0.875rem" />
              </td>
            </tr>
          </template>
          <template v-else-if="rows.length">
            <tr
              v-for="row in rows"
              :key="String(row[rowKey])"
              class="transition-ui hover:bg-brand-50/50"
            >
              <td
                v-for="column in columns"
                :key="column.key"
                :class="[
                  'border-line text-ink border-b px-4 py-4',
                  alignClass(column),
                  column.technical ? 'font-latin' : '',
                ]"
                :dir="column.technical ? 'ltr' : undefined"
              >
                <slot :name="`cell-${column.key}`" :row="row" :value="valueFor(row, column.key)">{{
                  valueFor(row, column.key)
                }}</slot>
              </td>
            </tr>
          </template>
          <tr v-else>
            <td :colspan="columns.length" class="border-line border-b px-4 py-10 text-center">
              <p class="text-ink-strong font-medium">{{ emptyTitle }}</p>
              <p v-if="emptyDescription" class="text-ink-muted mt-1">{{ emptyDescription }}</p>
              <slot name="empty-action" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
