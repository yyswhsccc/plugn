#!/usr/bin/env python3
from pathlib import Path

files = [
    Path("api/modules/v1/Module.php"),
    Path("api/modules/v2/Module.php"),
]

for path in files:
    source = path.read_text()
    required_snippets = [
        "$ip = $this->resolveClientIp();",
        "$isBlocked = $ip && BlockedIp::find()->andWhere(['ip_address' => $ip])->exists();",
        "private function resolveClientIp()",
        "trim((string) Yii::$app->request->getRemoteIP())",
        "array_map('trim', explode(',', $forwardedFor))",
        "filter_var($IParray[0], FILTER_VALIDATE_IP) !== false",
        "return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;",
    ]

    missing = [snippet for snippet in required_snippets if snippet not in source]
    if missing:
        raise SystemExit(f"{path} is missing API blocked-IP hardening: {', '.join(missing)}")

    for forbidden in [
        "array_values(array_filter(explode(',', $forwardedFor)))",
        "$ip = $IParray[0];\n        }",
    ]:
        if forbidden in source:
            raise SystemExit(f"{path} still contains unsafe forwarded IP handling: {forbidden}")

print("API blocked-IP resolution hardening guard is present.")
