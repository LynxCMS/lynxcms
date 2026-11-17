<script setup lang="ts">
import { withBase } from 'vitepress'
</script>

<!-- The admin tour on the docs home page, between the hero and the cards (home-features-before). -->
<template>
    <div class="home-tour">
        <figure class="container">
            <p class="label">From the admin</p>
            <video
                :src="withBase('/img/admin/admin-tour.mp4')"
                :poster="withBase('/img/admin/admin-tour-poster.jpg')"
                controls
                muted
                playsinline
                preload="metadata"
                aria-label="A short tour of the admin: the home screen, the page tree, and the page editor with its tabs and search"
            ></video>
            <figcaption>The admin: the Home screen, the page tree and the page editor.</figcaption>
        </figure>
    </div>
</template>

<style scoped>
/* The same width and side padding as the cards below (VPHomeFeatures). */
.home-tour {
    margin-bottom: 48px;
    padding: 0 24px;
}

.container {
    max-width: 1152px;
    margin: 0 auto;
}

@media (min-width: 640px) {
    .home-tour { padding: 0 48px; }
}

@media (min-width: 960px) {
    .home-tour { padding: 0 64px; margin-bottom: 64px; }
}

.label {
    margin: 0 0 10px;
    color: var(--vp-c-text-2);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.home-tour video {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 10;
    border: 1px solid var(--vp-c-divider);
    border-radius: 12px;
    background: var(--vp-c-bg-soft);
}

.home-tour figcaption {
    margin-top: 12px;
    color: var(--vp-c-text-2);
    font-size: 14px;
    text-align: center;
}
</style>
