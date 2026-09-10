#!/usr/bin/env python3
"""
Prove what content/chrome.json turns into on a page.

Development tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/test_chrome.py
Requires the PHP CLI:    sudo apt install php-cli

WHY THIS EXISTS
The header, footer and dock are one document rendered by lib/body.php on every
page of the site (ADR 0023). Nothing else here looks at what that document
turns INTO:

  tools/audit_pages.py     audits the rendered page, but against the document
                           as it happens to be -- it cannot hide a row and see
                           what changes
  tools/test_publish.py    proves the document travels the wire and that a
                           marker in it reaches a page; it does not vary the
                           document's SHAPE
  tools/check_shared_markup.py   has nothing to compare any more: there is one
                           copy of this markup

So this varies the document and reads the page back. Seven things, and the
last two are the reason the file exists at all.

WHAT IT PROVES
  - a hidden row is ABSENT, in every band that has rows;
  - aria-current lands on exactly one nav link, and never on the brand;
  - the header and the dock agree about which page you are on;
  - the services column follows content/services.json, hidden service and all;
  - phone rows dial, email rows compose, addresses and hours are text;
  - a band emptied on purpose STAYS empty, while a missing one falls back;
  - EVERY PAGE STILL RENDERS WITH content/chrome.json MISSING. That is the
    failure mode this arrangement created and the one worth a test of its own:
    the chrome is on all seventeen pages, so a document that did not arrive
    used to be a site with no navigation at all. chrome_normalise() fills from
    chrome_defaults(), which is the site's own header, footer and dock.

Every test runs against a COPY of content/chrome.json and content/services.json,
restored afterwards whether the run passes or fails.
"""

import json
import os
import re
import shutil
import signal
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CHROME = ROOT / "content" / "chrome.json"
SERVICES = ROOT / "content" / "services.json"

MARK = "ZQX"

# One of each shape of page: the home page, whose brand link used to carry a
# second aria-current; an ordinary interior page; the services index; a service
# page, which marks its SECTION rather than itself; and the 404, which has no
# address of its own and marks nothing.
PAGES = ["/", "/pages/about/", "/pages/services/",
         "/pages/services/cybersecurity/", "/pages/branding-and-advertisement/"]


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

    def section(self, name):
        print(f"\n{name}")


def free_port() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return int(s.getsockname()[1])


def fetch(base: str, path: str) -> tuple[int, str]:
    try:
        with urllib.request.urlopen(base + path, timeout=20) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")


def write(path: Path, change) -> None:
    """Change one document on disk, the way a publish would leave it.

    content/ is a replica written by api/publish.php and by nothing else, so a
    test that edits it must put it back -- which main() does, from bytes taken
    before anything ran. tools/test_sitemap.py does the same to the SEO record
    and for the same reason: what is being tested here is the RENDERER, and
    going through the signed endpoint to reach it would be testing the wire
    again. tools/test_publish.py is where the wire is proved.
    """
    data = json.loads(path.read_text())
    change(data)
    path.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n")


def html_escape(value: str) -> str:
    """What h() would write. Only the four that appear in a name."""
    return (value.replace("&", "&amp;").replace("<", "&lt;")
                 .replace(">", "&gt;").replace('"', "&quot;"))


def band(html: str, start: str, end: str) -> str:
    """One region of a page, so a match cannot come from another."""
    a = html.find(start)
    b = html.find(end, a + 1) if a >= 0 else -1
    return html[a:b] if a >= 0 and b > a else ""


def header_of(html: str) -> str:
    return band(html, '<header class="site-header"', "</header>")


def footer_of(html: str) -> str:
    return band(html, '<footer class="site-footer"', "</footer>")


def dock_of(html: str) -> str:
    return band(html, '<div class="dock" data-dock>', "<!-- Deferred so nothing")


def nav_links(html: str) -> list[tuple[str, str]]:
    """(href, text) for each link in the header nav, in order."""
    return re.findall(r'<a class="nav-link" href="([^"]*)"[^>]*>([^<]*)</a>',
                      header_of(html))


