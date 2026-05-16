#!/usr/bin/env python3

from pathlib import Path


repo_root = Path(__file__).resolve().parents[1]
component_path = repo_root / "common" / "components" / "BlogManager.php"
source = component_path.read_text()

if source.count("http_build_query([") < 2:
    raise SystemExit("BlogManager list methods must build query strings with http_build_query().")

if source.count("PHP_QUERY_RFC3986") < 2:
    raise SystemExit("BlogManager list queries must use RFC3986 encoding.")

for required in ["'limit' => $limit", "'page' => $page", "'query' => $query"]:
    if source.count(required) < 2:
        raise SystemExit(f"BlogManager list methods must include encoded {required} parameters.")

bad_patterns = [
    '"/post?limit=".$limit."&page=" . $page',
    '"/category?limit=".$limit."&page=" . $page',
    "'&query=' . $query",
    '"&query=" . $query',
]

for pattern in bad_patterns:
    if pattern in source:
        raise SystemExit(f"Unsafe BlogManager query concatenation is still present: {pattern}")

print("BlogManager post/category listing queries are encoded.")
