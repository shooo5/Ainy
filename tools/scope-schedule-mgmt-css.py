#!/usr/bin/env python3
"""Prefix unscoped selectors in schedule-management-page.css."""

import re
from pathlib import Path

path = Path(__file__).resolve().parents[1] / "assets/css/pages/schedule-management-page.css"
content = path.read_text(encoding="utf-8")

SKIP_PREFIXES = (
    ".page-schedule-management",
    ".tentative-confirm-toast",
    ".center-toast",
)


def should_prefix(selector: str) -> bool:
    sel = selector.strip()
    if not sel or sel.startswith("@"):
        return False
    if sel.startswith(SKIP_PREFIXES):
        return False
    return True


def prefix_selector_list(selector_text: str) -> str:
    parts = re.split(r"\s*,\s*", selector_text)
    out = []
    for part in parts:
        p = part.strip()
        if not p:
            continue
        if should_prefix(p):
            out.append(".page-schedule-management " + p)
        else:
            out.append(p)
    return ", ".join(out)


def repl(m: re.Match) -> str:
    indent, selector = m.group(1), m.group(2)
    if selector.strip().startswith("@"):
        return m.group(0)
    return indent + prefix_selector_list(selector) + " {"


new_content = re.sub(r"(?m)^(\s*)([^{@][^{]*?)\s*\{", repl, content)
path.write_text(new_content, encoding="utf-8", newline="\n")
print(f"Scoped {path.name}")
