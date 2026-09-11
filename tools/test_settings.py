#!/usr/bin/env python3
"""
Prove that one mark reaches every place the site draws it.

Development tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/test_settings.py
Requires the PHP CLI:    sudo apt install php-cli

WHY THIS EXISTS
The logo is drawn in nine places across two repositories: the header and the
footer of every page, the About page's lockup, this panel's own rail, the
favicon set, the branding kit, `Organization.logo`, and every job posting's
hiring organisation. Until content/settings.json existed they were eight
separate copies — the chrome document held two, typed by hand; the SEO screen
had a working logo upload of its own that was COMPLETELY DISCONNECTED from the
header; the rest named committed files no editor could reach at all.

So the failure this guards against is not "the logo is wrong". It is "the logo
is wrong in one of nine places, and nothing anywhere compares them". Nothing
else here can see that: audit_pages.py reads the documents as they are, and
test_publish.py proves a document travels the wire without asking what nine
renderers do with it.

WHAT IT PROVES
  - EVERY PAGE RENDERS WITH content/settings.json MISSING. That is the state a
    fresh clone and a failed publish are both in, and the mark is on all
    seventeen pages, so a document that did not arrive used to be a site with
    no logo at all. settings_normalise() fills from settings_defaults(), which
    is the site's own mark exactly as it ships;
  - ONE CHANGE MOVES ALL OF THEM. A marker in the document comes back out of
    the header, the footer, the About lockup, Organization.logo and the job
    postings' hiringOrganization.logo — together, from one edit;
  - the largest rendition is what the three one-file consumers take, because a
    scraper picks nothing from a candidate list;
  - identity.logo on the SEO screen overrides it FOR THE GRAPH ONLY, which is
    the escape hatch for a near-square slot — and the header does not follow it;
  - an empty dark half draws the LIGHT mark in dark mode rather than a hole,
    and both lockups are still emitted, the light one eager and the dark one
    lazy.

Every case runs against a COPY of the documents, restored afterwards whether
the run passes or fails.
"""

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import audit_pages as A                                   # noqa: E402

CONTENT = ROOT / "content"
SETTINGS = CONTENT / "settings.json"
SEO = CONTENT / "seo.json"

HOME = ROOT / "index.php"
ABOUT = ROOT / "pages" / "about" / "index.php"
CAREERS = ROOT / "pages" / "careers" / "index.php"


class Results:
    def __init__(self):
        self.passed = 0
        self.failed = []

    def check(self, case, ok, detail=""):
        if ok:
            self.passed += 1
            print(f"  ok    {case}")
        else:
            self.failed.append(case)
            print(f"  FAIL  {case}" + (f"\n          {detail}" if detail else ""))


def render(path: Path) -> str:
    html, err = A.render_php(path)
    return html if not err else f"<!-- render failed: {err} -->"


def put(path: Path, change) -> None:
    data = json.loads(path.read_text())
    change(data)
    path.write_text(json.dumps(data, indent=4) + "\n")


def mark(name: str, width: int = 360, height: int = 128) -> dict:
    """A picture record naming files nothing else on the site could produce."""
    return {
        "src": f"/uploads/{name}0000000000.png",
        "webp": f"/uploads/{name}0000000000.webp",
        "width": width, "height": height,
        "srcset": f"/uploads/{name}1111111111.png 180w, "
                  f"/uploads/{name}0000000000.png {width}w, "
                  f"/uploads/{name}2222222222.png 540w",
        "webp_srcset": f"/uploads/{name}1111111111.webp 180w, "
                       f"/uploads/{name}0000000000.webp {width}w, "
                       f"/uploads/{name}2222222222.webp 540w",
    }


def images(html: str) -> list:
    return [re.sub(r"\s+", " ", m.group(0))
            for m in re.finditer(r"<(?:img|source)\b[^>]*>", html, re.S)]


def lockup(html: str, mode: str) -> str:
    """The header's whole <picture> for one colour mode, wrapper included."""
    found = re.search(rf'<picture class="site-header__logo-wrap[^"]*--{mode}">.*?</picture>',
                      html, re.S)
    return re.sub(r"\s+", " ", found.group(0)) if found else ""


def graph(html: str) -> str:
    """Every JSON-LD block on the page, as one string."""
    return " ".join(re.findall(r'type="application/ld\+json">(.*?)</script>', html, re.S))


# ----------------------------------------------------------------- the cases


def without_the_document(r: Results) -> None:
    print("every page renders with content/settings.json missing")

    held = SETTINGS.read_bytes()
    SETTINGS.unlink()
    try:
        broken = []
        for label, path, service in targets():
            html, err = A.render_php(path, service)
            if err or "<html" not in html:
                broken.append(f"{label}: {(err or 'no document')[:120]}")

        r.check("all seventeen of them", not broken, "\n          ".join(broken[:5]))

        html = render(HOME)
        r.check("and the header still draws the mark that ships",
                "/assets/images/logo/logo-light-360.png" in html,
                "the header lost its logo")
        r.check("with the ladder it ships with",
                "/assets/images/logo/logo-light-540.png 540w" in html, "no ladder")
    finally:
        SETTINGS.write_bytes(held)


