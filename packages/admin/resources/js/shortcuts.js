/**
 * Keyboard shortcuts across the admin, read from the page: "?" opens the list of shortcuts, "/"
 * the search, "[" and "]" the previous and next tab of a tab list marked data-shortcut-tabs, and
 * "g" then a letter follows the link marked data-shortcut="g {letter}" (the sidebar's screens).
 * Keys typed into a field, or held with Cmd, Ctrl or Alt, are left alone.
 */

/** How long "g" waits for its second key, in milliseconds. */
const SEQUENCE_TIMEOUT = 1000

const EDITABLE = 'input, textarea, select, [contenteditable=""], [contenteditable="true"]'

function isTyping(target) {
    return target instanceof Element && target.closest(EDITABLE) !== null
}

function stepTab(direction) {
    const tabs = [...document.querySelectorAll('[data-shortcut-tabs] [role="tab"]')]
    const current = tabs.findIndex((tab) => tab.getAttribute('aria-selected') === 'true')
    const next = tabs[current + direction]

    if (current === -1 || next === undefined) {
        return false
    }

    next.click()
    next.focus()

    return true
}

function focusSearch() {
    const search = document.querySelector('[data-shortcut-search]')
    search?.focus()
    search?.select?.()

    return search !== null
}

function openList() {
    window.dispatchEvent(new CustomEvent('open-dialog-lynx-shortcuts'))

    return true
}

/** The action for a single key, or null when the key has none. */
function actionFor(key) {
    return {
        '?': openList,
        '/': focusSearch,
        '[': () => stepTab(-1),
        ']': () => stepTab(1),
    }[key] ?? null
}

export function registerShortcuts() {
    let pending = null
    let timer = null

    const clearPending = () => {
        pending = null
        clearTimeout(timer)
    }

    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey || isTyping(event.target)) {
            return
        }

        if (pending !== null) {
            const link = document.querySelector(`[data-shortcut="${pending} ${CSS.escape(event.key)}"]`)
            clearPending()

            if (link !== null) {
                event.preventDefault()
                link.click()
            }

            return
        }

        if (event.key === 'g') {
            pending = 'g'
            timer = setTimeout(clearPending, SEQUENCE_TIMEOUT)

            return
        }

        const action = actionFor(event.key)

        if (action !== null && action()) {
            event.preventDefault()
        }
    })
}
