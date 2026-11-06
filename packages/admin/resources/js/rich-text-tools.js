/**
 * The rich text editor's toolbar commands and the extensions beyond StarterKit. Every extension
 * loads on every field, so markup a tool makes survives an edit in a field that hides the tool;
 * a field's "tools" setting only adds buttons (RichTextTools, ADR-0107).
 */
import Superscript from '@tiptap/extension-superscript'
import Subscript from '@tiptap/extension-subscript'
import Highlight from '@tiptap/extension-highlight'
import TextAlign from '@tiptap/extension-text-align'

/** A command that toggles a mark or node, pressed while the cursor is in one. */
function toggle(command, name, attributes) {
    return { command, attributes, isActive: (editor) => editor.isActive(name, attributes) }
}

/** Alignment of the current block; left is the default and is never written. */
function align(alignment) {
    return { command: 'setTextAlign', attributes: alignment, isActive: (editor) => editor.isActive({ textAlign: alignment }) }
}

/** Toolbar buttons by name: the command run on click and when the button shows as pressed. */
export const TOOLS = {
    bold: toggle('toggleBold', 'bold'),
    italic: toggle('toggleItalic', 'italic'),
    strike: toggle('toggleStrike', 'strike'),
    h2: toggle('toggleHeading', 'heading', { level: 2 }),
    h3: toggle('toggleHeading', 'heading', { level: 3 }),
    bulletList: toggle('toggleBulletList', 'bulletList'),
    orderedList: toggle('toggleOrderedList', 'orderedList'),
    blockquote: toggle('toggleBlockquote', 'blockquote'),
    superscript: toggle('toggleSuperscript', 'superscript'),
    subscript: toggle('toggleSubscript', 'subscript'),
    underline: toggle('toggleUnderline', 'underline'),
    highlight: toggle('toggleHighlight', 'highlight'),
    code: toggle('toggleCode', 'code'),
    codeBlock: toggle('toggleCodeBlock', 'codeBlock'),
    horizontalRule: { command: 'setHorizontalRule', isActive: () => false },
    alignLeft: align('left'),
    alignCenter: align('center'),
    alignRight: align('right'),
    alignJustify: align('justify'),
}

/** Extensions for the tools StarterKit lacks; HtmlCleaner keeps what they write. */
export const TOOL_EXTENSIONS = [
    Superscript,
    Subscript,
    Highlight,
    TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right', 'justify'] }),
]
