---
name: Lynx Admin
description: The Livewire admin of Lynx CMS, built on BlatUI tokens, white label by design.
colors:
  lynx-teal: "oklch(0.52 0.105 223.128)"
  lynx-teal-foreground: "oklch(0.984 0.019 200.873)"
  lynx-teal-night: "oklch(0.45 0.085 224.283)"
  paper: "oklch(1 0 0)"
  stone-ink: "oklch(0.147 0.004 49.25)"
  stone-wash: "oklch(0.97 0.001 106.424)"
  stone-graphite: "oklch(0.216 0.006 56.043)"
  stone-muted-ink: "oklch(0.49 0.013 58.071)"
  stone-rule: "oklch(0.923 0.003 48.717)"
  stone-ring: "oklch(0.709 0.01 56.259)"
  sidebar-paper: "oklch(0.985 0.001 106.423)"
  night-ground: "oklch(0.147 0.004 49.25)"
  night-surface: "oklch(0.216 0.006 56.043)"
  night-wash: "oklch(0.268 0.007 34.298)"
  night-muted-ink: "oklch(0.709 0.01 56.259)"
  night-rule: "oklch(1 0 0 / 10%)"
  signal-red: "oklch(0.55 0.238 27.325)"
  signal-green: "oklch(0.455 0.125 150)"
  signal-amber: "oklch(0.48 0.11 65)"
  signal-blue: "oklch(0.475 0.16 250)"
  notice-yellow: "oklch(0.87 0.16 92)"
  notice-yellow-ink: "oklch(0.27 0.06 75)"
  mark-deep: "oklch(0.36 0.069 226.5)"
  mark-mid: "oklch(0.523 0.099 225.3)"
  mark-light: "oklch(0.74 0.1 218.2)"
typography:
  headline:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.333
    letterSpacing: "0em"
  title:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
    letterSpacing: "0.025em"
  body:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
    letterSpacing: "0.025em"
  tree-title:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 500
    letterSpacing: "0.025em"
  label:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1
    letterSpacing: "0.025em"
  micro:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.625rem"
    lineHeight: 1.4
    letterSpacing: "0.025em"
rounded:
  sm: "calc(0.3rem - 4px)"
  md: "calc(0.3rem - 2px)"
  lg: "0.3rem"
  xl: "calc(0.3rem + 4px)"
  full: "9999px"
spacing:
  unit: "0.25rem"
  xs: "0.5rem"
  sm: "0.75rem"
  md: "1rem"
  lg: "1.5rem"
  xl: "2rem"
components:
  button-primary:
    backgroundColor: "{colors.lynx-teal}"
    textColor: "{colors.lynx-teal-foreground}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
    height: "2.25rem"
  button-outline:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.stone-ink}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
    height: "2.25rem"
  button-outline-hover:
    backgroundColor: "{colors.stone-wash}"
    textColor: "{colors.stone-graphite}"
  button-ghost:
    textColor: "{colors.stone-ink}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
    height: "2.25rem"
  button-ghost-hover:
    backgroundColor: "{colors.stone-wash}"
  input:
    textColor: "{colors.stone-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0.25rem 0.75rem"
    height: "2.25rem"
  card:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.stone-ink}"
    rounded: "{rounded.xl}"
    padding: "1.5rem"
  badge:
    backgroundColor: "{colors.lynx-teal}"
    textColor: "{colors.lynx-teal-foreground}"
    rounded: "{rounded.md}"
    padding: "0.125rem 0.5rem"
  tree-row:
    textColor: "{colors.stone-ink}"
    typography: "{typography.tree-title}"
    padding: "0.375rem 0.5rem"
  tree-row-hover:
    backgroundColor: "{colors.stone-wash}"
  sidebar:
    backgroundColor: "{colors.sidebar-paper}"
    textColor: "{colors.stone-ink}"
    width: "16rem"
  top-bar:
    backgroundColor: "{colors.paper}"
    height: "3.5rem"
---

# Design System: Lynx Admin

## Overview

**Creative North Star: "The Quiet Workbench"**

The admin is a workbench: a flat, ruled surface where the editor's content lies in front and the tools stay out of the way until a hand reaches for them. Surfaces are separated by hairline borders rather than shadows, color is almost entirely warm stone, and the single accent marks the places where something happens (the primary action, a focus ring, a filled translation, a drop target). Nothing is decorative. Confidence comes from precision: aligned rows, consistent heights, exact states.

The character is technical, dense and developer grade, voiced in plain type. Every text style is set in Geist, letter spacing runs slightly wide across the whole interface, and controls sit at a compact 36px height. Rows in the page tree, fields in the editor and entries in the history read like a well kept record. Density is a feature here, so long as the words stay plain: the look can be technical while the labels speak the editor's language.