def marked(region: str) -> list[str]:
    """The href of every link in a region carrying aria-current."""
    return re.findall(r'<a [^>]*href="([^"]*)"[^>]*aria-current="page"', region)


# ------------------------------------------------------------------- tests


def as_shipped(base: str, r: Results) -> None:
    """What the document renders as before anything is changed."""
    r.section("the chrome is on every page")

    for path in PAGES:
        status, html = fetch(base, path)
        ok = (status == 200
              and header_of(html) != "" and footer_of(html) != "" and dock_of(html) != "")
        r.check(f"{path} has a header, a footer and a dock", ok, f"status {status}")

    r.section("aria-current marks one link, and the right one")

    for path, want in [("/", "/"),
                       ("/pages/about/", "/pages/about/"),
                       ("/pages/services/", "/pages/services/"),
                       # A SERVICE MARKS ITS SECTION. There is no nav entry for
                       # cybersecurity, and the entry the visitor is inside is
                       # Services -- which is what the site has always sent.
                       ("/pages/services/cybersecurity/", "/pages/services/"),
                       # No nav entry at all, so nothing is marked.
                       ("/pages/branding-and-advertisement/", None)]:
        _, html = fetch(base, path)
        got = marked(header_of(html))

        if want is None:
            r.check(f"{path} marks no navigation link", got == [], str(got))
        else:
            r.check(f"{path} marks {want}", got == [want], str(got))

    # THE DEFECT THIS CONVERSION FIXED. propagate_shared.py re-marked every <a>
    # whose href a page already marked, so index.php sent aria-current on its
    # logo link as well as its Home nav link -- two current links, one of them
    # a picture. lib/body.php applies it to a nav link and nowhere else.
    _, home = fetch(base, "/")
    brand = re.search(r'<a class="site-header__brand"[^>]*>', home)
    r.check("and the brand link on the home page is not one of them",
            brand is not None and "aria-current" not in brand.group(0),
            brand.group(0) if brand else "no brand link at all")

    r.check("the footer marks nothing, being a directory rather than a place",
            marked(footer_of(home)) == [], str(marked(footer_of(home))))

    _, four04 = fetch(base, "/no-such-page-" + MARK + "/")
    r.check("and the 404 marks nothing, having no address of its own",
            marked(four04) == [], str(marked(four04)))

    r.section("the header and the dock agree")

    for path in ["/", "/pages/services/", "/pages/services/cybersecurity/"]:
        _, html = fetch(base, path)
        head = set(marked(header_of(html)))
        dock = set(marked(dock_of(html)))
        # The dock bar carries four of the six destinations, so its marked set
        # is the header's or empty -- never a DIFFERENT page.
        r.check(f"{path}: the dock marks what the header marks",
                dock <= head or head <= dock, f"header {head}, dock {dock}")

    r.section("the footer's contact rows dial, compose, or say nothing")

    _, html = fetch(base, "/")
    foot = footer_of(html)
    chrome = json.loads(CHROME.read_text())

    for row in chrome["footer"]["contact"]["items"]:
        if row["status"] != "shown" or not row["lines"]:
            continue
        line = row["lines"][0]

        if row["kind"] == "phone":
            want = 'tel:' + re.sub(r"[^0-9]", "", line)
            want = ("+" if line.strip().startswith("+") else "") + want[4:]
            r.check(f"  {line} dials", f'href="tel:{want}"' in foot,
                    f'expected href="tel:{want}"')
        elif row["kind"] == "email":
            r.check(f"  {line} composes", f'href="mailto:{line}"' in foot)
        else:
            r.check(f"  {line} is text, not a link",
                    line in foot and f'>{line}</a>' not in foot)

    r.section("the services column is content/services.json")

    services = json.loads(SERVICES.read_text())["services"]["items"]
    for service in services:
        want = f'href="/pages/services/{service["slug"]}/"'
        # h() escapes the name on the way out, so "IT Consultancy & Training"
        # is on the page as "IT Consultancy &amp; Training".
        drawn = html_escape(service["name"])
        r.check(f"  {service['name']} is in the footer",
                want in foot and drawn in foot, want)

    r.check("  and so is the row that goes to all of them",
            f'>{chrome["footer"]["services"]["index_label"]}</a>' in foot)


