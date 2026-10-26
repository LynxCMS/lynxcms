import { readdirSync, readFileSync } from 'node:fs'
import { join, relative } from 'node:path'
import { defineConfig, type DefaultTheme } from 'vitepress'

const DOCS = join(__dirname, '..')

/**
 * Pages whose front matter says `draft: true`, as paths relative to docs/ ("getting-started/compared.md").
 * Found with a pattern, not a YAML parser, so a page being written with broken front matter is
 * still recognized.
 */
function draftPages(folder = DOCS): string[] {
    return readdirSync(folder, { withFileTypes: true }).flatMap((entry) => {
        const path = join(folder, entry.name)

        if (entry.isDirectory()) {
            return ['node_modules', '.vitepress', 'snippets', 'public'].includes(entry.name) ? [] : draftPages(path)
        }

        const frontMatter = entry.name.endsWith('.md') ? /^---\n([\s\S]*?)\n---/.exec(readFileSync(path, 'utf8')) : null

        return frontMatter && /^draft:\s*true\s*$/m.test(frontMatter[1]) ? [relative(DOCS, path)] : []
    })
}

// Drafts show in `npm run dev`, and are left out of builds (the published site and PR previews)
// unless DOCS_DRAFTS=1. Links to a draft from other pages are not dead links while it is one.
const drafts = process.argv.includes('build') && process.env.DOCS_DRAFTS !== '1' ? draftPages() : []
const draftLinks = new Set(drafts.map((page) => '/' + page.replace(/\.md$/, '').replace(/(^|\/)index$/, '$1')))

function withoutDrafts(items: DefaultTheme.SidebarItem[]): DefaultTheme.SidebarItem[] {
    return items
        .filter((item) => item.link === undefined || !draftLinks.has(item.link))
        .map((item) => (item.items ? { ...item, items: withoutDrafts(item.items) } : item))
}

