#!/bin/sh
# shellcheck disable=SC2016
set -eu

controller="shortner/controllers/ShortenerController.php"

grep -F "private const FALLBACK_URL = 'https://www.plugn.io';" "$controller" >/dev/null
grep -F "private const SAFE_REDIRECT_SCHEMES = ['http', 'https'];" "$controller" >/dev/null
grep -F 'normalizeRestaurantDomain($model->restaurant->restaurant_domain)' "$controller" >/dev/null
grep -F "preg_match('/[\\x00-\\x1F\\x7F]/', \$domain)" "$controller" >/dev/null
grep -F 'parse_url($domain)' "$controller" >/dev/null
grep -F "isset(\$parts['user']) || isset(\$parts['pass'])" "$controller" >/dev/null
grep -F 'rawurlencode((string)$orderId)' "$controller" >/dev/null

if grep -F '$this->redirect($model->restaurant->restaurant_domain' "$controller" >/dev/null; then
    echo "shortener redirect must not use raw restaurant_domain directly" >&2
    exit 1
fi