def hiding(base: str, r: Results) -> None:
    """A hidden row is kept and not drawn, in every band that has rows."""
    r.section("hiding a row takes it off the page")

    original = json.loads(CHROME.read_text())
    targets = json.loads(subprocess.run(
        ["php", "-r", "require 'lib/chrome.php'; echo json_encode(chrome_target_list());"],
        cwd=ROOT, capture_output=True, text=True).stdout)

    def hide_first(data, *path):
        rows = data
        for key in path:
            rows = rows[key]
        rows[0]["status"] = "hidden"

    for what, path, region, mark in [
        ("a navigation link", ("header", "nav", "items"), header_of, None),
        ("a quick link",      ("footer", "links", "items"), footer_of, None),
        ("a bottom-bar link", ("footer", "legal", "items"), footer_of, None),
        ("a contact detail",  ("footer", "contact", "items"), footer_of, None),
        ("a dock section",    ("dock", "panel", "items"), dock_of, None),
    ]:
        CHROME.write_text(json.dumps(original, indent=4, ensure_ascii=False) + "\n")
        rows = original
        for key in path:
            rows = rows[key]
        row = rows[0]

        # What the row draws on the page: a contact row's first line, or a
        # link's whole href attribute. NOT the bare route -- the home page's
        # is "/", which is in every closing tag on the document.
        needle = (row["lines"][0] if "lines" in row
                  else 'href="' + targets[row["target"]]["route"] + '"')

        _, before = fetch(base, "/pages/about/")
        r.check(f"{what}: it is on the page to begin with",
                needle in region(before), needle)

        write(CHROME, lambda d, p=path: hide_first(d, *p))
        _, after = fetch(base, "/pages/about/")

        # A LINK'S HREF MAY APPEAR TWICE ON A PAGE -- the Home nav link and the
        # Home quick link are the same address -- so the count is what matters,
        # not presence.
        r.check(f"{what}: hidden, it is drawn one fewer time",
                region(after).count(needle) == region(before).count(needle) - 1,
                f"{region(before).count(needle)} -> {region(after).count(needle)}")

        r.check(f"{what}: and the document still holds it",
                json.loads(CHROME.read_text())[path[0]][path[1]][path[2]][0]["status"]
                == "hidden",
                "hiding is not deleting")

    CHROME.write_text(json.dumps(original, indent=4, ensure_ascii=False) + "\n")


