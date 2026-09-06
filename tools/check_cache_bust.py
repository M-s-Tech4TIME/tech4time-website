#!/usr/bin/env python3
"""
Refuse to ship a changed stylesheet or script that nobody will re-download.

Build/audit tool. NOT deployed to the web server (see tools/README.md).

    python3 tools/check_cache_bust.py                 # against origin/main
    python3 tools/check_cache_bust.py --base HEAD~1
    python3 tools/check_cache_bust.py --base v1.2.0

WHY THIS EXISTS
Filenames here are not content-hashed, because ADR 0001 forbids the build step
that would hash them, and `.htaccess` caches CSS, JS and fonts for a year. So a
released stylesheet reaches a returning visitor only if the URL in the markup
changed too -- a version query, bumped by hand, in the same breath as the file.

That is a rule kept by remembering, and remembering is not a mechanism. It has
already been missed twice in this repository, and both misses were silent:

  * `assets/css/layout.css` was rewritten with a new set of animation classes
    while the markup still asked for the unversioned URL. A returning visitor
    would have got the new markup against a year-old stylesheet, which does not
    error -- it just leaves every rule the new classes need undefined.

  * `assets/js/main.js` gained an entry in its hardcoded MODULES allow list. A
    stale copy iterates the old array, so the module registers itself and is
    never initialised. No error. No console line. The feature is simply absent,
    and only for the visitors who have been here before -- which is to say, not
    for whoever is checking.

Neither shows up in a browser opened on a clean cache, which is every browser
a developer tests in. This is the check that does not have to remember.

WHAT IT COMPARES
The reference *as written in the markup*, at the base revision and now. A
version query is the usual way to change it, but the check does not care how:
renaming the file works, and so would a hash if this project ever grew one.
The rule is only that a changed asset is not still served from an unchanged
URL.

A file no page references (a module loaded by another module, say) is reported
and skipped: there is no markup URL to bump, and its freshness is its
importer's problem.
"""

import argparse
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
WATCHED = re.compile(r"^assets/.*\.(css|js)$")

# Every place a page can name an asset. The query string is part of the capture
# on purpose -- it is the thing being checked.
REFERENCE = re.compile(r'(?:href|src)="(/assets/[^"]+)"')

# A stylesheet is no longer named by an href. Since the seventeen heads were
# collapsed into lib/head.php the URL is ASSEMBLED -- '/assets/css/' . $sheet --
# from two places: HEAD_STYLES for the five every page loads, and the third
# argument of each page's own seo_head() call for the one that is its own.
#
# This matters more than it looks. Neither is an href= for the regex above to
# find, so without reading them the check finds no stylesheet anywhere on the
# site and reports every one of them as "no page references it directly;
# skipped" -- passing, in silence, about the exact thing it was written to
# refuse. That is how it behaved for one commit, and it is why these two are
# parsed rather than left to a glob.
STYLE_ENTRY = re.compile(r"'([^']+\.css(?:\?[^']*)?)'")


def git(*args: str) -> str:
    out = subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True)
    if out.returncode != 0:
        raise SystemExit(f"git {' '.join(args)} failed:\n{out.stderr.strip()}")
    return out.stdout


def pages() -> list[str]:
    """Every file that can carry a reference, as repository-relative paths.

    lib/head.php is in here because it holds the five shared stylesheet URLs
    and their version queries. It is not a page, but it is where a page's
    markup now comes from, which is the same thing to this check.
    """
    found = ["index.php", "404.php", "lib/head.php"]
    found += [str(p.relative_to(ROOT)) for p in (ROOT / "pages").rglob("index.*")]
    return sorted(p for p in found if (ROOT / p).exists())


