import type MarkdownIt from 'markdown-it'
import type Token from 'markdown-it/lib/token.mjs'

/**
 * Recipe cards for the Cookbook: on a page whose front matter has `cookbook`, each `##` section
 * becomes a card. The front matter names each section's course, by its heading:
 *
 *     cookbook:
 *       Views: Mains
 *
 * The Markdown itself stays plain headings and lists, so the page reads the same as Markdown
 * (Copy page, llms.txt). The card's header shows the course and how many recipes it holds.
 */
export function cookbookCards(md: MarkdownIt): void {
    md.core.ruler.push('cookbook_cards', (state) => {
        const courses = state.env?.frontmatter?.cookbook as Record<string, string> | undefined

        if (!courses) {
            return
        }

        const tokens = state.tokens
        const out: Token[] = []
        let isOpen = false

        for (let i = 0; i < tokens.length; i++) {
            const token = tokens[i]

            if (token.type === 'heading_open' && token.tag === 'h2') {
                if (isOpen) {
                    out.push(html(state, '</section>\n'))
                }

                const title = tokens[i + 1]?.content ?? ''
                const count = recipesUntilNextSection(tokens, i)
                out.push(html(state, cardOpening(md, courses[title] ?? '', count)))
                isOpen = true
            }

            out.push(token)
        }

        if (isOpen) {
            out.push(html(state, '</section>\n'))
        }

        state.tokens = out
    })
}

/** The top-level list items between this heading and the next `##`. */
function recipesUntilNextSection(tokens: Token[], start: number): number {
    let count = 0

    for (let i = start + 1; i < tokens.length; i++) {
        if (tokens[i].type === 'heading_open' && tokens[i].tag === 'h2') {
            break
        }

        if (tokens[i].type === 'list_item_open' && tokens[i].level === 1) {
            count++
        }
    }

    return count
}

function cardOpening(md: MarkdownIt, course: string, count: number): string {
    const label = md.utils.escapeHtml(course)
    const recipes = count === 1 ? '1 recipe' : `${count} recipes`

    return `<section class="recipe-card">\n<p class="recipe-card-meta">${label ? `<span class="recipe-card-course">${label}</span>` : ''}<span class="recipe-card-count">${recipes}</span></p>\n`
}

function html(state: { Token: new (type: string, tag: string, nesting: -1 | 0 | 1) => Token }, content: string): Token {
    const token = new state.Token('html_block', '', 0)
    token.content = content

    return token
}
