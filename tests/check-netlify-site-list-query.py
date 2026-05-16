#!/usr/bin/env python3

from pathlib import Path


repo_root = Path(__file__).resolve().parents[1]
component_path = repo_root / "common" / "components" / "NetlifyComponent.php"
source = component_path.read_text()

if "http_build_query([" not in source:
    raise SystemExit("NetlifyComponent::listSiteData must build query strings with http_build_query().")

if "PHP_QUERY_RFC3986" not in source:
    raise SystemExit("Netlify query parameters must use RFC3986 encoding.")

if "'page' => $page" not in source:
    raise SystemExit("Netlify site list pagination must keep an explicit page= parameter.")

if "'name' => $query" not in source:
    raise SystemExit("Netlify site list search must pass the name query through encoded parameters.")

bad_patterns = [
    '"/sites?per_page=2&page" . $page',
    '"&name=" . $query',
    "'&name=' . $query",
]

for pattern in bad_patterns:
    if pattern in source:
        raise SystemExit(f"Unsafe Netlify query concatenation is still present: {pattern}")

print("Netlify site-list query construction is encoded and preserves page=.")
