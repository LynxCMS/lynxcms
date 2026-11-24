<script setup lang="ts">
import { ref } from 'vue'
import { withBase } from 'vitepress'

type Feature = {
    title: string
    details: string
    file?: string
    code?: string
    image?: { src: string; alt: string }
    output?: { label: string; text: string }
}

// Code comes from the tested snippets in docs/snippets or from the page each feature links to,
// shortened to fit. Screenshots are the admin's own, from docs/public/img/admin. Outputs follow
// the commands' real formats (FindCommand's table, ChangeSet::summary(), AskCommand's plan box),
// with the blog of "Your First Site" as the data.
const features: Feature[] = [
    {
        title: 'Fields without migrations',
        details: 'Define templates and fields in the admin, in PHP or in YAML files you commit. Lynx creates their tables while the site runs.',
        code: `lynx()->fields->create('summary', 'textarea', translatable: true);
lynx()->fields->create('body', 'textarea', translatable: true, settings: ['format' => 'html']);
lynx()->fields->create('published_on', 'date');

lynx()->templates->create('post', fields: ['title', 'summary', 'body', 'published_on']);

// Later, on the live site: a new field, added to the template.
lynx()->fields->create('reading_time', 'integer');
lynx()->templates->update('post', ['add' => ['reading_time']]);`,
        output: {
            label: 'Changes recorded',
            text: `+ Create textarea field summary (translatable)
+ Create textarea field body (translatable)
+ Create date field published_on
+ Create template post with fields: title, summary, body, published_on
+ Create integer field reading_time
+ Update template post; add reading_time`,
        },
    },
    {
        title: 'A language in one step',
        details: "Add a language to a running site without a deploy. A field without a translation shows the next language's value, marked [en] below, and every page gets a URL per language.",
        code: `lynx()->languages->create('pt', 'Português', locale: 'pt-PT', fallback: 'en');

$post = lynx()->pages->get('/blog/hello-world');
$post->setLanguageName('pt', 'ola-mundo');           // /pt/blogue/ola-mundo
$post->setLanguageValue('pt', 'title', 'Olá, mundo');
$post->save();`,
        output: {
            label: 'Terminal',
            text: `$ php artisan lynx:find "template=post" --lang=pt
+----+----------+----------------------+--------------------+
| ID | Template | Path                 | Title              |
+----+----------+----------------------+--------------------+
| 3  | post     | /pt/blogue/ola-mundo | Olá, mundo         |
| 4  | post     | /blog/second-post    | A second post [en] |
+----+----------+----------------------+--------------------+
  2 of 2 page(s).`,
        },
    },
    {
        title: 'Search in every language',
        details: 'Search ignores case and accents and matches word beginnings, on SQLite and PostgreSQL. In seven languages, singular and plural find each other.',
        code: `lynx()->pages->find('search*="livros"');    // finds "livro" too
lynx()->pages->find('search*="estacoes"');  // finds "estações"
lynx()->pages->find('search*="estaç"');     // word beginnings
lynx()->pages->find('search*="seasons", template=project');`,
        output: {
            label: 'Terminal',
            text: `$ php artisan lynx:find 'search*="livros"' --lang=pt
+----+----------+----------------------------+-----------------+
| ID | Template | Path                       | Title           |
+----+----------+----------------------------+-----------------+
| 7  | post     | /pt/blogue/o-livro-do-mes  | O livro do mês  |
| 9  | post     | /pt/blogue/livros-de-verao | Livros de verão |
+----+----------+----------------------------+-----------------+
  2 of 2 page(s).`,
        },
    },
    {
        title: 'Find pages with one line',
        details: 'Selectors compile to plain SQL, with fallback, permissions and visibility rules applied. They work in views, controllers, jobs and commands.',
        code: `lynx()->pages->find('template=post, sort=-published_on, limit=10');
lynx()->pages->find('template=course, price<300, title%=risk');
lynx()->pages->find('template=category, children.count>0');
lynx()->pages->find('template=post, language!=pt'); // not translated yet`,
        output: {
            label: 'Terminal',
            text: `$ php artisan lynx:find "template=post, sort=-published_on, limit=10"
+----+----------+-------------------+---------------+
| ID | Template | Path              | Title         |
+----+----------+-------------------+---------------+
| 4  | post     | /blog/second-post | A second post |
| 3  | post     | /blog/hello-world | Hello, world  |
+----+----------+-------------------+---------------+
  2 of 2 page(s).`,
        },
    },
    {
        title: 'Every write is an action',
        details: 'The admin, Artisan, your code, the MCP server and the AI agent call the same validated actions, with permissions, hooks, dry runs and an activity log.',
        file: 'app/Providers/AppServiceProvider.php',
        code: `// A rule for every way of saving: the admin, your code, MCP and the AI agent.
lynx()->hook(SavePage::class)->after(function (Page $page): void {
    $isPublishedPost = $page->template()->name === 'post' && ! $page->hasStatus(PageStatus::UNPUBLISHED);

    if ($isPublishedPost && $page->summary === null) {
        throw ValidationException::withMessages(['values.summary' => 'A published post needs a summary.']);
    }
});

// Any action as a dry run: it does the work, rolls it back and lists the changes.
lynx()->pages->delete('/blog', dryRun: true)->changes->summary();`,
        output: {
            label: 'Output',
            text: `// Publishing a post without a summary, from the admin, Artisan or MCP:
ValidationException: A published post needs a summary.

// The dry run, with nothing deleted:
! Delete page blog and 2 page(s) under it`,
        },
    },
    {
        title: 'AI agents that ask first',
        details: 'An MCP server and a built-in agent are generated from the actions. An agent plans its changes as a dry run, and nothing is saved until someone approves the plan.',
        code: `# Connect Claude Code, or any MCP client, to the site
claude mcp add lynx -- php artisan mcp:start lynx

# Or ask the built-in agent; it shows its plan before writing
php artisan lynx:ask "Create a new course with this content and translate it to PT" --file=course.md`,
        output: {
            label: 'Terminal',
            text: `┌ Plan ──────────────────────────────────────────────── 2 step(s) ┐
│ I'll create the course from course.md and translate it.         │
│                                                                 │
│ 1. pages_save                                                   │
│   • Create course page yoga-teacher-training: title, summary    │
│ 2. pages_translate                                              │
│   • Translate page yoga-teacher-training into pt                │
└─────────────────────────────────────────────────────────────────┘

┌ Apply this plan? ───────────────────────────────────────────────┐
│ ● Yes / ○ No                                                    │
└─────────────────────────────────────────────────────────────────┘

  INFO  Applied.`,
        },
    },
    {
        title: 'An admin for editors',
        details: 'A page tree, an editor laid out per template with a tab per language, drafts, scheduling, history and a trash. Sites that need no admin manage content from code.',
        image: { src: '/img/admin/admin-page-editor.png', alt: 'The page editor on a product page: its fields, their languages, and the Save and Publish buttons' },
    },
]