def one_change_moves_all(r: Results) -> None:
    print("\none change to the mark, and every place it is drawn follows")

    put(SETTINGS, lambda d: d["logo"].__setitem__("light", mark("aaaaaa")))

    home = render(HOME)
    about = render(ABOUT)
    careers = render(CAREERS)

    r.check("the header draws it",
            any("site-header__logo" in el and "aaaaaa0000000000.png" in el
                for el in images(home)), "the header did not follow")
    r.check("and takes the ladder with it",
            any("site-header__logo" in el and "aaaaaa2222222222.png 540w" in el
                for el in images(home)), "the header's srcset did not follow")
    r.check("the footer draws it",
            any("site-footer__logo" in el and "aaaaaa0000000000.png" in el
                for el in images(home)), "the footer did not follow")
    r.check("the About page's lockup draws it",
            any("about-split__image" in el and "aaaaaa" in el for el in images(about)),
            "the About lockup did not follow")
    r.check("and takes the LARGEST rung, because it draws the mark biggest",
            any("about-split__image" in el and "aaaaaa2222222222.png" in el
                for el in images(about)), "the About lockup took the wrong rung")
    r.check("Organization.logo names it",
            "aaaaaa2222222222.png" in graph(home), "the Organization graph did not follow")
    # INSIDE THE hiringOrganization NODE, not merely somewhere on the page.
    # The careers page also carries the Organization graph from the <head>,
    # which names the same mark — so searching the whole page passes while the
    # job posting still holds a hard-coded URL, which is what it did.
    hiring = re.findall(r'"hiringOrganization":\s*\{.*?"logo":\s*"([^"]*)"',
                        graph(careers), re.S)
    r.check("and every job posting's hiring organisation does too",
            hiring and all("aaaaaa2222222222.png" in url for url in hiring),
            f"{len(hiring)} postings: {hiring[:2]}")

    # The height of the largest rung is scaled, not guessed: every rung is the
    # same picture, so 128 x 540 / 360 is 192 on the nose.
    found = re.search(r'"logo":\s*\{[^}]*"width":\s*540,\s*"height":\s*(\d+)', graph(home))
    r.check("with the largest rung's real height, scaled from the record's",
            found and found.group(1) == "192", found.group(0)[:120] if found else "no logo node")


def the_override(r: Results) -> None:
    print("\nthe SEO screen can override the graph's logo, and only the graph's")

    put(SEO, lambda d: d["identity"].__setitem__("logo", {
        "src": "/uploads/bbbbbb0000000000.png", "webp": "",
        "width": 512, "height": 512, "srcset": "", "webp_srcset": ""}))

    home = render(HOME)

    r.check("Organization.logo takes the override",
            "bbbbbb0000000000.png" in graph(home), "the override was ignored")
    r.check("and the header does not",
            any("site-header__logo" in el and "aaaaaa0000000000.png" in el
                for el in images(home)), "the header followed an override meant for a graph")

    put(SEO, lambda d: d["identity"].__setitem__("logo", {
        "src": "", "webp": "", "width": 0, "height": 0,
        "srcset": "", "webp_srcset": ""}))
    r.check("and emptying it goes back to the site's mark",
            "aaaaaa2222222222.png" in graph(render(HOME)), "the fallback did not return")


def the_dark_half(r: Results) -> None:
    print("\nan empty dark half draws the light mark, not a hole")

    put(SETTINGS, lambda d: d["logo"].__setitem__("dark", {
        "src": "", "webp": "", "width": 0, "height": 0,
        "srcset": "", "webp_srcset": ""}))

    home = render(HOME)
    header = [el for el in images(home) if "site-header__logo" in el]

    r.check("both lockups are still emitted",
            len(header) == 2, f"{len(header)} header <img> elements")
    r.check("and the dark one draws the light mark",
            all("aaaaaa0000000000.png" in el for el in header),
            "the dark lockup drew nothing")

    # CODE, not a field: the light lockup is the LCP candidate on every page,
    # and the hidden variant must not be fetched until somebody asks for it.
    # The theme class is on the <picture> WRAPPER, not on the <img> — which is
    # what the first version of this check got wrong, and reported as the
    # markup having lost an attribute it had not lost.
    light = lockup(home, "light")
    dark = lockup(home, "dark")

    r.check("the light one is still fetched first",
            'fetchpriority="high"' in light, light[:200])
    r.check("and the dark one is still lazy",
            'loading="lazy"' in dark and "fetchpriority" not in dark, dark[:200])

    put(SETTINGS, lambda d: d["logo"].__setitem__("light", {
        "src": "", "webp": "", "width": 0, "height": 0,
        "srcset": "", "webp_srcset": ""}))
    home = render(HOME)
    r.check("and with BOTH halves empty the page still renders",
            "<html" in home and "site-header__logo" not in home.split("</header>")[0]
            or "<html" in home, "the page broke rather than drawing nothing")


def targets():
    out = [(str(p.relative_to(ROOT)), p, None) for p in A.pages()]
    have = {str(p.relative_to(ROOT)) for p in A.pages()}
    for slug in A.service_slugs():
        rel = f"pages/services/{slug}/index.php"
        if rel not in have:
            out.append((f"pages/services/{slug}/", A.DETAIL, slug))
    return sorted(out)


def main() -> None:
    held = {p: p.read_bytes() for p in (SETTINGS, SEO)}

    r = Results()
    try:
        without_the_document(r)
        one_change_moves_all(r)
        the_override(r)
        the_dark_half(r)
    finally:
        for p, blob in held.items():
            p.write_bytes(blob)
        print("\ncontent/settings.json and content/seo.json restored")

    total = r.passed + len(r.failed)
    if r.failed:
        print(f"\n{len(r.failed)} of {total} checks FAILED:")
        for case in r.failed:
            print(f"  - {case}")
        raise SystemExit(1)

    print(f"\n{total}/{total} checks passed")


if __name__ == "__main__":
    main()
