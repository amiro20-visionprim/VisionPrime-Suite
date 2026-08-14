import '../css/app.css'
import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, h, type DefineComponent } from 'vue'

import { vReveal } from '@/directives/reveal'
import { syncDocumentLocale } from '@/lib/locale'
import type { AppPageProps } from '@/types/app'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue', {
      eager: true,
    })
    const page = pages[`./Pages/${name}.vue`]

    if (!page) {
      throw new Error(`Inertia page not found: ${name}`)
    }

    return page
  },
  setup({ el, App, props, plugin }) {
    const pageProps = props.initialPage.props as unknown as AppPageProps
    syncDocumentLocale(pageProps.app.locale)

    createApp({ render: () => h(App, props) })
      .use(plugin)
      .directive('reveal', vReveal)
      .mount(el)
  },
})