def styles(text: str) -> set[str]:
    """The stylesheet URLs a file assembles, rather than writes.

    Two shapes:

        const HEAD_STYLES = ['base.css', 'layout.css?v=4', ...];
        seo_head('/pages/about/', $data['meta'], ['pages/about.css'], ...);

    Read by taking the whole construct -- to the `;` for the constant, to the
    closing `)` for the call -- and then the quoted names inside it that end in
    .css. Scoping it to the construct is what keeps a comment mentioning
    layout.css from counting as a reference; matching on .css rather than on
    the first bracket is what keeps $data['meta'] from being read as the
    stylesheet list, which is a mistake this function has already made.
    """
    found: set[str] = set()

    at = text.find("const HEAD_STYLES = ")
    if at != -1:
        end = text.find(";", at)
        for name in STYLE_ENTRY.findall(text[at:end if end != -1 else len(text)]):
            found.add("/assets/css/" + name)

    at = text.find("seo_head(")
    while at != -1:
        depth, i = 0, text.index("(", at)
        for i in range(i, len(text)):
            if text[i] == "(":
                depth += 1
            elif text[i] == ")":
                depth -= 1
                if depth == 0:
                    break
        for name in STYLE_ENTRY.findall(text[at:i]):
            found.add("/assets/css/" + name)
        at = text.find("seo_head(", i)

    return found


def references(revision: str | None, paths: list[str]) -> dict[str, set[str]]:
    """asset path -> the set of URLs the markup uses for it, at one revision."""
    urls: dict[str, set[str]] = {}
    for rel in paths:
        if revision is None:
            text = (ROOT / rel).read_text(encoding="utf-8", errors="replace")
        else:
            out = subprocess.run(["git", "show", f"{revision}:{rel}"],
                                 cwd=ROOT, capture_output=True, text=True)
            if out.returncode != 0:      # the page did not exist yet
                continue
            text = out.stdout
        for url in set(REFERENCE.findall(text)) | styles(text):
            urls.setdefault(url.split("?", 1)[0].lstrip("/"), set()).add(url)
    return urls


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--base", default="origin/main",
                    help="revision to compare against (default: origin/main)")
    args = ap.parse_args()

    base = args.base
    if subprocess.run(["git", "rev-parse", "--verify", "--quiet", base],
                      cwd=ROOT, capture_output=True).returncode != 0:
        print(f"check_cache_bust: no such revision '{base}'.")
        print("Fetch it first, or pass --base. Nothing was checked.")
        return 2

    # Committed AND uncommitted. A check that only reads history tells you about
    # the mistake after you have made it; the point is to catch it while the
    # working tree is still the thing you are looking at.
    names = set(git("diff", "--name-only", f"{base}...HEAD").split("\n"))
    names |= set(git("diff", "--name-only", base).split("\n"))
    changed = sorted(n for n in names if WATCHED.match(n))
    if not changed:
        print(f"check_cache_bust: no stylesheet or script changed since {base}.")
        return 0

    now = references(None, pages())
    then = references(base, pages())

    problems, ok, unreferenced = [], [], []
    for asset in changed:
        here, before = now.get(asset, set()), then.get(asset, set())
        if not here:
            unreferenced.append(asset)
        elif here == before:
            problems.append((asset, sorted(here)))
        else:
            ok.append((asset, sorted(before) or ["(new)"], sorted(here)))

    print(f"check_cache_bust: {len(changed)} asset(s) changed since {base}\n")
    for asset, was, is_ in ok:
        print(f"  ok    {asset}")
        print(f"          {', '.join(was)}  ->  {', '.join(is_)}")
    for asset in unreferenced:
        print(f"  --    {asset} — no page references it directly; skipped")
    for asset, urls in problems:
        print(f"  FAIL  {asset}")
        print(f"          still served from {', '.join(urls)}")

    if problems:
        print(f"\n{len(problems)} changed asset(s) keep an unchanged URL.")
        print("Anyone who has visited before keeps the copy they already have,")
        print("for up to a year, and sees the new markup against the old file.")
        print("\nBump the version query where the URL is written: a shared")
        print("stylesheet in lib/head.php's HEAD_STYLES, a page's own in its")
        print("seo_head() call, a script in tools/templates/scripts.html AND in")
        print("every page — scripts.html is not propagated, so both have to be")
        print("edited. docs/20-deployment/routine-deploys.md, 'Cache busting'.")
        return 1

    print("\nEvery changed asset is served from a URL that changed with it.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