The system is white label by construction. Every color, radius, font, shadow and spacing value is a BlatUI token that a preset, a client's accent or a host stylesheet can replace without a rebuild. The defaults below are Lynx's own out of the box look, and the design must hold when a client's logo and accent take their place.

**Key Characteristics:**
- Flat by default: no shadows in the default preset, depth from borders and tonal washes.
- One accent, used for action and state, never for decoration.
- Geist for every text style, headings included; slightly wide tracking everywhere.
- Compact, consistent control heights (36px default, 32px small, 28px extra small).
- Tokens only: Tailwind's raw palette is removed, so every color comes from a theme token.
- Light, dark and system schemes, each user's choice remembered per browser.

## Colors

A warm stone ground with a single teal accent; status colors appear only to report state.

### Primary
- **Lynx Teal** (`lynx-teal`): primary buttons, links, focus emphasis, the filled language facet, the current screen's facet in the sidebar, active drop zones and the moving row's outline. In dark mode it deepens to **Lynx Teal Night** (`lynx-teal-night`) with the same pale foreground (`lynx-teal-foreground`). A client's branding accent replaces both through the `<html>` style attribute, with white or near black text picked by contrast.

### Neutral
- **Paper** (`paper`): the light background, cards, popovers and the top bar (at 95% with a backdrop blur).
- **Stone Ink** (`stone-ink`): body text and headings in light mode; also the dark mode ground (`night-ground`).
- **Stone Graphite** (`stone-graphite`): text on secondary and accent washes; the dark mode card surface (`night-surface`).
- **Stone Wash** (`stone-wash`): muted, secondary and accent backgrounds, hover states, the tree's row hover (at 60%) and code blocks.
- **Stone Muted Ink** (`stone-muted-ink`): help text, placeholders, fallback values and empty language facets. Tuned to clear WCAG AA (4.5:1) on Paper and on Stone Wash.
- **Stone Rule** (`stone-rule`): every border and input outline, and the hairline under each tree row.
- **Stone Ring** (`stone-ring`): focus rings, drawn at 50% opacity and 3px wide.
- **Sidebar Paper** (`sidebar-paper`): the sidebar, a shade off Paper so the navigation reads as its own plane.
- **Night Wash, Night Muted Ink, Night Rule** (`night-wash`, `night-muted-ink`, `night-rule`): the dark mode equivalents of Stone Wash, Stone Muted Ink and Stone Rule; dark borders are white at 10% (inputs at 15%).

### Status
- **Signal Red** (`signal-red`): destructive buttons, validation errors (`aria-invalid` borders and rings), the error notice bar.
- **Signal Green** (`signal-green`): the success notice bar and success badges.
- **Signal Amber** (`signal-amber`): warning text and badges.
- **Signal Blue** (`signal-blue`): informational badges and alerts.
- **Notice Yellow** on **Notice Yellow Ink** (`notice-yellow`, `notice-yellow-ink`): the warning notice bar only, brighter than Signal Amber so a warning across the full width cannot be missed.

