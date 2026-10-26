---
layout: home
title: Lynx CMS
titleTemplate: A multilingual CMS for Laravel

hero:
  name: Lynx
  text: A multilingual CMS for Laravel
  tagline: Add a page tree, runtime fields and a translated admin to the Laravel app you already know. People and AI agents edit through the same safe actions.
  actions:
    - theme: brand
      text: Get started
      link: /getting-started/introduction
    - theme: alt
      text: Build your first site
      link: /getting-started/first-site
    - theme: alt
      text: GitHub
      link: https://github.com/LynxCMS/lynxcms

features:
  - title: It is still your Laravel app
    details: Lynx is a set of Composer packages. Your routes, controllers, Blade views, queues and deployments keep working; Lynx serves the URLs your app leaves free.
  - title: An admin for editors, if you want one
    details: A page tree, an editor laid out per template with a tab per language, drafts, scheduling, history and a trash, in plain words. Or skip it and manage content from code.
  - title: Livewire, in your pages and in the admin
    details: Templates use any Livewire component, editors can place components on pages themselves, and the admin is built with Livewire 4.
  - title: Fields without migrations
    details: Define templates and fields in the admin, in PHP or in YAML files you commit. Lynx creates their tables at runtime.
  - title: Languages are rows
    details: Add a language to a running site in one step. Each field falls back to the next language when a translation is missing, and URLs are translated too.
  - title: Find pages with one line
    details: "Selectors such as template=post, sort=-published_on, limit=10 compile to plain SQL, with fallback, permissions and visibility rules applied."
  - title: Every write is an action
    details: The admin, Artisan, your code, the MCP server and the AI agent call the same validated actions, with permissions, hooks, dry runs and an activity log.
  - title: Ready for AI agents
    details: An MCP server and a built-in agent are generated from the actions. Agents plan their changes as a dry run, and apply only what you approve.
  - title: Modules when you need them
    details: Menus, forms, SEO, sitemaps, a page cache, page passwords, media and a static site export. Install only what the site uses.
---
