#!/usr/bin/env python3

from pathlib import Path


repo_root = Path(__file__).resolve().parents[1]
component_path = repo_root / "common" / "components" / "NetlifyComponent.php"
source = component_path.read_text()

method_start = source.find("public function listSiteData(")
if method_start == -1:
    raise SystemExit("Could not find NetlifyComponent::listSiteData().")

brace_start = source.find("{", method_start)
if brace_start == -1:
    raise SystemExit("Could not find NetlifyComponent::listSiteData() body.")

depth = 0
method_end = None
for index in range(brace_start, len(source)):
    if source[index] == "{":
        depth += 1
    elif source[index] == "}":
        depth -= 1
        if depth == 0:
            method_end = index
            break

if method_end is None:
    raise SystemExit("Could not parse NetlifyComponent::listSiteData() body.")

list_site_data = source[brace_start:method_end + 1]

if "http_build_query($queryParams" not in list_site_data:
    raise SystemExit("NetlifyComponent::listSiteData must build query strings with http_build_query().")

if "PHP_QUERY_RFC3986" not in list_site_data:
    raise SystemExit("Netlify query parameters must use RFC3986 encoding.")

if "'page' => $page" not in list_site_data:
    raise SystemExit("Netlify site list pagination must keep an explicit page= parameter.")

if "$queryParams['name'] = $query" not in list_site_data:
    raise SystemExit("Netlify site list search must pass the name query through encoded parameters.")

if "trim((string) $query)" not in list_site_data or "if ($query !== '')" not in list_site_data:
    raise SystemExit("Netlify site list search must omit empty name filters.")

bad_patterns = [
    '"/sites?per_page=2&page" . $page',
    '"&name=" . $query',
    "'&name=' . $query",
]

for pattern in bad_patterns:
    if pattern in source:
        raise SystemExit(f"Unsafe Netlify query concatenation is still present: {pattern}")

print("Netlify site-list query construction is encoded and preserves page=.")
