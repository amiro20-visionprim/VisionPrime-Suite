import type { Directive } from 'vue'

interface StaggerOptions {
  /** تأخیر بین هر فرزند (ms) */
  step?: number
  /** تأخیر شروع (ms) */
  delay?: number
  once?: boolean
}

const prefersReducedMotion =
  typeof window !== 'undefined' &&
  window.matchMedia('(prefers-reduced-motion: reduce)').matches

/**
 * Staggered entrance. Applies `vp-stagger-item` to direct children so each one
 * fades/slides in after a small delay, creating a cascading reveal.
 *
 * Usage: `v-stagger` or `v-stagger="{ step: 80 }"`.
 */
export const vStagger: Directive<HTMLElement, StaggerOptions> = {
  mounted(el, binding) {
    if (prefersReducedMotion) {
      return
    }

    const options = binding.value ?? {}
    const step = options.step ?? 70
    const delay = options.delay ?? 0

    const children = Array.from(el.children) as HTMLElement[]
    children.forEach((child, i) => {
      child.classList.add('vp-stagger-item')
      child.style.setProperty('--vp-stagger-delay', `${delay + i * step}ms`)
    })

    el.classList.add('vp-stagger')

    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0]
        if (!entry || !entry.isIntersecting) {
          return
        }
        el.classList.add('vp-staggered')
        observer.disconnect()
      },
      { threshold: 0.08, rootMargin: '0px 0px -40px 0px' },
    )
    observer.observe(el)
  },
}
