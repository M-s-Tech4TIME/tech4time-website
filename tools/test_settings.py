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
import struct
import subprocess
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


def served(path: str) -> bytes:
    """What a generated file sends, run the way the router runs it."""
    out = subprocess.run(
        ["php", "-r", '$_SERVER["REQUEST_METHOD"]="GET"; ob_start();'
                      f' include {json.dumps(str(ROOT / path))}; echo ob_get_clean();'],
        cwd=ROOT, capture_output=True)
    return out.stdout


def icons(html: str) -> dict:
    """Every favicon <link> on the page, by the size it declares."""
    out = {}
    for tag in re.findall(r'<link rel="(?:icon|apple-touch-icon)"[^>]*>', html):
        size = re.search(r'sizes="([^"]*)"', tag)
        href = re.search(r'href="([^"]*)"', tag)
        if size and href:
            out[size.group(1)] = href.group(1)
    return out


def targets():
    out = [(str(p.relative_to(ROOT)), p, None) for p in A.pages()]
    have = {str(p.relative_to(ROOT)) for p in A.pages()}
    for slug in A.service_slugs():
        rel = f"pages/services/{slug}/index.php"
        if rel not in have:
            out.append((f"pages/services/{slug}/", A.DETAIL, slug))
    return sorted(out)


def the_icons(r: Results) -> None:
    print("\nthe tab icon, and the address a browser probes before anything else")

    shipped = (ROOT / "assets/images/favicon/favicon.ico").read_bytes()

    # WITH NOTHING UPLOADED, EVERYTHING IS WHAT SHIPS. That is the state a
    # fresh clone and a failed publish are both in, and a browser tab is not a
    # place to discover it.
    home = render(HOME)
    r.check("every page points /favicon.ico at the site root",
            icons(home).get("any") == "/favicon.ico", str(icons(home))[:200])
    r.check("and it answers, where it used to 404",
            served("favicon.php") == shipped,
            f"{len(served('favicon.php'))} bytes vs {len(shipped)} shipped")
    r.check("with the committed set named beside it",
            icons(home).get("16x16") == "/assets/images/favicon/favicon-16.png"
            and icons(home).get("180x180") == "/assets/images/favicon/apple-touch-icon.png",
            str(icons(home))[:250])

    made = {n: f"/uploads/cccccc{n}0000.png" for n in
            ("png16", "png32", "png48", "png96", "apple", "png192", "png512")}
    put(SETTINGS, lambda d: d["icon"].__setitem__("generated", dict(made)))

    home = render(HOME)
    r.check("an uploaded set reaches every <link> in the head",
            icons(home).get("16x16") == made["png16"]
            and icons(home).get("96x96") == made["png96"]
            and icons(home).get("180x180") == made["apple"],
            str(icons(home))[:250])

    manifest = json.loads(served("manifest.php") or b"{}")
    r.check("and both icons the manifest names",
            [i["src"] for i in manifest.get("icons", [])] == [made["png192"], made["png512"]],
            str(manifest.get("icons"))[:200])

    # ALL OR NOTHING. A container built from two of three sizes is a valid file
    # holding the wrong set; one built from a rung that never arrived is a
    # valid file holding nothing. Either is worse than the mark that ships.
    r.check("/favicon.ico falls back whole when a named file is not there",
            served("favicon.php") == shipped, "it served a partial container")

    print("\nthe container, assembled from what was published")
    # Real files this time, so the bytes are real: the three the shape names
    # for the .ico, copied from the committed set under upload-shaped names.
    uploads = ROOT / "uploads"
    uploads.mkdir(exist_ok=True)
    written = []
    try:
        real = {}
        for size in (16, 32, 48):
            blob = (ROOT / f"assets/images/favicon/favicon-{size}.png").read_bytes()
            name = f"dddddd{size:04d}00000000.png"
            (uploads / name).write_bytes(blob)
            written.append(uploads / name)
            real[f"png{size}"] = "/uploads/" + name

        put(SETTINGS, lambda d: d["icon"].__setitem__(
            "generated", {**made, **real}))

        ico = served("favicon.php")
        res, kind, count = struct.unpack("<HHH", ico[:6])
        r.check("it is an icon directory holding three images",
                res == 0 and kind == 1 and count == 3, f"type={kind} entries={count}")

        bad = []
        for i in range(count):
            w, h, _c, _r, _p, _bc, length, offset = struct.unpack(
                "<BBBBHHII", ico[6 + i * 16:22 + i * 16])
            payload = ico[offset:offset + length]
            if len(payload) != length or payload[:8] != b"\x89PNG\r\n\x1a\n":
                bad.append(f"{w}x{h} at {offset}")
        r.check("every entry points at its own PNG payload", not bad, "; ".join(bad))
        r.check("and the sizes are the ones the shape declares",
                [struct.unpack("<B", ico[6 + i * 16:7 + i * 16])[0] for i in range(3)]
                == [16, 32, 48], "wrong sizes")
    finally:
        for f in written:
            f.unlink(missing_ok=True)
        if uploads.is_dir() and not any(uploads.iterdir()):
            uploads.rmdir()


def main() -> None:
    held = {p: p.read_bytes() for p in (SETTINGS, SEO)}

    r = Results()
    try:
        without_the_document(r)
        one_change_moves_all(r)
        the_override(r)
        the_dark_half(r)
        the_icons(r)
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
