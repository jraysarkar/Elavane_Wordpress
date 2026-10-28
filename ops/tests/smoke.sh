#!/usr/bin/env bash
# Quick health check of the key storefront pages.
# Usage: ops/tests/smoke.sh [base-url]     (default: the dev server)
# Fails if a page is not HTTP 200, shows a PHP error, or leaks a raw shortcode.

set -uo pipefail

BASE="${1:-http://167.71.233.143}"
PAGES=(
	"/"
	"/shop/"
	"/product-category/trousers/"
	"/product-category/t-shirt/polo/"
	"/product/baggy-fit-textured-cotton-trousers/"
	"/product/ice-blue-classic-fit-polo/"
	"/cart/"
	"/order-tracking/"
	"/contact-us/"
	"/?s=trouser&post_type=product"
)

fail=0
for path in "${PAGES[@]}"; do
	body="$(curl -s -L -m 60 -w '\n%{http_code} %{time_total}' "$BASE$path")"
	meta="$(tail -n1 <<<"$body")"
	code="${meta%% *}"
	secs="${meta##* }"
	problems=""
	[[ "$code" == "200" ]] || problems+=" http=$code"
	grep -qiE 'Fatal error|Parse error|Warning</b>:|critical error on this website' <<<"$body" && problems+=" php-error"
	shortcodes="$(sed -e 's/<script[^>]*>.*<\/script>//g' <<<"$body" | grep -oE '>[^<]*\[[a-z_]{3,}[^]]*\][^<]*<' | grep -oE '\[[a-z_]{3,}[^]]*\]' | sort -u | tr '\n' ' ')"
	[[ -n "$shortcodes" ]] && problems+=" raw-shortcode: $shortcodes"
	if [[ -n "$problems" ]]; then
		printf 'FAIL %-48s %5ss%s\n' "$path" "$secs" "$problems"
		fail=1
	else
		printf 'ok   %-48s %5ss\n' "$path" "$secs"
	fi
done
exit $fail
