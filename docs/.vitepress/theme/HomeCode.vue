<script setup lang="ts">
import { withBase } from 'vitepress'

// Calls from "Your First Site" (docs/snippets/first-site/schema.php and pages.php), which
// tests/Feature/Docs/FirstSiteTutorialTest.php runs, shortened to fit the hero.
const code = `// Fields and a template, without a migration
lynx()->fields->create('summary', 'textarea', translatable: true);
lynx()->templates->create('post', fields: ['title', 'summary']);

// A page, saved by the same action the admin runs
$post = lynx()->pages->new('post', '/blog');
$post->title = 'Hello, world';
$post->save();

// The latest posts, found with a selector
lynx()->pages->find('template=post, sort=-published, limit=3');`

/** Each line as parts: comments and quoted strings get a color of their own. */
const lines = code.split('\n').map((line) =>
    line.trimStart().startsWith('//')
        ? [{ text: line, kind: 'comment' }]
        : line.split(/('[^']*')/).filter(Boolean).map((text) => ({ text, kind: text.startsWith("'") ? 'string' : '' })),
)
</script>

<!-- The code half of the docs home page's hero (home-hero-image). The video below is the admin half. -->
<template>
    <figure class="home-code">
        <p class="label">From code</p>
        <pre><code><template v-for="(line, index) in lines" :key="index"><span v-for="(part, p) in line" :key="p" :class="part.kind">{{ part.text }}</span>
</template></code></pre>
        <figcaption>
            <a :href="withBase('/getting-started/first-site')">From Your First Site</a>
        </figcaption>
    </figure>
</template>

<style scoped>
.home-code {
    margin: 0;
    width: 100%;
    text-align: left;
}

.label {
    margin: 0 0 10px;
    color: var(--vp-c-text-2);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

pre {
    margin: 0;
    padding: 20px 22px;
    overflow-x: auto;
    border: 1px solid var(--vp-c-divider);
    border-radius: 12px;
    background: var(--vp-code-block-bg);
    color: var(--vp-c-text-1);
    font-family: var(--vp-font-family-mono);
    font-size: 13px;
    line-height: 1.7;
}

.comment {
    color: var(--vp-c-text-3);
}

.string {
    color: var(--vp-c-brand-1);
}

figcaption {
    margin-top: 10px;
    font-size: 14px;
}

figcaption a {
    color: var(--vp-c-brand-1);
    font-weight: 500;
}

figcaption a::after {
    content: " →";
}
</style>
