/**
 * The admin's notification bar (components/notifications.blade.php). Notices close on their own
 * after a while, longer for warnings and errors, and wait while the pointer or the keyboard focus
 * is on the bar, so they can be read to the end (WCAG 2.2.1): their time runs again on leaving.
 */

const DURATIONS = { success: 4000, warning: 6000, error: 9000 }

export function lynxNotices() {
    return {
        notices: [],
        isHeld: false,

        add(message, type) {
            if (!message) return
            type = DURATIONS[type] ? type : 'success'
            const notice = { id: Date.now() + Math.random(), message, type, remaining: DURATIONS[type], timer: null, startedAt: 0 }
            this.notices.push(notice)
            if (!this.isHeld) this.start(notice)
        },

        fromToast(toast) {
            this.add(toast.description || toast.title || '', toast.type === 'error' ? 'error' : (toast.type === 'warning' ? 'warning' : 'success'))
        },

        remove(id) {
            const notice = this.notices.find((n) => n.id === id)
            if (notice) clearTimeout(notice.timer)
            this.notices = this.notices.filter((n) => n.id !== id)
        },

        start(notice) {
            notice.startedAt = Date.now()
            notice.timer = setTimeout(() => this.remove(notice.id), notice.remaining)
        },

        /** The pointer or the focus came onto the bar: every notice waits. */
        hold() {
            if (this.isHeld) return
            this.isHeld = true
            this.notices.filter((notice) => notice.timer !== null).forEach((notice) => {
                clearTimeout(notice.timer)
                notice.timer = null
                notice.remaining = Math.max(0, notice.remaining - (Date.now() - notice.startedAt))
            })
        },

        /** Both left the bar: the notices' time runs again, from where it stopped. */
        release() {
            if (!this.isHeld || this.$root.matches(':hover') || this.$root.contains(document.activeElement)) return
            this.isHeld = false
            this.notices.forEach((notice) => this.start(notice))
        },
    }
}
