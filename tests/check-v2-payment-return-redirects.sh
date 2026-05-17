#!/usr/bin/env bash
set -euo pipefail

base_controller="api/modules/v2/controllers/BaseController.php"
payment_controllers=(
  "api/modules/v2/controllers/OrderController.php"
  "api/modules/v2/controllers/payment/MoyasarController.php"
  "api/modules/v2/controllers/payment/StripeController.php"
  "api/modules/v2/controllers/payment/TabbyController.php"
  "api/modules/v2/controllers/payment/UpaymentController.php"
)

rg -q "PAYMENT_RETURN_FALLBACK_URL = 'https://www.plugn.io'" "$base_controller"
rg -q "function buildRestaurantReturnUrl" "$base_controller"
rg -q 'in_array\(\$scheme, \['\''http'\'', '\''https'\''\], true\)' "$base_controller"
rg -q 'isset\(\$parts\['\''user'\''\]\) \|\| isset\(\$parts\['\''pass'\''\]\)' "$base_controller"

if rg 'restaurant_domain \.[[:space:]]*['\''"]/payment-|redirect\(\$paymentRecord->restaurant->restaurant_domain|redirect\(\$payment->restaurant->restaurant_domain|\$order->restaurant->restaurant_domain \.[[:space:]]*['\''"]/payment-' \
  api/modules/v2/controllers \
  api/modules/v2/controllers/payment; then
  echo "direct v2 payment return redirect from restaurant_domain found" >&2
  exit 1
fi

for controller in "${payment_controllers[@]}"; do
  rg -q 'buildRestaurantReturnUrl\(' "$controller"
done
