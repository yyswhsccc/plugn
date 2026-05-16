#!/usr/bin/env python3
from pathlib import Path

source = Path("common/components/JWT.php").read_text()

required_snippets = [
    "$responseContent = curl_exec($ch);",
    "if ($responseContent === false)",
    "Yii::error('Unable to fetch Apple public keys: ' . curl_error($ch), __METHOD__);",
    "curl_close($ch);",
    "$response = json_decode($responseContent);",
    "json_last_error() !== JSON_ERROR_NONE",
    "!isset($response->keys)",
    "!is_array($response->keys)",
    "Invalid Apple public keys response.",
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("Apple JWK fetch hardening is missing: " + ", ".join(missing))

if "json_decode(curl_exec($ch))" in source:
    raise SystemExit("Apple JWK fetch still decodes curl_exec directly")

print("Apple JWK fetch hardening guard is present.")
