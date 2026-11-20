/**
 * Ties each error message to its field (WCAG 1.3.1 and 3.3.1), so a screen reader moving to the
 * field reads why it is invalid, not only that it is.
 *
 * Messages carry data-error-for, the error's key (`@error('invite.email') <p data-error-for=...>`).
 * The field is the control bound to that key in the same Livewire component or form: by
 * wire:model, or by name on the sign-in forms. A key for a whole field (values.title) ties the
 * message to each of its controls (values.title.en, values.title.pt); a key ending in .* to the
 * controls under it. The message gets an id, the field aria-describedby and aria-invalid.
 *
 * These attributes exist only in the browser, and a Livewire morph sets attributes back to the
 * server's HTML. Errors only appear with a page load or a server response, so the wiring is
 * derived on load, after each Livewire morph and after wire:navigate, and at no other time: no
 * work while someone types, and no reacting to other scripts' writes (a page-wide observer
 * could trade writes with BlatUI's own, one per frame, forever).
 */

const CONTROLS = 'input:not([type="hidden"]), select, textarea, [contenteditable="true"]'

/** The key a control is bound to: its wire:model (any modifiers), else its name. */
function boundKey(control) {
    for (const { name, value } of control.attributes) {
        if (name === 'wire:model' || name.startsWith('wire:model.')) return value
    }

    return control.getAttribute('name')
}

/** The controls an error key is about, in the message's component or form. */
function controlsFor(message) {
    const key = message.dataset.errorFor ?? ''
    const prefix = (key.endsWith('.*') ? key.slice(0, -2) : key) + '.'
    const scope = message.closest('[wire\\:id], form') ?? document
    const controls = [...scope.querySelectorAll(CONTROLS)]
    const exact = controls.filter((control) => boundKey(control) === key)

    return exact.length ? exact : controls.filter((control) => (boundKey(control) ?? '').startsWith(prefix))
}

/** A stable id from the key, unique on the page. */
function messageId(message, index) {
    if (message.id) return message.id
    const base = 'lynx-error-' + (message.dataset.errorFor ?? '').replace(/[^\w-]+/g, '-')
    message.id = document.getElementById(base) ? `${base}-${index}` : base

    return message.id
}

function describe(control, id) {
    const ids = (control.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean)
    if (!ids.includes(id)) control.setAttribute('aria-describedby', [...ids, id].join(' '))
    if (control.getAttribute('aria-invalid') !== 'true') control.setAttribute('aria-invalid', 'true')
}

/** One pass over the page: every message tied to its controls. */
export function linkFieldErrors(root = document) {
    root.querySelectorAll('[data-error-for]').forEach((message, index) => {
        const controls = controlsFor(message)
        if (!controls.length) return
        const id = messageId(message, index)
        controls.forEach((control) => describe(control, id))
    })
}

/** Links the messages on load, and again after Livewire updates or changes the page. */
export function keepFieldErrorsLinked() {
    let queued = false
    const queue = () => {
        if (queued) return
        queued = true
        // Once per frame, after every component a response morphed.
        requestAnimationFrame(() => {
            queued = false
            linkFieldErrors()
        })
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => linkFieldErrors())
    else linkFieldErrors()

    document.addEventListener('livewire:init', () => window.Livewire.hook('morphed', queue))
    document.addEventListener('livewire:navigated', queue)
}
