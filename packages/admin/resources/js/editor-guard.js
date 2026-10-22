/**
 * Alpine component for the page editor's root: warns before leaving the page with unsaved edits
 * and saves on Cmd/Ctrl+S. Usage: x-data="lynxEditorGuard".
 *
 * Unsaved means the form's values ($wire.values, which includes edits not sent yet) differ from
 * the values it was loaded with ($wire.original). Empty and missing count as the same, and numbers
 * compare as text, as the server does when it works out what changed.
 */

/** Empty text and null are the same value to the editor; numbers arrive as text from inputs. */
function normalize(value) {
    if (value === null || value === undefined || value === '') {
        return null
    }

    return typeof value === 'number' ? String(value) : value
}

/** Deep comparison of two form states. */
export function sameValues(a, b) {
    a = normalize(a)
    b = normalize(b)

    if (a === null || b === null || typeof a !== 'object' || typeof b !== 'object') {
        return a === b
    }

    const keys = new Set([...Object.keys(a), ...Object.keys(b)])

    return [...keys].every((key) => sameValues(a[key], b[key]))
}

export function lynxEditorGuard() {
    return {
        init() {
            this.warnOnLeave = (event) => {
                if (this.hasUnsavedChanges()) {
                    event.preventDefault()
                    event.returnValue = ''
                }
            }
            window.addEventListener('beforeunload', this.warnOnLeave)
        },

        destroy() {
            window.removeEventListener('beforeunload', this.warnOnLeave)
        },

        hasUnsavedChanges() {
            return ! sameValues(this.$wire.values, this.$wire.original)
        },

        /** Cmd/Ctrl+S saves only where the Save button is offered (Content tab, page not locked). */
        save(event) {
            event.preventDefault()

            if (this.$root.querySelector('[data-lynx-save]')) {
                this.$wire.save()
            }
        },
    }
}
