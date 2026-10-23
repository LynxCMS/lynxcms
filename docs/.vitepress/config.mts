import { defineConfig } from 'vitepress'

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
    // Files in docs/ that are not pages.
    srcExclude: ['**/README.md', 'node_modules/**'],

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
            { text: 'Reference', link: '/reference/configuration', activeMatch: '^/reference/' },
            { text: 'Roadmap', link: '/prologue/roadmap' },
        ],

        sidebar: [
            {
                text: 'Prologue',
                collapsed: true,
                items: [
                    { text: 'Roadmap', link: '/prologue/roadmap' },
                ],
            },
            {
                text: 'Getting Started',
                items: [
                    { text: 'Introduction', link: '/getting-started/introduction' },
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
                text: 'AI',
                items: [
                    { text: 'MCP Server and Agent', link: '/ai/' },
                ],
            },
            {
                text: 'Digging Deeper',
                items: [
                    { text: 'Actions and Hooks', link: '/deeper/actions-and-hooks' },
                    { text: 'Visibility Rules', link: '/deeper/visibility' },
                    { text: 'Schema and Content Packages', link: '/deeper/schema-and-content' },
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
        ],

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
