#!/usr/bin/env python3
"""
Build the web font files for the RB brand family.

Drop the three licensed originals into assets/fonts/src/ - any of .ttf, .otf,
.woff or .woff2 - then run:

    python tools/build-fonts.py

Each source is matched to a weight by its filename (light / regular / bold) and
written to assets/fonts/ as rb-<weight>.woff2 and rb-<weight>.woff, which is what
assets/css/fonts.css asks for. Sources are never modified.

Needs fonttools with Brotli support:  pip install "fonttools[woff]"
"""

from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC_DIR = ROOT / "assets" / "fonts" / "src"
OUT_DIR = ROOT / "assets" / "fonts"

SOURCE_TYPES = {".ttf", ".otf", ".woff", ".woff2"}

# Ordered longest-first so "semibold" can never be mistaken for "bold" and
# "extralight" can never be mistaken for "light".
WEIGHTS = [
    ("bold", ["extrabold", "semibold", "demibold", "bold"]),
    ("light", ["extralight", "ultralight", "light"]),
    ("regular", ["regular", "normal", "book"]),
]
UNWANTED = {"extrabold", "semibold", "demibold", "extralight", "ultralight"}


def classify(name: str) -> tuple[str, str] | None:
    """Return (weight, matched token) for a filename, or None if unrecognised."""
    flat = name.lower().replace("_", "").replace("-", "").replace(" ", "")
    for weight, tokens in WEIGHTS:
        for token in tokens:
            if token in flat:
                return weight, token
    return None


def main() -> int:
    try:
        from fontTools.ttLib import TTFont
    except ImportError:
        print('fonttools is missing. Install it with:  pip install "fonttools[woff]"')
        return 1

    try:
        import brotli  # noqa: F401  - required by fonttools to write woff2
    except ImportError:
        print('Brotli is missing, so woff2 cannot be written. Run:  pip install "fonttools[woff]"')
        return 1

    if not SRC_DIR.is_dir():
        print(f"Missing source folder: {SRC_DIR}")
        return 1

    sources = sorted(p for p in SRC_DIR.iterdir() if p.suffix.lower() in SOURCE_TYPES)
    if not sources:
        print(f"No font files in {SRC_DIR}. Put the RB Light, Regular and Bold files there.")
        return 1

    claimed: dict[str, Path] = {}
    built = 0

    for source in sources:
        match = classify(source.stem)
        if match is None:
            print(f"skip   {source.name}  (no light/regular/bold in the filename)")
            continue

        weight, token = match
        if token in UNWANTED:
            print(f"skip   {source.name}  ('{token}' is not one of the three weights)")
            continue

        if weight in claimed:
            print(f"skip   {source.name}  ({weight} already built from {claimed[weight].name})")
            continue
        claimed[weight] = source

        for flavor in ("woff2", "woff"):
            target = OUT_DIR / f"rb-{weight}.{flavor}"
            # Reopen per flavour: saving stamps the flavour onto the loaded font.
            font = TTFont(str(source))
            font.flavor = flavor
            font.save(str(target))
            font.close()
            print(f"build  {source.name}  ->  {target.name}  ({target.stat().st_size / 1024:.0f} KB)")
            built += 1

    missing = [w for w, _ in WEIGHTS if w not in claimed]
    if missing:
        print("\nStill missing: " + ", ".join(sorted(missing)))
        print("Those weights will fall back to the nearest one that did build.")
        return 1

    print(f"\nDone. {built} file(s) written to assets/fonts/.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
