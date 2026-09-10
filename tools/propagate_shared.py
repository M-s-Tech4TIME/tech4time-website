#!/usr/bin/env python3
"""
Push a change in tools/templates/ out to every page.

Build tool. NOT deployed to the web server (see tools/README.md).

    python3 tools/propagate_shared.py            # write the changes
    python3 tools/propagate_shared.py --dry-run  # list what would change

WHAT IS LEFT FOR IT TO DO, WHICH IS NOT MUCH
This tool existed for the header, footer and dock: 6,800 lines of markup
duplicated across seventeen page files, which it copied and
tools/check_shared_markup.py policed. Those three are lib/body.php now,
rendering from content/chrome.json, and one copy needs no propagating. ADR
0023.

ONE BLOCK STILL WANTS IT. The hero circuit is decoration around a page title:
about sixty lines of SVG path data, aria-hidden, with no text in it, nothing
editable about it and no reason for anybody to open a form to change a
coordinate. It is not content, so it did not become a document; it is still
literal, so it can still drift, and this is still what puts it back in step.

THE THING THAT USED TO MAKE THIS DELICATE IS GONE. aria-current="page" was the
one legitimate per-page difference in shared markup, so a blind copy would have
wiped it from every page and marked the active link nowhere -- this tool read
it out of each page first and re-applied it afterwards. It re-applied it to
EVERY <a> whose href was in the marked set, which is why index.php sent
aria-current on its logo link as well as its Home nav link, a defect nobody
designed. lib/body.php decides that now, from the route the page passes, and
applies it to one nav link and never to the brand. The circuit has no links at
all.
"""

import argparse
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TEMPLATES = ROOT / "tools" / "templates"

# name -> (template file, regex for the block in a page, anchor to insert
#          before when the page does not have the block yet, optional)
#
# "optional" means a page without the anchor simply does not have that block
# and is not missing anything: the circuit belongs to the page-title band, and
# the home page and the 404 do not have one.
BLOCKS = {
    "hero-circuit": (
        "hero-circuit.html",
        re.compile(r"<!--hero-circuit:start-->.*?<!--hero-circuit:end-->", re.S),
        re.compile(r'<div class="container page-hero__inner">'),
        True,
    ),
}


def pages() -> list[Path]:
    return sorted(
        list(ROOT.glob("*.html"))
        # index.php is the home page and 404.php the error page. Named rather
        # than globbed as "*.php": the root also holds contact-handler.php and
        # sitemap.php, endpoints with no <body> of their own, and a broad glob
        # pastes a decorative circuit straight into them.
        + list(ROOT.glob("index.php"))
        + list(ROOT.glob("404.php"))
        + list(ROOT.glob("pages/**/*.html"))
        + list(ROOT.glob("pages/**/*.php"))
    )


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    canonical = {}
    for name, (filename, _, _, _) in BLOCKS.items():
        path = TEMPLATES / filename
        if not path.exists():
            raise SystemExit(f"Missing template: {path}")
        canonical[name] = path.read_text().strip("\n")

    files = pages()
    if not files:
        raise SystemExit("No pages found.")

    changed = 0
    for page in files:
        original = page.read_text()
        markup = original
        notes = []

        for name, (_, pattern, anchor, optional) in BLOCKS.items():
            found = pattern.search(markup)
            replacement = canonical[name]

            if found:
                new = pattern.sub(lambda _m: replacement, markup, count=1)
                if new != markup:
                    notes.append(name)
                markup = new
                continue

            if anchor is None:
                notes.append(f"{name}: MISSING and no anchor to insert at")
                continue

            spot = anchor.search(markup)
            if not spot:
                if not optional:
                    notes.append(f"{name}: MISSING and anchor not found")
                continue
            at = spot.start()
            markup = markup[:at] + replacement + "\n\n" + markup[at:]
            notes.append(f"{name} (inserted)")

        rel = page.relative_to(ROOT)
        if markup == original:
            continue

        changed += 1
        print(f"  {rel}  —  {', '.join(notes)}")
        if not args.dry_run:
            page.write_text(markup)

    verb = "would change" if args.dry_run else "updated"
    print(f"\n{changed} of {len(files)} pages {verb}.")
    if not args.dry_run and changed:
        print("Now run: python3 tools/check_shared_markup.py")


if __name__ == "__main__":
    main()
