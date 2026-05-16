#!/usr/bin/env python3
from pathlib import Path

source = Path("common/models/Currency.php").read_text()

required_snippets = [
    "http_build_query([",
    "'access_key' => $api_key,",
    "'source' => 'USD',",
    "PHP_QUERY_RFC3986",
    "file_get_contents('https://apilayer.net/api/live?' . $query)",
    "if ($response === false)",
    "json_last_error() !== JSON_ERROR_NONE",
    "isset($data->success) && $data->success === false",
    "CurrencyLayer returned an error while updating rates.",
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("Currency API fetch hardening is missing: " + ", ".join(missing))

for forbidden in [
    "file_get_contents('http://apilayer.net/api/live?access_key=' . $api_key . '&source=USD')",
    "Yii::error($api_key",
]:
    if forbidden in source:
        raise SystemExit("Currency API fetch still contains unsafe pattern: " + forbidden)

print("Currency API fetch hardening guard is present.")
