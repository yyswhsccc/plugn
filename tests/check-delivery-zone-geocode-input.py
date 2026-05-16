#!/usr/bin/env python3
from pathlib import Path

source = Path("api/modules/v2/controllers/DeliveryZoneController.php").read_text()

required_snippets = [
    "if (!is_numeric($latitude) || !is_numeric($longitude))",
    "'message' => 'Latitude and longitude are invalid'",
    "http_build_query([",
    "'latlng' => $latitude . ',' . $longitude,",
    "PHP_QUERY_RFC3986",
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("Delivery-zone geocode input hardening is missing: " + ", ".join(missing))

if "'https://maps.googleapis.com/maps/api/geocode/json?latlng=' . $latitude .','." in source:
    raise SystemExit("Delivery-zone endpoint still concatenates raw geocode coordinates")

print("Delivery-zone geocode input hardening guard is present.")
