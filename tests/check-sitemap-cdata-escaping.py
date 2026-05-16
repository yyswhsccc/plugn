#!/usr/bin/env python3

from pathlib import Path


SITEMAP_VIEW = Path("api/modules/v2/views/sitemap/index.php")


def cdata(value: str) -> str:
    return "<![CDATA[" + value.replace("]]>", "]]]]><![CDATA[>") + "]]>"


def main() -> None:
    source = SITEMAP_VIEW.read_text()

    assert "function sitemapCdata($value): string" in source
    assert "str_replace(']]>', ']]]]><![CDATA[>', (string) $value)" in source
    assert "<![CDATA[<?= $" not in source
    assert "<![CDATA[<?php" not in source

    expected_calls = [
        "sitemapCdata($restaurant->restaurant_domain)",
        "sitemapCdata($category->slug",
        "sitemapCdata($product->slug",
        "sitemapCdata($restaurant->restaurant_domain . '/order-status')",
    ]
    for call in expected_calls:
        assert call in source, f"missing sitemap CDATA wrapper: {call}"

    sample = "https://example.test/category/a]]>b"
    escaped = cdata(sample)
    assert escaped == "<![CDATA[https://example.test/category/a]]]]><![CDATA[>b]]>"
    assert "a]]>b" not in escaped


if __name__ == "__main__":
    main()
