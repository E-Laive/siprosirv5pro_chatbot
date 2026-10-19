#!/usr/bin/env python3
"""Read-only heuristic: list possible inline color literals in project source.

Use findings as prompts for manual review, not as automatic violations.
Usage: python audit_style_literals.py /path/to/project --limit 80
"""

from __future__ import annotations

import argparse
import os
import re
import sys
from pathlib import Path

EXTENSIONS = {".css", ".scss", ".less", ".vue", ".svelte", ".jsx", ".tsx", ".html", ".dart", ".kt", ".xml"}
IGNORE_PARTS = {"node_modules", ".git", ".agents", "vendor", "dist", "build", "coverage", "bin", "obj", ".next", ".nuxt", ".dart_tool", "Pods"}
LITERAL = re.compile(r"(?<![a-zA-Z0-9])(?:#(?:[a-fA-F0-9]{8}|[a-fA-F0-9]{6}|[a-fA-F0-9]{4}|[a-fA-F0-9]{3})(?![a-fA-F0-9])|(?:rgb|hsl)a?\s*\()", re.IGNORECASE)


def findings(root: Path, limit: int = 100) -> list[tuple[Path, int, str]]:
    issues: list[tuple[Path, int, str]] = []
    if not root.exists() or not root.is_dir():
        raise ValueError(f"Not a directory: {root}")
    for current_dir, subdirs, filenames in os.walk(root):
        # Prune dependency/generated directories before traversing them.
        subdirs[:] = sorted(name for name in subdirs if name not in IGNORE_PARTS)
        parent = Path(current_dir)
        for filename in sorted(filenames):
            candidate = parent / filename
            if candidate.suffix.lower() not in EXTENSIONS:
                continue
            try:
                if candidate.stat().st_size > 1_000_000:
                    continue
                content = candidate.read_text(encoding="utf-8")
            except (UnicodeError, OSError):
                continue
            for number, line in enumerate(content.splitlines(), start=1):
                if LITERAL.search(line):
                    issues.append((candidate.relative_to(root), number, line.strip()[:200]))
                    if len(issues) >= limit:
                        return issues
    return issues
    return issues


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("project_root", type=Path)
    parser.add_argument("--limit", type=int, default=100)
    args = parser.parse_args()
    if args.limit < 1:
        parser.error("--limit must be >= 1")
    try:
        result = findings(args.project_root, args.limit)
    except (ValueError, OSError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1
    for relative_path, lineno, excerpt in result:
        print(f"{relative_path}:{lineno}: {excerpt}")
    print(f"Potential literal occurrences: {len(result)} (read-only heuristic; not proof of a defect)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
