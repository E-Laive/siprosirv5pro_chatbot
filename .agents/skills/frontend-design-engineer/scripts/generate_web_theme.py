#!/usr/bin/env python3
"""Generate Web semantic CSS custom properties from a canonical JSON token file.

This optional, standard-library-only example is intentionally narrow. It emits
style tokens, not a runtime ThemeProvider or persisted setting. Never overwrite
project styles without reviewing the diff. Usage:
  python generate_web_theme.py assets/theme-token-examples/tokens.example.json output.css
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

TOKEN_NAME = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")
COLOR = re.compile(r"^#[0-9a-fA-F]{6}$")


class TokenError(ValueError):
    pass


def check_name(name: str) -> str:
    if not isinstance(name, str) or not TOKEN_NAME.fullmatch(name):
        raise TokenError(f"Invalid token name: {name!r}")
    return name


def check_value(value: object) -> str:
    if not isinstance(value, (str, int, float)):
        raise TokenError(f"Invalid token value: {value!r}")
    result = str(value)
    if not result or any(char in result for char in ("{", "}", ";", "\n", "\r")):
        raise TokenError(f"Unsafe or empty CSS value: {value!r}")
    return result


def collect_tokens(tokens: dict) -> tuple[dict, dict, dict]:
    try:
        light = tokens["themes"]["light"]["color"]
        dark = tokens["themes"]["dark"]["color"]
    except (KeyError, TypeError) as exc:
        raise TokenError("Required themes.light.color and themes.dark.color maps") from exc
    if not isinstance(light, dict) or not isinstance(dark, dict):
        raise TokenError("Both theme color sections must be objects")
    if set(light) != set(dark):
        raise TokenError("Light and dark semantic color token keys must match")
    if not light:
        raise TokenError("Color token sets must not be empty")
    light_out: dict[str, str] = {}
    dark_out: dict[str, str] = {}
    for key in sorted(light):
        check_name(key)
        for original, target in ((light, light_out), (dark, dark_out)):
            value = check_value(original[key])
            if not COLOR.fullmatch(value):
                raise TokenError(f"Expected #RRGGBB for color.{key}: {value!r}")
            target[f"color-{key}"] = value

    common: dict[str, str] = {}
    categories = {
        "typography": {"family": "font-family", "size": "font-size", "weight": "font-weight", "line-height": "line-height"},
        "space": "space",
        "radius": "radius",
    }
    typography = tokens.get("typography", {})
    if not isinstance(typography, dict):
        raise TokenError("typography must be an object")
    for group, prefix in categories["typography"].items():
        values = typography.get(group, {})
        if not isinstance(values, dict):
            raise TokenError(f"typography.{group} must be an object")
        for name, value in values.items():
            common[f"{prefix}-{check_name(name)}"] = check_value(value)
    for group in ("space", "radius"):
        values = tokens.get(group, {})
        if not isinstance(values, dict):
            raise TokenError(f"{group} must be an object")
        for name, value in values.items():
            common[f"{group}-{check_name(name)}"] = check_value(value)
    return light_out, dark_out, common


def rule(selector: str, values: dict[str, str], *, scheme: str | None = None, indent: str = "") -> list[str]:
    result = [f"{indent}{selector} {{"]
    if scheme:
        result.append(f"{indent}  color-scheme: {scheme};")
    for name in sorted(values):
        result.append(f"{indent}  --{name}: {values[name]};")
    result.append(f"{indent}}}")
    return result


def render(tokens: dict) -> str:
    light, dark, common = collect_tokens(tokens)
    lines = ["/* GENERATED from the canonical token source. Edit JSON and regenerate. */", ""]
    lines.extend(rule(":root, [data-theme=\"light\"], [data-theme=\"system\"]", {**common, **light}, scheme="light"))
    lines.append("")
    lines.extend(rule("[data-theme=\"dark\"]", dark, scheme="dark"))
    lines.extend(["", "@media (prefers-color-scheme: dark) {"])
    lines.extend(rule("[data-theme=\"system\"]", dark, scheme="dark", indent="  "))
    lines.extend(["}", ""])
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("tokens_json", type=Path)
    parser.add_argument("output_css", type=Path)
    args = parser.parse_args()
    if args.tokens_json.resolve() == args.output_css.resolve():
        parser.error("Input and output must be different files")
    if args.output_css.exists():
        parser.error(f"Refusing to overwrite existing file: {args.output_css}")
    try:
        tokens = json.loads(args.tokens_json.read_text(encoding="utf-8"))
        if not isinstance(tokens, dict):
            raise TokenError("Root must be a JSON object")
        css = render(tokens)
    except (OSError, json.JSONDecodeError, TokenError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1
    args.output_css.parent.mkdir(parents=True, exist_ok=True)
    args.output_css.write_text(css, encoding="utf-8", newline="\n")
    print(f"Wrote {args.output_css}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
