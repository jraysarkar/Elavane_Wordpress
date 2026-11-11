#!/usr/bin/env bash
# Quick health check of the key storefront pages.
# Usage: ops/tests/smoke.sh [base-url]     (default: the dev server)
# Fails if a page is not HTTP 200, shows a PHP error, or leaks a raw shortcode.

set -uo pipefail

BASE="${1:-https://dev.elvaneclothing.com}"

# Products and categories come from the store itself (whatever is in the admin
# today): the newest two products and every category with products.
path_of() { python3 -c 'import json,sys,urllib.parse; [print(urllib.parse.urlparse(x["permalink"]).path) for x in json.load(sys.stdin)]'; }
PRODUCTS=$(curl -s -m 60 "$BASE/wp-json/wc/store/v1/products?per_page=2&orderby=date&order=desc" | path_of)
CATEGORIES=$(curl -s -m 60 "$BASE/wp-json/wc/store/v1/products/categories?hide_empty=true&per_page=100" | path_of)
[[ -n "$PRODUCTS" && -n "$CATEGORIES" ]] || { echo "FAIL could not list products/categories from the Store API"; exit 1; }

PAGES=(
	"/"
	"/shop/"
	$CATEGORIES
	$PRODUCTS
	"/cart/"
	"/?s=$(basename "$(head -n1 <<<"$PRODUCTS")" | cut -d- -f1)&post_type=product"
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