def derived(base: str, r: Results) -> None:
    """The two columns that store nothing follow the documents that do."""
    r.section("a hidden service leaves the footer by itself")

    services_before = SERVICES.read_bytes()
    chrome_before   = CHROME.read_bytes()

    services = json.loads(SERVICES.read_text())
    victim = services["services"]["items"][0]

    _, before = fetch(base, "/")
    r.check("the service is in the footer to begin with",
            html_escape(victim["name"]) in footer_of(before))

    write(SERVICES, lambda d: d["services"]["items"][0].update(status="hidden"))
    _, after = fetch(base, "/")

    r.check("hidden, it is gone from the footer",
            html_escape(victim["name"]) not in footer_of(after),
            "a hidden service must not be the one thing still linking to a page "
            "that has left the sitemap")
    r.check("and the others are still there",
            html_escape(services["services"]["items"][1]["name"]) in footer_of(after))

    # A NAV ROW POINTING AT IT IS SKIPPED TOO, for the same reason: hiding a
    # service takes its page out of the sitemap, and a nav link to it would be
    # the only thing left pointing at a page nobody is meant to find.
    write(CHROME, lambda d: d["header"]["nav"]["items"].append({
        "id": "victim", "target": "service:" + victim["id"],
        "label": MARK + "-hiddensvc", "status": "shown"}))
    _, after = fetch(base, "/")
    r.check("and a navigation row pointing at it draws nothing",
            MARK + "-hiddensvc" not in header_of(after),
            "the renderer must skip what it cannot honestly link to")

    # A TARGET NOTHING ANSWERS TO IS SKIPPED RATHER THAN DRAWN DEAD. The
    # contract keeps the row so the editor can show it; the renderer will not
    # put a link on the page for it.
    write(CHROME, lambda d: d["header"]["nav"]["items"].append({
        "id": "ghost", "target": "service:no-such-service",
        "label": MARK + "-ghost", "status": "shown"}))
    _, after = fetch(base, "/")
    r.check("a row pointing at nothing at all draws nothing either",
            MARK + "-ghost" not in header_of(after))

    r.section("the social links are the SEO document's profiles")

    seo = json.loads((ROOT / "content" / "seo.json").read_text())
    for row in seo.get("sameas", {}).get("items", []):
        if row.get("status") == "hidden":
            continue
        r.check(f"  {row['label']} is in the footer",
                f'href="{row["url"]}"' in footer_of(before), row["url"])

    # BOTH DOCUMENTS GO BACK before the next group, which renders the site as
    # it ships. Leaving the service hidden here is what made
    # /pages/services/cybersecurity/ answer 404 in the group after it, the
    # first time this file was run -- a test failing on the state a previous
    # test left is the worst kind of failure to read.
    SERVICES.write_bytes(services_before)
    CHROME.write_bytes(chrome_before)


def without_the_document(base: str, r: Results) -> None:
    """Every page still renders when content/chrome.json is not there.

    THE REASON THIS FILE EXISTS. Before ADR 0023 the chrome was literal markup
    in seventeen page files: it could not go missing, because there was nothing
    to arrive. It is one document now, on every page of the site, so a publish
    that never landed -- or a fresh host, or a file somebody moved -- used to
    mean a site with no navigation at all.

    chrome_normalise() fills from chrome_defaults(), which is the site's own
    header, footer and dock as they shipped, extracted from the markup rather
    than typed. So the answer to a missing document is the correct page, not an
    empty one, and this is what says so.
    """
    r.section("with content/chrome.json missing")

    moved = CHROME.with_suffix(".json.moved")
    CHROME.rename(moved)
    try:
        for path in PAGES:
            status, html = fetch(base, path)
            r.check(f"{path} still renders", status == 200, f"status {status}")

        _, html = fetch(base, "/pages/about/")
        head, foot, dock = header_of(html), footer_of(html), dock_of(html)

        r.check("the header is there, with its logo and its nav",
                'class="site-header__brand"' in head and head.count("nav-link") == 6,
                f'{head.count("nav-link")} nav links')
        r.check("and the right link is marked",
                marked(head) == ["/pages/about/"], str(marked(head)))
        r.check("the footer is there, with all four columns",
                foot.count('class="site-footer__section"') == 3
                and 'class="site-footer__brand"' in foot,
                f'{foot.count("site-footer__section")} sections beside the brand')
        r.check("its services column is still read from the services document",
                "/pages/services/cybersecurity/" in foot)
        r.check("its contact rows are the ones the site shipped with",
                "info@tech4time.bd" in foot)
        r.check("the dock is there, with four keys and a menu button",
                dock.count('class="dock__key"') == 3
                and 'dock__key--contact' in dock
                and 'dock__key--menu' in dock,
                f'{dock.count(chr(34) + "dock__key" + chr(34))} plain keys')

        # AND THE ICONS. The chrome's symbols come from chrome_sprite(), which
        # reads the document -- so a missing document must not leave a page
        # drawing empty squares where the dock keys are.
        used = set(re.findall(r'<use href="#([a-z0-9-]+)"', html))
        defined = set(re.findall(r'<symbol id="([a-z0-9-]+)"', html))
        local = set(re.findall(r'<(?!symbol)[a-z]+[^>]*\sid="([a-z0-9-]+)"', html))
        r.check("and every icon it draws is inlined",
                used <= defined | local,
                f"missing: {sorted(used - defined - local)}")
    finally:
        moved.rename(CHROME)


