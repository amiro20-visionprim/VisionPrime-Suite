<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import VButton from '@/shared/ui/VButton.vue'
import VInput from '@/shared/ui/VInput.vue'
import VSelect, { type SelectOption } from '@/shared/ui/VSelect.vue'
import VTextarea from '@/shared/ui/VTextarea.vue'
interface Project {
  id?: number
  name: string
  objective?: string | null
  clientId: number
}
const props = defineProps<{ project?: Project; clients: { id: number; name: string }[] }>()
const form = useForm({
  client_id: String(props.project?.clientId ?? ''),
  name: props.project?.name ?? '',
  objective: props.project?.objective ?? '',
})
const options: SelectOption[] = props.clients.map((c) => ({ label: c.name, value: String(c.id) }))
function submit() {
  if (props.project?.id) form.put(`/app/projects/${props.project.id}`)
  else form.post('/app/projects')
}
</script>
<template>
  <form class="space-y-6" @submit.prevent="submit">
    <VSelect
      v-model="form.client_id"
      label="مشتری"
      :options="options"
      required
      :error="form.errors.client_id"
    /><VInput v-model="form.name" label="نام پروژه" required :error="form.errors.name" /><VTextarea
      v-model="form.objective"
      label="هدف پروژه"
      hint="مثلاً افزایش ورودی تجاری یا بهبود صفحات خدمات."
      :error="form.errors.objective"
    />
    <div class="flex gap-3">
      <VButton type="submit" :loading="form.processing">{{
        project ? 'ذخیره تغییرات' : 'ایجاد پروژه'
      }}</VButton
      ><VButton href="/app/projects" variant="secondary">انصراف</VButton>
    </div>
  </form>
</template>
