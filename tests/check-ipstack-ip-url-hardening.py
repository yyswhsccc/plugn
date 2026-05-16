#!/usr/bin/env python3
from pathlib import Path

source = Path("common/components/Ipstack.php").read_text()

required_snippets = [
    "trim((string) Yii::$app->request->getRemoteIP())",
    "array_map('trim', explode(',', $forwardedFor))",
    "if (!empty($IParray))",
    "filter_var($ip, FILTER_VALIDATE_IP) === false",
    "rawurlencode($ip)",
    "http_build_query([",
    "'token' => $this->accessKey,",
    "PHP_QUERY_RFC3986",
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("Ipstack IP URL hardening is missing: " + ", ".join(missing))

for forbidden in [
    "'https://ipinfo.io/' . $ip . '/json?token=' . $this->accessKey",
    "'https://ipinfo.io/json?token=' . $this->accessKey",
]:
    if forbidden in source:
        raise SystemExit("Ipstack still builds an unsafe ipinfo URL: " + forbidden)

print("Ipstack IP URL hardening guard is present.")