def emptied_on_purpose(base: str, r: Results) -> None:
    """A band with no rows left in it stays empty.

    THE OTHER HALF OF THE GROUP ABOVE, and the half that is easy to lose. A
    missing band falls back to chrome_defaults(); a band that arrived EMPTY is
    an operator who hid or removed every row in it, and filling that one back
    in from the defaults would put links somebody deliberately took away back
    on every page of the site -- silently, and with no way to stop it from the
    editor.

    chrome_normalise() tells the two apart by asking whether the key holds an
    array at all, not whether it holds anything. It did not always: it filled
    both, which is how the empty page in the group above was found. Without
    this check, restoring that bug would pass every other test in this file.
    """
    r.section("a band emptied on purpose is left empty")

    def empty_the_nav(data):
        data["header"]["nav"]["items"] = []

    before = CHROME.read_bytes()
    try:
        write(CHROME, empty_the_nav)

        status, html = fetch(base, "/pages/about/")
        head, foot = header_of(html), footer_of(html)

        r.check("the page still renders", status == 200, f"status {status}")
        r.check("the header is still there, with its logo",
                'class="site-header__brand"' in head)
        r.check("and its nav draws nothing at all",
                head.count("nav-link") == 0,
                f'{head.count("nav-link")} nav links came back from the defaults')
        # THE BAND NEXT TO IT PROVES THE FALLBACK STILL WORKS. If emptying one
        # band quietly emptied the rest, this check would be the only thing
        # that noticed.
        r.check("while every other band keeps its rows",
                foot.count('class="site-footer__section"') == 3
                and "info@tech4time.bd" in foot,
                "only the emptied band is empty")
    finally:
        CHROME.write_bytes(before)


def main() -> None:
    if not shutil.which("php"):
        print("php not found — skipping. sudo apt install php-cli")
        return

    port = free_port()
    base = f"http://127.0.0.1:{port}"
    backup = {p: p.read_bytes() for p in (CHROME, SERVICES) if p.is_file()}

    with tempfile.TemporaryDirectory() as tmp:
        private = Path(tmp) / "t4t-private"
        private.mkdir(mode=0o700)
        (private / "publish.key").write_text("9f" * 32 + "\n")

        server = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", str(ROOT),
             str(ROOT / "tools" / "dev-router.php")],
            cwd=str(ROOT), stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
            env=dict(os.environ, T4T_PRIVATE=str(private)), preexec_fn=os.setsid,
        )

        r = Results()
        try:
            for _ in range(80):
                try:
                    urllib.request.urlopen(base + "/robots.txt", timeout=1).read()
                    break
                except Exception:
                    time.sleep(0.15)

            as_shipped(base, r)
            hiding(base, r)
            derived(base, r)
            without_the_document(base, r)
            emptied_on_purpose(base, r)
        finally:
            try:
                os.killpg(os.getpgid(server.pid), signal.SIGTERM)
                server.wait(timeout=5)
            except Exception:
                pass
            for path, bytes_ in backup.items():
                path.write_bytes(bytes_)
            CHROME.with_suffix(".json.moved").unlink(missing_ok=True)
            print("\ncontent/ restored")

    total = r.passed + len(r.failed)
    if r.failed:
        print(f"\n{len(r.failed)} of {total} checks FAILED:")
        for case in r.failed:
            print(f"  - {case}")
        sys.exit(1)
    print(f"\n{r.passed}/{total} checks passed")


if __name__ == "__main__":
    main()
