<script setup lang="ts">
import { withUtm } from '@/lib/analytics'
import VButton from '@/shared/ui/VButton.vue'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    title: string
    description: string
    ctaLabel: string
    /** Landing segment slug; appended as utm_campaign so demo leads are attributable. */
    campaign: string
    ctaHref?: string
  }>(),
  {
    ctaHref: '/demo',
  },
)

const emit = defineEmits<{
  'cta-click': []
}>()

const resolvedHref = computed(() => withUtm(props.ctaHref, props.campaign))

function handleCta(): void {
  emit('cta-click')
}
</script>

<template>
  <section class="mx-auto max-w-7xl px-5 pb-16 sm:px-8 lg:px-10 lg:pb-24">
    <div
      v-reveal
      class="relative overflow-hidden rounded-3xl bg-brand-900 px-6 py-12 text-center text-white sm:px-12 sm:py-14"
    >
      <div aria-hidden="true" class="pointer-events-none absolute inset-0">
        <div class="absolute -top-24 left-1/4 size-72 rounded-full bg-violet-500/30 blur-3xl" />
        <div class="absolute -bottom-28 right-1/4 size-72 rounded-full bg-indigo-500/30 blur-3xl" />
      </div>
      <div class="relative">
        <h2 class="mx-auto max-w-2xl text-3xl leading-relaxed font-bold">
          {{ title }}
        </h2>
        <p class="text-brand-100 mx-auto mt-4 max-w-xl leading-8">
          {{ description }}
        </p>
        <div class="mt-8 flex justify-center">
          <VButton
            :href="resolvedHref"
            size="lg"
            variant="secondary"
            @click="handleCta"
          >
            {{ ctaLabel }}
          </VButton>
        </div>
      </div>
    </div>
  </section>
</template>
