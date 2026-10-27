/**
 * Lynx admin scripts, bundled into dist/admin.js (npm run build) and loaded before Livewire
 * starts Alpine.
 */
import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import { Placeholder } from '@tiptap/extensions'
import { lynxEditorGuard } from './editor-guard.js'
import { lynxEditorSection } from './editor-section.js'
import { registerBlatUI } from './blatui-core.js'
import { lynxPasskeyRegister, lynxPasskeyVerify } from './passkeys.js'

/** Toolbar buttons: command run on click and the mark or node that shows it as pressed. */
const TOOLS = {
    bold: ['toggleBold', 'bold'],
    italic: ['toggleItalic', 'italic'],
    strike: ['toggleStrike', 'strike'],
    h2: ['toggleHeading', 'heading', { level: 2 }],
    h3: ['toggleHeading', 'heading', { level: 3 }],
    bulletList: ['toggleBulletList', 'bulletList'],
    orderedList: ['toggleOrderedList', 'orderedList'],
    blockquote: ['toggleBlockquote', 'blockquote'],
}

/** Links keep the title, target and rel the link dialog sets; no defaults are added. */
const LynxLink = Link.extend({
    addAttributes() {
        return { ...this.parent?.(), title: { default: null } }
    },
}).configure({ openOnClick: false, autolink: true, protocols: ['mailto', 'tel'], HTMLAttributes: { target: null, rel: null } })

/** Rich text editors waiting for the link dialog (LinkDialog), by request id. */
const linkRequests = new Map()
let linkRequestCount = 0

window.addEventListener('lynx-link-chosen', (event) => {
    const chosen = event.detail ?? {}
    const apply = linkRequests.get(chosen.requestId)
    linkRequests.delete(chosen.requestId)
    apply?.(chosen)
})

/**
 * Alpine component for html textareas: a Tiptap editor bound to a Livewire property.
 * Usage: x-data="lynxRichText($wire.$entangle('values.body.default', false), false, 'Placeholder', 'label-id', { pageId: 12, language: 'pt' })"
 * The last argument tells the link dialog which page's files to offer and which language's
 * page addresses to use. The editor object stays outside Alpine's reactive state: ProseMirror
 * must not be proxied.
 */
function lynxRichText(model, disabled, placeholder, labelledBy, linkContext = {}) {
    let editor = null

    return {
        value: model,
        active: {},

        init() {
            editor = new Editor({
                element: this.$refs.editor,
                editable: ! disabled,
                content: this.value || '',
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                        // Otherwise an empty paragraph is kept after headings and stored with the content.
                        trailingNode: false,
                        link: false,
                    }),
                    LynxLink,
                    Placeholder.configure({ placeholder: placeholder || '' }),
                ],
                editorProps: {
                    attributes: { class: 'lynx-richtext-content', role: 'textbox', 'aria-multiline': 'true', 'aria-labelledby': labelledBy },
                },
                onUpdate: ({ editor: current }) => {
                    this.value = current.isEmpty ? '' : current.getHTML()
                    this.refresh()
                },
                onSelectionUpdate: () => this.refresh(),
            })

            // Livewire changed the value (after Save or Reload): show it without echoing it back.
            this.$watch('value', (value) => {
                const current = editor.isEmpty ? '' : editor.getHTML()

                if ((value || '') !== current) {
                    editor.commands.setContent(value || '', { emitUpdate: false })
                }
            })

            this.refresh()
        },

        destroy() {
            editor?.destroy()
            editor = null
        },

        run(tool) {
            const [command, , attributes] = TOOLS[tool]
            editor.chain().focus()[command](attributes).run()
            this.refresh()
        },

        /** Opens the link dialog with the link under the cursor (or the selected text). */
        link() {
            const requestId = `link-${++linkRequestCount}`
            editor.chain().focus().extendMarkRange('link').run()
            const { from, to } = editor.state.selection
            const text = editor.state.doc.textBetween(from, to, ' ')
            const current = editor.getAttributes('link')
            linkRequests.set(requestId, (chosen) => this.applyLink(chosen, from, to, text))
            window.Livewire.dispatch('lynx-link-open', {
                requestId,
                href: current.href || '',
                text,
                title: current.title || '',
                target: current.target || '',
                rel: current.rel || '',
                pageId: linkContext.pageId ?? null,
                language: linkContext.language || '',
            })
        },

        /** The dialog's answer: an empty href removes the link; new text replaces the selection. */
        applyLink(chosen, from, to, previousText) {
            const chain = editor.chain().focus().setTextSelection({ from, to })

            if (!chosen.href) {
                chain.extendMarkRange('link').unsetLink().run()
            } else {
                const attrs = { href: chosen.href, title: chosen.title || null, target: chosen.target || null, rel: chosen.rel || null }
                const text = chosen.text || previousText || chosen.href

                text === previousText && from !== to
                    ? chain.setLink(attrs).run()
                    : chain.insertContentAt({ from, to }, { type: 'text', text, marks: [{ type: 'link', attrs }] }).run()
            }

            this.refresh()
        },

        undo() {
            editor.chain().focus().undo().run()
        },

        redo() {
            editor.chain().focus().redo().run()
        },

        refresh() {
            const active = {}

            for (const [tool, [, name, attributes]] of Object.entries(TOOLS)) {
                active[tool] = editor.isActive(name, attributes)
            }

            active.link = editor.isActive('link')
            this.active = active
        },
    }
}

