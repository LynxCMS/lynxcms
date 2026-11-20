#!/bin/bash
# Runs axe-core (WCAG 2.1 A and AA) on admin screens with agent-browser, one JSON file per screen.
# Usage: tools/a11y/scan.sh <out-dir> <base-url> <path>...
#   composer build && LYNX_ADMIN_REQUIRE_TWO_FACTOR=false php vendor/bin/testbench serve --port=8765
#   agent-browser set media dark   # or light
#   tools/a11y/scan.sh build/a11y http://127.0.0.1:8765 lynx lynx/pages lynx/pages/5/edit
#   python3 tools/a11y/summary.py build/a11y
# The workbench signs the demo superuser in on admin screens. See documentation/reports/accessibility-review-2026-11.md.
set -u
OUT="$1"; BASE="$2"; shift 2
AXE="https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js"
mkdir -p "$OUT"
for path in "$@"; do
  name=$(echo "$path" | tr '/' '_')
  agent-browser open "$BASE/$path" >/dev/null 2>&1
  agent-browser wait --load networkidle >/dev/null 2>&1
  agent-browser eval "window.__axe = null; const s = document.createElement('script'); s.src = '$AXE'; s.onload = () => axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } }).then(r => { window.__axe = JSON.stringify(r.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.length, targets: v.nodes.slice(0, 4).map(n => n.target.join(' ')) }))) }); document.head.appendChild(s); 'ok'" >/dev/null 2>&1
  agent-browser wait --fn "window.__axe !== null" >/dev/null 2>&1
  agent-browser eval "window.__axe" > "$OUT/$name.json" 2>&1
  echo "$path"
done
