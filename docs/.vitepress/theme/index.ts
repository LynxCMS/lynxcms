import DefaultTheme from 'vitepress/theme'
import CopyOrDownloadAsMarkdownButtons from 'vitepress-plugin-llms/vitepress-components/CopyOrDownloadAsMarkdownButtons.vue'
import { useRoute } from 'vitepress'
import mediumZoom from 'medium-zoom'
import { h, nextTick, onMounted, watch } from 'vue'
import type { Theme } from 'vitepress'
import HomeCode from './HomeCode.vue'
import HomeHighlights from './HomeHighlights.vue'
import HomeTour from './HomeTour.vue'
import './custom.css'

/** Images in a page's text open larger on click; logos and icons elsewhere stay as they are. */
const ZOOMABLE = '.vp-doc img:not(a img)'

export default {
    extends: DefaultTheme,
    // The home page shows code in its hero, the admin tour between the hero and its cards, and what
    // Lynx adds below them.
    Layout: () => h(DefaultTheme.Layout, null, {
        'home-hero-image': () => h(HomeCode),
        'home-features-before': () => h(HomeTour),
        'home-features-after': () => h(HomeHighlights),
    }),
    enhanceApp({ app }) {
        app.component('CopyOrDownloadAsMarkdownButtons', CopyOrDownloadAsMarkdownButtons)
    },
    setup() {
        const route = useRoute()
        const zoom = () => mediumZoom(ZOOMABLE, { background: 'var(--vp-c-bg)', margin: 24 })

        onMounted(zoom)
        // Each page swaps the content without reloading, so attach to the new page's images.
        watch(() => route.path, () => nextTick(zoom))
    },
} satisfies Theme
