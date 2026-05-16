#!/usr/bin/env python3
from pathlib import Path

source = Path("common/components/GoogleMapComponent.php").read_text()

required_snippets = [
    "http_build_query([",
    "'latlng' => $lat . ',' . $lng,",
    "'key' => $this->token,",
    "'language' => 'en',",
    "PHP_QUERY_RFC3986",
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("GoogleMapComponent URL hardening is missing: " + ", ".join(missing))

if "'/geocode/json?latlng='. $lat . ',' . $lng . '&key=' . $this->token" in source:
    raise SystemExit("GoogleMapComponent still concatenates raw geocode query parameters")

print("GoogleMapComponent geocode URL hardening guard is present.")