// The Lynx documentation site, built from this folder and served at lynxcms.org/cms/docs by a
// Cloudflare Worker with static assets (wrangler.jsonc, .github/workflows/docs.yml). Pages keep
// their place in the sidebar below; a page that is not listed here still builds, but readers only
// find it through links and search.
export default defineConfig({
    base: '/cms/docs/',
    // The asset folder mirrors the route's path, as Workers routes on a path require.
    outDir: '.vitepress/dist/cms/docs',
    title: 'Lynx',
    titleTemplate: ':title · Lynx CMS',
    description: 'A multilingual CMS for Laravel that people and AI agents can build and run safely.',
    lang: 'en',
    cleanUrls: true,
    lastUpdated: true,
    // Files in docs/ that are not pages, and drafts in a build.
    srcExclude: ['**/README.md', 'node_modules/**', ...drafts],
    ignoreDeadLinks: [(url) => draftLinks.has(url.replace(/[#?].*$/, '').replace(/\.html$/, ''))],

    head: [
        ['meta', { name: 'theme-color', content: '#0e7490' }],
        ['meta', { property: 'og:site_name', content: 'Lynx CMS' }],
    ],

    markdown: {
        theme: { light: 'github-light', dark: 'github-dark' },
        // PHPStan's config files.
        languageAlias: { neon: 'yaml' },
        config(md) {
            // Inline code is shown as written: Blade's {{ }} would otherwise be read as Vue.
            const inlineCode = md.renderer.rules.code_inline!
            md.renderer.rules.code_inline = (...args) => inlineCode(...args).replace('<code', '<code v-pre')
        },
    },

    themeConfig: {
        siteTitle: 'Lynx',

        nav: [
            { text: 'Guide', link: '/getting-started/introduction', activeMatch: '^/(getting-started|basics|admin|modules|ai|deeper)/' },
            { text: 'Guides', link: '/guides/training-site/', activeMatch: '^/guides/' },
            { text: 'Reference', link: '/reference/configuration', activeMatch: '^/reference/' },
            { text: 'Roadmap', link: '/prologue/roadmap' },
        ],

        sidebar: withoutDrafts([
            {
                text: 'Prologue',
                collapsed: true,
                items: [
                    { text: 'Release Notes', link: '/prologue/release-notes' },
                    { text: 'Upgrade Guide', link: '/prologue/upgrade' },
                    { text: 'Roadmap', link: '/prologue/roadmap' },
                ],
            },
            {
                text: 'Getting Started',
                items: [
                    { text: 'Introduction', link: '/getting-started/introduction' },
                    { text: 'Lynx Compared', link: '/getting-started/compared' },
                    { text: 'Installation', link: '/getting-started/installation' },
                    { text: 'Your First Site', link: '/getting-started/first-site' },
                    { text: 'Core Concepts', link: '/getting-started/concepts' },
                ],
            },
            {
                text: 'The Basics',
                items: [
                    { text: 'Templates and Fields', link: '/basics/templates-and-fields' },
                    { text: 'Field Types', link: '/basics/field-types' },
                    { text: 'Views', link: '/basics/views' },
                    { text: 'Finding Pages', link: '/basics/selectors' },
                    { text: 'Languages', link: '/basics/languages' },
                    { text: 'Images and Files', link: '/basics/images-and-files' },
                    { text: 'Publishing', link: '/basics/publishing' },
                ],
            },
            {
                text: 'The Admin',
                items: [
                    { text: 'Overview', link: '/admin/' },
                    { text: 'Sign-in and Accounts', link: '/admin/sign-in' },
                    { text: 'Roles and Permissions', link: '/admin/permissions' },
                    { text: 'Extending the Admin', link: '/admin/extending' },
                ],
            },
            {
                text: 'Modules',
                items: [
                    { text: 'Menus', link: '/modules/menus' },
                    { text: 'Forms', link: '/modules/forms' },
                    { text: 'SEO', link: '/modules/seo' },
                    { text: 'Sitemap', link: '/modules/sitemap' },
                    { text: 'Page Cache', link: '/modules/page-cache' },
                    { text: 'Page Passwords', link: '/modules/page-passwords' },
                    { text: 'Static Site Export', link: '/modules/static-site' },
                ],
            },
            {
                text: 'Guide: A Training Site',
                collapsed: true,
                items: [
                    { text: 'Overview', link: '/guides/training-site/' },
                    { text: '1. Plan and Install', link: '/guides/training-site/install' },
                    { text: '2. The Content Model', link: '/guides/training-site/content-model' },
                    { text: '3. The Front End', link: '/guides/training-site/front-end' },
                    { text: '4. The Editors\' Side', link: '/guides/training-site/editors' },
                    { text: '5. SEO', link: '/guides/training-site/seo' },
                    { text: '6. Leads', link: '/guides/training-site/leads' },
                    { text: '7. Paid Bookings', link: '/guides/training-site/bookings' },
                    { text: '8. Members Area', link: '/guides/training-site/members' },
                    { text: '9. Surveys', link: '/guides/training-site/surveys' },
                    { text: '10. Launch', link: '/guides/training-site/launch' },
                ],
            },
            {
                text: 'Guide: Meeting Rooms',
                collapsed: true,
                items: [
                    { text: 'Overview', link: '/guides/meeting-rooms/' },
                    { text: '1. Plan and Install', link: '/guides/meeting-rooms/install' },
                    { text: '2. Rooms Are Pages', link: '/guides/meeting-rooms/rooms' },
                    { text: '3. Bookings Are Actions', link: '/guides/meeting-rooms/bookings' },
                    { text: '4. The Admin', link: '/guides/meeting-rooms/admin' },
                    { text: '5. Screens', link: '/guides/meeting-rooms/screens' },
                    { text: '6. Staff Pages, Mail and Calendars', link: '/guides/meeting-rooms/staff' },
                    { text: '7. AI Assistants', link: '/guides/meeting-rooms/assistants' },
                    { text: '8. Hosting', link: '/guides/meeting-rooms/hosting' },
                ],
            },
            {
                text: 'AI',
                items: [
                    { text: 'MCP Server and Agent', link: '/ai/' },
                ],
            },
            {
                text: 'Digging Deeper',
                items: [
                    { text: 'Actions and Hooks', link: '/deeper/actions-and-hooks' },
                    { text: 'Page Access', link: '/deeper/visibility' },
                    { text: 'Schema and Content Packages', link: '/deeper/schema-and-content' },
                    { text: 'Working on a Live Site', link: '/deeper/live-site' },
                    { text: 'Custom Field Types', link: '/deeper/custom-field-types' },
                    { text: 'Server Capacity', link: '/deeper/server-capacity' },
                ],
            },
            {
                text: 'Reference',
                items: [
                    { text: 'Artisan Commands', link: '/reference/artisan' },
                    { text: 'Configuration', link: '/reference/configuration' },
                ],
            },
        ]),

        search: { provider: 'local' },

        editLink: {
            pattern: 'https://github.com/LynxCMS/lynxcms/edit/main/docs/:path',
            text: 'Edit this page on GitHub',
        },

        socialLinks: [
            { icon: 'github', link: 'https://github.com/LynxCMS/lynxcms' },
        ],

        outline: { level: [2, 3], label: 'On this page' },

        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Lynx CMS',
        },
    },
})
