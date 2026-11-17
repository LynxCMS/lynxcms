import DefaultTheme from 'vitepress/theme'
import { useRoute } from 'vitepress'
import mediumZoom from 'medium-zoom'
import { nextTick, onMounted, watch } from 'vue'
import type { Theme } from 'vitepress'
import './custom.css'

/** Images in a page's text open larger on click; logos and icons elsewhere stay as they are. */
const ZOOMABLE = '.vp-doc img:not(a img)'

export default {
    extends: DefaultTheme,
    setup() {
        const route = useRoute()
        const zoom = () => mediumZoom(ZOOMABLE, { background: 'var(--vp-c-bg)', margin: 24 })

        onMounted(zoom)
        // Each page swaps the content without reloading, so attach to the new page's images.
        watch(() => route.path, () => nextTick(zoom))
    },
} satisfies Theme