### Brand Mark
- **Mark Deep, Mark Mid, Mark Light** (`mark-deep` #004459, `mark-mid` #007595, `mark-light` #56BAD5): the three flat tones of the Lynx mark, with a white eye. They are artwork, held in `--lynx-mark-*` tokens, and no accent or preset changes them. In dark mode the eye takes Mark Light. Mark Mid is Lynx Teal in hex.

### Named Rules
**The Token Only Rule.** No raw color reaches markup. Tailwind's palette is removed (`--color-*: initial`); a color is a BlatUI token or a `--lynx-*` token defaulting to one. Black and white survive only for overlays and text on solid fills. The `--lynx-mark-*` tokens are the one exception: they hold the logo's own colors.

**The Action Accent Rule.** The accent marks where something happens or has happened (an action, focus, a filled value, a drop target). If a screen uses it for decoration, the editor loses the signal.

## Typography

**Heading Font:** Geist, following the body font (`heading_font` is `sans`); a host app may give headings their own font
**Body Font:** Geist (with ui-sans-serif, system-ui), loaded from fonts.bunny.net unless self hosted
**Label/Mono Font:** labels use Geist; code, selectors, keys and page addresses use the system monospace

**Character:** one face from headings to labels keeps the admin plain for editors; Geist stays crisp and legible in dense rows, with even figures in tables. Monospace is kept for code and addresses, never as a costume. Both are preset choices (`appearance.font`, `appearance.heading_font`), so a host app may swap them without touching markup.

### Hierarchy
- **Headline** (600, 1.5rem, tight tracking): the screen's `h1`, once per screen.
- **Title** (600, 1rem): section headings (`h2`) such as a template's Fields or Family, and dialog titles.
- **Tree Title** (500, 0.9375rem): page titles in the tree, a step above body so the structure scans first.
- **Body** (400, 0.875rem): everything editors read and type; inputs use 1rem below the `md` breakpoint to stop mobile zoom, 0.875rem above.
- **Label** (500, 0.875rem, line height 1): field labels and button text.
- **Micro** (0.625rem): the compact language tabs above translatable fields, and nothing else.

### Named Rules
**The Wide Tracking Rule.** Letter spacing runs at +0.025em across the interface (`tracking: wide`); only the headline pulls back to neutral. Do not set per element tracking against it.

## Layout

A fixed shell: a collapsible sidebar on the start side (16rem open, 3rem as icons, remembered in a cookie so it never flashes between pages), a sticky 3.5rem top bar with the sidebar toggle, breadcrumbs, the search box (Cmd or Ctrl+K) and a view site link, and a main column capped at 80rem with 1rem gutters on small screens and 2rem from `md` up (1.5rem and 2rem vertical padding).

Spacing derives from one unit (`--spacing`, 0.25rem); the `spacing` preset scales it to 0.22rem (compact) or 0.28rem (comfortable) without changing any markup. Common steps are 0.5rem inside controls, 1rem between fields, 1.5rem inside cards and between sections. Field rows in the editor use a 12 column grid, so a template can set a field's width and fields sit side by side.

**The One Unit Rule.** Every gap, padding and size is a multiple of the spacing unit, so the spacing preset scales the whole admin at once. Fixed pixel spacing breaks it.

## Elevation & Depth

Flat. The default preset sets every shadow token to `none`, so cards, inputs and buttons carry their `shadow-*` classes but render without them. Depth comes from three things instead: hairline borders in Stone Rule, tonal washes (Stone Wash for hover and secondary surfaces, Sidebar Paper for the navigation plane) and the translucent, blurred top bar. Overlays (dialogs, sheets) dim the page with black at 50%.

The `appearance.shadow` preset can restore BlatUI's shadow scale (`sm`, `lg`, `xl`), so components keep their shadow classes; the design must still read correctly with shadows off.

**The Ruled Surface Rule.** A surface is separated by a border or a wash, never by a shadow alone. If turning shadows off would merge two surfaces, the design is wrong.

## Shapes

Nearly square. The default radius is 0.3rem, and BlatUI derives the scale from it: controls, buttons, inputs and drop zones use `md` (0.3rem less 2px, almost sharp), cards use `xl` (0.3rem plus 4px), checkboxes keep 4px, radios are full circles, and the language state facets keep their slant. The `appearance.radius` preset (0 to 1rem) rescales every corner at once. Borders are always 1px; focus rings are 3px at half opacity, drawn outside the border.

## Components

### Buttons
Quiet until used: compact, square shouldered, one primary per view.
- **Shape:** `md` radius, 36px tall (`sm` 32px, `xs` 28px, `lg` 40px; icon buttons square at the same heights).
- **Primary:** Lynx Teal fill, pale foreground, medium weight label; hover drops the fill to 90%.
- **Outline:** Paper fill with a Stone Rule border; hover washes to Stone Wash.
- **Ghost:** no fill or border; hover washes. Used for toolbar and row actions.
- **Destructive:** Signal Red fill with white text; reserved for actions that remove something (deleting a page, field, language, role or submission, clearing a schedule).
- **Focus:** the border turns Stone Ring and a 3px ring at 50% appears. Disabled buttons drop to 50% opacity and ignore the pointer.

### Badges
- **Style:** `md` radius, 0.75rem text, 0.125rem by 0.5rem padding. Brand variants (primary, secondary, outline, destructive) or semantic tones (success, warning, info, danger) in soft, solid or outline intensity. Soft tones put the tone's text on a 10% tint, tuned for AA.

### Cards / Containers
- **Corner Style:** `xl` radius.
- **Background:** Paper (Night Surface in dark mode).
- **Shadow Strategy:** flat; see Elevation & Depth.
- **Border:** 1px Stone Rule.
- **Internal Padding:** 1.5rem, or sectioned with header, content and footer each padding their own sides.

### Inputs / Fields
- **Style:** outline by default: transparent fill, 1px Stone Rule border, `md` radius, 36px tall, Stone Muted Ink placeholder. Dark mode fills inputs with the input token at 30%. The `input_style` preset switches all inputs to `fill` (muted, borderless) or `inset` (recessed with an inner shadow).
- **Focus:** border to Stone Ring plus the 3px half opacity ring.
- **Error / Disabled:** `aria-invalid` turns the border Signal Red with a red ring at 20%; the message sits below in Signal Red with `role="alert"`. Disabled inputs drop to 50%.
- **Rich text:** the Tiptap editor is framed exactly like a textarea, with a ghost button toolbar separated by a Stone Rule line; pressed tools show the Stone Wash fill.

### Navigation
- **Sidebar:** Sidebar Paper, the brand at the top (the Lynx mark and name until the site sets its own; then the site's logo, or its initial and name, the initial alone when collapsed), the screen links as icon plus label menu buttons, the user menu and sign out at the bottom. Collapses to icons with tooltips.
- **Top bar:** sidebar toggle, a vertical separator, breadcrumbs ending in the current screen, then search and the view link pushed to the end.

### Lynx Mark
A faceted lynx head facing right, flat planes separated by gaps. The full mark (`design/logo/lynx-mark.svg`) is for 24px and up: 28px tall in the sidebar, 40px on the sign-in screens, drawn inline by `<x-lynx-admin::lynx-mark>`. Below 24px, use the 16px drawing on its pixel grid (`lynx-mark-16.svg`), which also makes the favicon. The one color version (`lynx-mark-mono.svg`, `currentColor`) is for print and single color uses. Next to the mark, the name is set in the interface's own type, semibold; there is no drawn wordmark. The admin shows the mark and its favicon only until a site sets a name or a logo of its own (ADR-0089).

### Page Tree (signature component)
The admin's home screen and its most distinctive surface. Each page is a row with a 1px Stone Rule hairline below it, indented by 1.5rem per level, its title in Tree Title type. Hover washes the row; row actions appear after the title on hover, keyboard focus or selection. A chevron rotates 90° when a branch opens. Hidden pages fade to 55%. While dragging, slim drop zones under rows grow to 0.875rem with a dashed Lynx Teal outline at 35%, and the target zone expands to 2.5rem with a teal tint and solid outline; the dragged row's place shows as a 2px teal line. Move mode highlights the moving row with an accent fill and a 1px teal inset outline. All of it is driven by `--lynx-*` tokens that default to BlatUI's, so a preset restyles the tree too.

### Language Tabs (signature component)
Every translatable field carries a compact tab strip (Micro type) with one tab per language. After each label a small facet, the mark's parallelogram, reports the value's state: filled with the accent when the language has its own value, a light accent fill with an accent outline when it falls back to another language, a muted outline when nothing is there. A fallback value reads in Stone Muted Ink italics and becomes the input's placeholder, so the editor always sees what visitors would see. The sentences explaining a fallback are translation hints, shown only when the editor turns them on in the user menu.

### Notification Bar (signature component)
Notices span the full width at the bottom of the viewport, centered 0.875rem medium text on a solid status fill: Signal Green for success, Notice Yellow for warnings, Signal Red for errors. A click or the close mark dismisses them; the bar respects the safe area inset and shows a 3px Stone Ring outline on focus.

## Do's and Don'ts

### Do:
- **Do** take every color from a token (`bg-background`, `text-muted-foreground`, `bg-primary`), so presets, client branding and host stylesheets keep working.
- **Do** build new screens from the BlatUI components in `components/ui` and the `blat-*` native classes; patch a copied component only with a comment saying why.
- **Do** keep controls on the 36, 32 and 28px heights and spacing on multiples of the spacing unit.
- **Do** separate surfaces with a 1px Stone Rule border or a wash, so the design reads correctly with shadows off.
- **Do** check every new screen in light and dark mode and with a client accent other than Lynx Teal, at WCAG AA contrast.
- **Do** show translation state with the language facet vocabulary (filled, light, outline) wherever a value has languages.

### Don't:
- **Don't** use Tailwind's raw palette (`bg-gray-100`, `text-blue-600`); it is removed from the build on purpose.
- **Don't** add new `--lynx-*` tokens for anything BlatUI already has a token for; Lynx tokens exist only for the page tree, the notification bar and the Lynx mark.
- **Don't** recolor the Lynx mark with the accent, stretch it, or show it in an admin the site has branded.
- **Don't** rely on a shadow to separate surfaces or to show state.
- **Don't** use the accent for decoration, illustration or large fills.
- **Don't** hardcode fonts, radii or letter spacing on an element; they are preset dimensions the host app controls.
- **Don't** use the Micro size for anything other than the compact language tabs.
