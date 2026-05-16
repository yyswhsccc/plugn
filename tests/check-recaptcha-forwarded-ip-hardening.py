#!/usr/bin/env python3
from pathlib import Path

source = Path("common/components/ReCaptcha.php").read_text()

required_snippets = [
    "trim((string) Yii::$app->request->getRemoteIP())",
    "array_map('trim', explode(',', $forwardedFor))",
    "if (!empty($IParray))",
    "filter_var($ip, FILTER_VALIDATE_IP) !== false",
    '$data["remoteip"] = $ip;',
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("ReCaptcha forwarded IP hardening is missing: " + ", ".join(missing))

for forbidden in [
    "array_values(array_filter(explode(',', $forwardedFor)))",
    '"remoteip" => $ip',
    "$ip = $IParray[0];\n        }",
]:
    if forbidden in source:
        raise SystemExit("ReCaptcha still contains unsafe forwarded IP handling: " + forbidden)

print("ReCaptcha forwarded IP hardening guard is present.")