const active = ref(0)
const tabs = ref<HTMLButtonElement[]>([])

function select(index: number, focus = false) {
    active.value = (index + features.length) % features.length
    if (focus) tabs.value[active.value]?.focus()
}

/** Arrow keys, Home and End move between tabs, as the WAI-ARIA tabs pattern describes. */
function onKeydown(event: KeyboardEvent) {
    const moves: Record<string, number> = { ArrowDown: active.value + 1, ArrowUp: active.value - 1, Home: 0, End: features.length - 1 }
    if (event.key in moves) {
        event.preventDefault()
        select(moves[event.key], true)
    }
}

/** Output lines: the command typed, comments, changes recorded and changes that remove data. */
function outputKind(line: string) {
    if (line.startsWith('$ ')) return 'prompt'
    if (line.startsWith('//')) return 'comment'
    if (line.startsWith('! ') || line.startsWith('ValidationException')) return 'removes'
    if (line.startsWith('+ ') || line.includes('INFO')) return 'adds'
    return ''
}

/** Each line as parts: comments and quoted strings get a color of their own. */
function highlight(code: string) {
    return code.split('\n').map((line) => {
        const comment = line.search(/(^|\s)(\/\/|#)/)
        const body = comment === -1 ? line : line.slice(0, comment)
        const parts = body.split(/('[^']*'|"[^"]*")/).filter(Boolean)
            .map((text) => ({ text, kind: /^['"]/.test(text) ? 'string' : '' }))
        return comment === -1 ? parts : [...parts, { text: line.slice(comment), kind: 'comment' }]
    })
}
</script>

<!-- What Lynx adds, on the docs home page below the cards (home-features-after): tabs on the left,
     the chosen feature's code or screenshot on the right. Below 960px each panel opens under its tab. -->
<template>
    <section class="home-highlights" aria-labelledby="home-highlights-title">
        <div class="container">
            <h2 id="home-highlights-title">What Lynx adds to your app</h2>
            <div class="layout">
                <div class="tabs" role="tablist" aria-orientation="vertical" aria-labelledby="home-highlights-title" @keydown="onKeydown">
                    <template v-for="(feature, index) in features" :key="feature.title">
                        <button
                            :id="`lynx-tab-${index}`"
                            ref="tabs"
                            class="tab"
                            :class="{ active: index === active }"
                            role="tab"
                            type="button"
                            :aria-selected="index === active"
                            :aria-controls="`lynx-panel-${index}`"
                            :tabindex="index === active ? 0 : -1"
                            @click="select(index)"
                        >
                            <span class="tab-title">{{ feature.title }}</span>
                            <span v-if="index === active" class="tab-details">{{ feature.details }}</span>
                        </button>
                        <div v-if="index === active" class="panel panel-inline" aria-hidden="true">
                            <div v-if="feature.code" class="code">
                                <p v-if="feature.file" class="file">{{ feature.file }}</p>
                                <pre><code><template v-for="(line, l) in highlight(feature.code)" :key="l"><span v-for="(part, p) in line" :key="p" :class="part.kind">{{ part.text }}</span>
</template></code></pre>
                            </div>
                            <div v-if="feature.output" class="output">
                                <p class="file">{{ feature.output.label }}</p>
                                <pre><code><span v-for="(line, l) in feature.output.text.split('\n')" :key="l" :class="outputKind(line)">{{ line }}
</span></code></pre>
                            </div>
                            <img v-else-if="feature.image" class="shot" :src="withBase(feature.image.src)" alt="" loading="lazy">
                        </div>
                    </template>
                </div>
                <div
                    v-for="(feature, index) in features"
                    v-show="index === active"
                    :id="`lynx-panel-${index}`"
                    :key="feature.title"
                    class="panel panel-side"
                    role="tabpanel"
                    :aria-labelledby="`lynx-tab-${index}`"
                    tabindex="0"
                >
                    <div v-if="feature.code" class="code">
                        <p v-if="feature.file" class="file">{{ feature.file }}</p>
                        <pre><code><template v-for="(line, l) in highlight(feature.code)" :key="l"><span v-for="(part, p) in line" :key="p" :class="part.kind">{{ part.text }}</span>
</template></code></pre>
                    </div>
                    <div v-if="feature.output" class="output">
                        <p class="file">{{ feature.output.label }}</p>
                        <pre><code><span v-for="(line, l) in feature.output.text.split('\n')" :key="l" :class="outputKind(line)">{{ line }}
</span></code></pre>
                    </div>
                    <img v-else-if="feature.image" class="shot" :src="withBase(feature.image.src)" :alt="feature.image.alt" loading="lazy">
                </div>
            </div>
            <p class="more">
                Menus, forms, SEO, sitemaps, a page cache, page passwords, media and a static site export come as
                <a :href="withBase('/getting-started/introduction#packages')">modules</a>: install only what the site uses.
                Every package passes PHPStan at its highest level with no baseline, the tests run on SQLite and PostgreSQL,
                and classes are marked <code>@api</code> or <code>@internal</code>, so you know which ones are safe to build on.
            </p>
        </div>
    </section>
</template>

<style scoped>
/* The same width and side padding as the cards above (VPHomeFeatures). */
.home-highlights {
    margin-top: 64px;
    padding: 0 24px 48px;
}

@media (min-width: 640px) {
    .home-highlights { padding: 0 48px 64px; }
}

@media (min-width: 960px) {
    .home-highlights { padding: 0 64px 64px; }
}

.container {
    max-width: 1152px;
    margin: 0 auto;
}

h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
    line-height: 1.25;
    letter-spacing: -0.02em;
}

.layout {
    margin-top: 28px;
}

@media (min-width: 960px) {
    .layout {
        display: grid;
        grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
        gap: 40px;
        align-items: start;
    }
}

.tabs {
    display: flex;
    flex-direction: column;
    border-top: 1px solid var(--vp-c-divider);
}

.tab {
    display: block;
    width: 100%;
    padding: 14px 16px;
    border: 0;
    border-bottom: 1px solid var(--vp-c-divider);
    border-left: 3px solid transparent;
    background: transparent;
    color: var(--vp-c-text-2);
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s, border-color 0.2s;
}

@media (hover: hover) {
    .tab:hover {
        color: var(--vp-c-text-1);
        background: var(--vp-c-bg-soft);
    }
}

.tab:focus-visible {
    outline: 2px solid var(--vp-c-brand-1);
    outline-offset: -2px;
}

.tab.active {
    border-left-color: var(--vp-c-brand-1);
    background: var(--vp-c-bg-soft);
    color: var(--vp-c-text-1);
}

.tab-title {
    display: block;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.4;
}

.tab-details {
    display: block;
    margin-top: 6px;
    color: var(--vp-c-text-2);
    font-size: 14px;
    line-height: 1.6;
}

/* Wide screens show the panel beside the tabs, narrow ones under the chosen tab. */
.panel-inline {
    padding: 16px 0 20px;
}

.panel-side {
    display: none;
}

@media (min-width: 960px) {
    .panel-inline { display: none; }
    .panel-side { display: block; position: sticky; top: calc(var(--vp-nav-height) + 24px); }
}

.panel-side:focus-visible {
    outline: 2px solid var(--vp-c-brand-1);
    outline-offset: 4px;
    border-radius: 12px;
}

.code {
    overflow: hidden;
    border: 1px solid var(--vp-c-divider);
    border-radius: 12px;
    background: var(--vp-code-block-bg);
}

.file {
    margin: 0;
    padding: 10px 20px;
    border-bottom: 1px solid var(--vp-c-divider);
    color: var(--vp-c-text-2);
    font-family: var(--vp-font-family-mono);
    font-size: 12px;
}

pre {
    margin: 0;
    padding: 18px 20px;
    overflow-x: auto;
    color: var(--vp-c-text-1);
    font-family: var(--vp-font-family-mono);
    font-size: 13px;
    line-height: 1.7;
}

.output {
    margin-top: 12px;
    overflow: hidden;
    border: 1px solid var(--vp-c-divider);
    border-radius: 12px;
    background: var(--vp-c-bg-alt);
}

.output pre {
    font-size: 12px;
    line-height: 1.6;
}

.prompt {
    color: var(--vp-c-brand-1);
    font-weight: 600;
}

.adds {
    color: var(--vp-c-green-1, var(--vp-c-tip-1));
}

.removes {
    color: var(--vp-c-warning-1);
}

.comment {
    color: var(--vp-c-text-3);
}

.string {
    color: var(--vp-c-brand-1);
}

.shot {
    display: block;
    width: 100%;
    height: auto;
    border: 1px solid var(--vp-c-divider);
    border-radius: 12px;
}

.more {
    max-width: 72ch;
    margin: 36px 0 0;
    color: var(--vp-c-text-2);
    font-size: 14px;
    line-height: 1.6;
}

.more a {
    color: var(--vp-c-brand-1);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.more code {
    font-size: 13px;
}
</style>
