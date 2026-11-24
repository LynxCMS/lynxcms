/**
 * A collapsible section (components/section): the page editor's template sections and the other
 * screens' sections. It starts as the template or screen says, then as the person last left it
 * (remembered in this browser). A section with a validation error always opens, so a collapsed
 * section never hides a problem.
 */
const STORAGE_PREFIX = 'lynx:section:'

export function lynxEditorSection(storageKey, startsCollapsed, hasError) {
    return {
        open: hasError || ! startsCollapsed,

        init() {
            if (hasError) {
                return
            }

            try {
                const remembered = localStorage.getItem(STORAGE_PREFIX + storageKey)

                if (remembered !== null) {
                    this.open = remembered === 'open'
                }
            } catch {
                // Storage can be blocked (private windows); the template's default applies.
            }
        },

        toggle() {
            this.open = ! this.open

            try {
                localStorage.setItem(STORAGE_PREFIX + storageKey, this.open ? 'open' : 'closed')
            } catch {
                // Not remembered when storage is blocked; the section still opens and closes.
            }
        },
    }
}
