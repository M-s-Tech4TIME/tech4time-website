#!/usr/bin/env python3
"""
Assemble a page from the shared templates plus a per-page <main> block.

Build tool. NOT deployed to the web server (see tools/README.md).

    python3 tools/assemble_page.py <spec.json>

WHY
The project forbids runtime partials, so a page has to be a complete document.
That used to mean pasting one -- the head, the header, the footer, the dock and
the script tags -- and pasting by hand is what makes copies drift.

Almost none of it is pasted any more. The <head> is emitted by lib/head.php
(ADR 0020) and the header, footer and dock by lib/body.php from
content/chrome.json (ADR 0023), both at request time, so there is nothing to
copy and nothing to keep in step. A page written by this tool carries five
calls where it used to carry six hundred lines, and its title, description,
share card, nav link and footer link are all edited at admin.tech4time.bd like
the rest of its content.

What is left for this tool is the skeleton: the doc comment, the requires, the
<head> calls, the <body> calls and the page's own <main>. The only thing it
still reads from tools/templates/ is scripts.html, which every page holds its
own copy of.

IMPORTANT
This is for creating a page, not for maintaining one. Once a page exists, edit
the file directly -- re-running the tool would discard any hand edits made to
its <main>. Changes to the shared blocks are made in tools/templates/ and then
propagated to every page.

SPEC FORMAT (JSON)
    {
      "out":         "pages/about/index.php",   relative to the repo root
      "main":        "/abs/path/to/main.html",  the page's <main> element
      "route":       "/pages/about/",           its SEO_ROUTES route, with slashes
      "document":    "about",                   the content document it renders
      "page_css":    "about",                   optional; assets/css/pages/<name>.css
      "extra_jsonld": "…"                       optional; raw <script> block(s)
    }

There is no "nav_current" here any more either. lib/body.php marks the active
link from the route the page passes it, so there is nothing to place by hand --
and nothing to place on the wrong element, which is what the old propagation
tool did to index.php's logo link.

There is no "title", "description" or "canonical" here any more. The first two
are content and belong in lib/contract.php's defaults for that document, where
the editor can then change them. The canonical is derived from "route" and is
not editable anywhere, by design -- one page, one address.
"""

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TPL = ROOT / "tools" / "templates"


def read(name: str) -> str:
    return (TPL / name).read_text().rstrip("\n")


def up_to_root(out: str) -> str:
    """The relative path from the page back to the repository root.

    pages/about/index.php is two directories down, so its requires read
    __DIR__ . '/../../lib/…'. index.php at the root reads __DIR__ . '/lib/…'.
    """
    depth = len(Path(out).parts) - 1
    return "/" + "../" * depth if depth else "/"


def build(spec: dict) -> str:
    if not spec["out"].endswith(".php"):
        raise SystemExit(
            f"out must be a .php file, not {spec['out']!r}: a page renders its "
            f"head from lib/head.php on the request, so it cannot be static."
        )

    to_root = up_to_root(spec["out"])
    doc = spec["document"]
    route = spec["route"]
    styles = f"['pages/{spec['page_css']}.css']" if spec.get("page_css") else "[]"

    parts = [
        "<?php",
        "/**",
        f" * Tech4TIME — the {doc} page.",
        " *",
        " * PHP, and not HTML, because its content is edited at admin.tech4time.bd",
        f" * and arrives here as content/{doc}.json. Rendered on the server, on this",
        " * request, from a file on this disk: no fetch, no framework, and the page",
        " * works with JavaScript switched off. See ADR 0003 and ADR 0010.",
        " *",
        " * Everything editable goes through h().",
        " *",
        " * The <head> is emitted by lib/head.php and the header, footer and dock",
        " * by lib/body.php, so there is nothing here to keep in step. Only the",
        " * hero circuit is still copied markup, and only on pages with a title",
        " * band -- tools/check_shared_markup.py holds that one byte-identical to",
        " * tools/templates/.",
        " */",
        "",
        "declare(strict_types=1);",
        "",
        # require_once, not require: lib/head.php pulls in lib/contact.php for
        # the Organization graph, so a page whose own model is contact.php
        # would otherwise load it twice and die on the redeclaration. That has
        # happened once already, on the contact page.
        f"require_once __DIR__ . '{to_root}lib/head.php';",
        f"require_once __DIR__ . '{to_root}lib/body.php';",
        f"require_once __DIR__ . '{to_root}lib/{doc}.php';",
        "",
        f"$data = {doc}_load();",
        "?>",
        "<!DOCTYPE html>",
        '<html lang="<?= h(seo_lang()) ?>">',
        "<head>",
        f"<?php seo_head('{route}', $data['meta'], {styles}, $data['updated']); ?>",
        f"<?php seo_jsonld('{route}', $data['meta'], $data['updated']); ?>",
    ]

    if spec.get("extra_jsonld"):
        parts += ["", spec["extra_jsonld"].rstrip("\n")]

    parts += [
        "</head>",
        "",
        '<body class="page">',
        # No icon-sprite block: tools/inject_icons.py writes one if the page's
        # own markup references an icon, and leaves none if it does not. The
        # chrome's symbols are emitted by body_header().
        f"<?php body_header('{route}'); ?>",
        "",
        Path(spec["main"]).read_text().rstrip("\n"),
        "",
        f"<?php body_footer(); ?>",
        "",
        f"<?php body_dock('{route}'); ?>",
        "",
        read("scripts.html"),
        "</body>",
        "</html>",
        "",
    ]
    return "\n".join(parts)


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit(__doc__)

    spec = json.loads(Path(sys.argv[1]).read_text())
    out = ROOT / spec["out"]
    out.parent.mkdir(parents=True, exist_ok=True)

    if out.exists():
        print(f"NOTE: overwriting existing {spec['out']} — hand edits to its <main> are lost.")

    out.write_text(build(spec))
    print(f"wrote {spec['out']}  ({out.stat().st_size:,} bytes)")

    # The page renders before any of this is done -- with the wrong title, no
    # sitemap entry and no way to edit either. None of it fails loudly, so it
    # is printed rather than left to be remembered.
    print()
    print("The page is written. It is not finished until all four are done:")
    print()
    print(f"  1. lib/contract.php   add the '{spec['document']}' document, with a meta")
    print( "                        band -- title, description, breadcrumb, robots,")
    print( "                        changefreq, priority. Copy to the backend.")
    print(f"  2. lib/contract.php   add SEO_ROUTES['{spec['document']}']:")
    print(f"                        ['{spec['route']}', '<rail label>', '{spec['document']}'].")
    print( "                        Without it the page has no canonical, no sitemap")
    print( "                        entry and no card on the SEO screen.")
    print(f"  3. lib/{spec['document']}.php".ljust(24) + f"{spec['document']}_load(), like the others.")
    print( "  4. the four page globs   tools/inject_icons.py, check_shared_markup.py,")
    print( "                        propagate_shared.py, audit_pages.py -- only if this")
    print( "                        page sits at the repository root.")
    print()
    print("then: python3 tools/inject_icons.py && python3 tools/audit_pages.py")


if __name__ == "__main__":
    main()