/** Attributes on <html> that BlatUI's theme store reads and writes, by store key. */
const THEME_ATTRIBUTES = {
    base: 'data-base',
    preset: 'data-theme',
    radius: 'data-radius',
    font: 'data-font',
    fontHeading: 'data-font-heading',
    shadow: 'data-shadow',
    spacing: 'data-spacing',
    tracking: 'data-tracking',
    inputStyle: 'data-input-style',
}

/** The light, dark or system scheme a user picked in the user menu, kept in this browser. */
const COLOR_SCHEME_KEY = 'lynx:color-scheme'
const COLOR_SCHEMES = ['light', 'dark', 'system']

function pickedColorScheme() {
    try {
        const scheme = localStorage.getItem(COLOR_SCHEME_KEY)

        return COLOR_SCHEMES.includes(scheme) ? scheme : null
    } catch {
        return null
    }
}

const TRANSLATION_HINTS_KEY = 'lynx:translation-hints'

/** The user menu's translation hints: on or off, remembered per browser (document.blade.php restores it). */
window.lynxTranslationHints = {
    isOn() {
        return document.documentElement.dataset.lynxHints === 'on'
    },

    set(isOn) {
        document.documentElement.dataset.lynxHints = isOn ? 'on' : 'off'

        try {
            localStorage.setItem(TRANSLATION_HINTS_KEY, isOn ? 'on' : 'off')
        } catch {
            // Storage blocked: the choice still applies until the next page load.
        }
    },
}

/** The user menu's appearance choice: current() for the menu, set() to switch and remember. */
window.lynxColorScheme = {
    current() {
        return pickedColorScheme() ?? document.documentElement.dataset.lynxTheme ?? 'system'
    },

    set(scheme) {
        if (!COLOR_SCHEMES.includes(scheme)) {
            return
        }

        try {
            localStorage.setItem(COLOR_SCHEME_KEY, scheme)
        } catch {
            // Storage blocked: the choice still applies until the next page load.
        }

        const theme = window.Alpine.store('theme')
        Object.assign(theme, { darkMode: 'system', mode: scheme })
        theme.apply()
    },
}

/**
 * The admin's look comes from config (lynx-admin.theme and lynx-admin.appearance, rendered on
 * <html>), not from BlatUI's localStorage choices, so every user sees what the site configured.
 * The one exception is the colour scheme a user picked in the user menu.
 * Read before BlatUI registers: its theme store resets these attributes as soon as it starts.
 */
function configuredTheme() {
    const root = document.documentElement
    const picked = pickedColorScheme()
    const scheme = picked ?? root.dataset.lynxTheme
    const theme = { darkMode: picked !== null || scheme === 'system' ? 'system' : false, mode: scheme }

    for (const [key, attribute] of Object.entries(THEME_ATTRIBUTES)) {
        if (root.hasAttribute(attribute)) {
            theme[key] = root.getAttribute(attribute)
        }
    }

    return theme
}

document.addEventListener('alpine:init', () => {
    const configured = configuredTheme()
    registerBlatUI(window.Alpine)
    const theme = window.Alpine.store('theme')
    Object.assign(theme, configured)
    theme.apply()
    window.Alpine.data('lynxRichText', lynxRichText)
    window.Alpine.data('lynxEditorGuard', lynxEditorGuard)
    window.Alpine.data('lynxEditorSection', lynxEditorSection)
    window.Alpine.data('lynxPasskeyRegister', lynxPasskeyRegister)
    window.Alpine.data('lynxPasskeyVerify', lynxPasskeyVerify)
})
