#!/usr/bin/env sh
set -eu

if grep -n "mashkor_tracking_link' => null" api/modules/v2/controllers/OrderController.php; then
    echo "v2 Mashkor webhook lookup must not require a tracking link before the callback writes it." >&2
    exit 1
fi

if grep -n "mashkor_driver_name' => null" api/modules/v2/controllers/OrderController.php; then
    echo "v2 Mashkor webhook lookup must not require a driver name before the callback writes it." >&2
    exit 1
fi

grep -q "Mashkor Test Driver" api/tests/functional/v2/OrderCest.php
