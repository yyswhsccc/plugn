#!/usr/bin/env python3
from pathlib import Path

source = Path("common/models/Order.php").read_text()

required_snippets = [
    "$this->ip_address = $this->resolveClientIp();",
    "private function resolveClientIp()",
    "trim((string) Yii::$app->request->getRemoteIP())",
    "array_map('trim', explode(',', $forwardedFor))",
    "filter_var($IParray[0], FILTER_VALIDATE_IP) !== false",
    "return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;",
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("Order client-IP capture hardening is missing: " + ", ".join(missing))

for forbidden in [
    "array_values(array_filter(explode(',', $forwardedFor)))",
    "$ip = $IParray[0];\n                }",
]:
    if forbidden in source:
        raise SystemExit("Order still contains unsafe forwarded IP handling: " + forbidden)

print("Order client-IP capture hardening guard is present.")
