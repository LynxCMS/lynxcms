#!/usr/bin/env bash
# Waits until Composer's metadata on repo.packagist.org lists a package's tag.
#
# Usage: wait-for-packagist.sh <vendor/package> <tag>
#
# Packagist lists a tag the split workflow has just pushed one package at a time, and its CDN
# caches the metadata for up to 15 minutes. Every few attempts this prints the HTTP status and
# the newest version returned, so a timeout shows whether the tag was missing, the cache stale,
# or the request refused. (packagist.org's own API is no help here: it is cached for 12 hours.)
set -uo pipefail

package="$1"
tag="$2"
attempts=30       # 10 minutes: past that a mirror is behind, and the daily run catches up
pause=20
report_every=6    # every 2 minutes
user_agent="lynxcms-starter-ci (+https://github.com/LynxCMS/lynxcms)"
body="$(mktemp)"
trap 'rm -f "$body"' EXIT

newest() {
  jq -r --arg package "$package" '(.packages[$package] // [])[0].version // "none"' "$body" 2>/dev/null || echo "unreadable"
}

report() {
  echo "${package}: attempt $1, repo.packagist.org answered $2, newest version $(newest)." >&2
}

for attempt in $(seq 1 "$attempts"); do
  status="$(curl -s -A "$user_agent" -H 'Cache-Control: no-cache' -o "$body" -w '%{http_code}' "https://repo.packagist.org/p2/${package}.json")"

  if [ "$status" = "200" ] && grep -q "\"${tag}\"" "$body"; then
    echo "${package} ${tag} is on Packagist."
    exit 0
  fi

  if [ $((attempt % report_every)) -eq 1 ]; then
    report "$attempt" "$status"
  fi

  sleep "$pause"
done

report "$attempts" "$status"
echo "${package} ${tag} is not on Packagist yet." >&2
exit 1
