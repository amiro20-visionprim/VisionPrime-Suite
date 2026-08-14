import type { Directive, DirectiveBinding } from 'vue'

interface RevealOptions {
  delay?: number
  once?: boolean
}

const prefersReducedMotion =
  typeof window !== 'undefined' &&
  window.matchMedia('(prefers-reduced-motion: reduce)').matches

/**
 * Scroll-reveal directive. Adds `vp-reveal` on mount and flips to `vp-revealed`
 * when the element enters the viewport (IntersectionObserver). Honors
 * `prefers-reduced-motion` by revealing immediately.
 *
 * Usage: `v-reveal` or `v-reveal="{ delay: 120 }"`.
 */
export const vReveal: Directive<HTMLElement, RevealOptions> = {
  mounted(el: HTMLElement, binding: DirectiveBinding<RevealOptions>) {
    if (prefersReducedMotion) {
      return
    }

    const options = binding.value ?? {}
    if (options.delay) {
      el.style.setProperty('--vp-reveal-delay', `${options.delay}ms`)
    }

    el.classList.add('vp-reveal')

    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0]
        if (!entry || !entry.isIntersecting) {
          return
        }
        el.classList.add('vp-revealed')
        observer.disconnect()
      },
      { threshold: 0.12, rootMargin: '0px 0px -48px 0px' },
    )

    observer.observe(el)
  },
}
