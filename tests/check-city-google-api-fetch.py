#!/usr/bin/env python3
from pathlib import Path

source = Path("common/models/City.php").read_text()

required_snippets = [
    "http_build_query([",
    "'key' => Yii::$app->params['google_api_key'],",
    "'location_type' => 'APPROXIMATE',",
    "PHP_QUERY_RFC3986",
    "$responseContent = curl_exec($ch);",
    "if ($responseContent === false)",
    "curl_close($ch);",
    "$response = json_decode($responseContent);",
    "json_last_error() !== JSON_ERROR_NONE",
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("City Google API fetch hardening is missing: " + ", ".join(missing))

for forbidden in [
    "$url .= '&key=' . Yii::$app->params['google_api_key'];",
    "$url .= '&location_type=APPROXIMATE';",
    "$response = json_decode(curl_exec($ch));",
]:
    if forbidden in source:
        raise SystemExit("City Google API fetch still contains unsafe pattern: " + forbidden)

print("City Google API fetch hardening guard is present.")
