#!/usr/bin/env python3
"""
Push the contact details out of content/contact.json into every page.

Build tool. NOT deployed to the web server (see tools/README.md).

    python3 tools/sync_site_contact.py            # write the changes
    python3 tools/sync_site_contact.py --dry-run  # show what would change

WHY THIS EXISTS
/pages/contact/ renders content/contact.json, so editing an address in the
admin changes that page the moment it is saved. The same facts also appear in
the footer's contact block, which this project deliberately keeps as literal
markup in every page file.

Runtime partials are ruled out, so nothing on the server can update that from a
file. This does it here, before a deploy, and stamps a fingerprint the editor
reads back so it can say whether the two are still in step.

IT USED TO DO TWO THINGS
The second was the Organization structured data, pasted into all seventeen
heads. That is gone: seo_graph() in lib/head.php builds the graph from
content/contact.json on the request, so the addresses and telephone numbers a
search engine reads are never a copy and can never be stale. Nothing needs to
run before a deploy for them to be right. See ADR 0020.

The footer is still literal markup, so this tool is still needed for it.

THE ORDER TO RUN THINGS IN
  1. download content/contact.json from the host — the server's copy is the
     real one, the repo's is only a seed;
  2. python3 tools/sync_site_contact.py
  3. python3 tools/check_shared_markup.py     (proves the sixteen agree)
  4. upload the pages — and NOT content/, which the host owns.

WHAT IT DOES NOT TOUCH
The contact page itself, which needs no help, and the wording around the
details — headings, taglines, the footer's own description. Only facts.
"""

import argparse
import hashlib
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATA = ROOT / "content" / "contact.json"
FOOTER = ROOT / "tools" / "templates" / "footer.html"

# Where the fingerprint is recorded once the footers have been rebuilt for it.
#
# It used to be stamped into contact.json, which stopped being possible when
# the backend took ownership of that file: this side's copy is a replica, and
# the next publish overwrites whatever was written into it. So the record lives
# in a file the backend never writes, deploys with lib/, and is handed back to
# the backend in every publish response — the frontend answering the question
# it is the only one that can answer.
STAMP = ROOT / "lib" / "footer-fingerprint.php"

# The block inside the footer template that holds the facts. Everything else
# in the footer — the brand, the two link columns, the legal line — is wording
# and is left alone.
CONTACT_BLOCK = re.compile(
    r'<address class="site-footer__contact">.*?</address>', re.S
)

# "Sun – Thu: 9:00 AM – 6:00 PM" -> "Sunday – Thursday". The footer says which
# days beside the numbers as well as beside the hours, because the question at
# the numbers is "can I ring now" and at the hours is "when are they open".
DAY_NAMES = {
    "sun": "Sunday", "mon": "Monday", "tue": "Tuesday", "tues": "Tuesday",
    "wed": "Wednesday", "thu": "Thursday", "thur": "Thursday", "thurs": "Thursday",
    "fri": "Friday", "sat": "Saturday",
}


# ----------------------------------------------------------------- the model


def load() -> dict:
    if not DATA.exists():
        raise SystemExit(f"Missing {DATA.relative_to(ROOT)} — nothing to sync from.")
    return json.loads(DATA.read_text())


def shown_offices(data: dict) -> list[dict]:
    return [
        o for o in data.get("offices", {}).get("items", [])
        if o.get("status", "shown") == "shown"
    ]


def email_of(data: dict) -> str:
    """The first email address among the reach rows.

    A row holds a list of values; "value" is the single-value shape those rows
    had before, and contact_reach_defaults() in lib/contact.php still migrates
    it, so an older contact.json read here has to work the same way. The two
    must agree — the fingerprint is computed from this on both sides, and a
    disagreement makes the editor warn about drift that is not there.
    """
    for item in data.get("reach", {}).get("items", []):
        if item.get("type") != "email":
            continue
        values = item.get("values")
        if isinstance(values, list) and values:
            return str(values[0]).strip()
        if item.get("value"):
            return str(item["value"]).strip()
    return ""


def tel(number: str) -> str:
    """The href form: digits, and a leading + if it had one. Mirrors
    contact_tel() in lib/contract.php — the two must agree, or the footer would
    dial a different number from the contact page."""
    digits = re.sub(r"[^0-9]", "", number)
    return ("+" if number.strip().startswith("+") else "") + digits


def fingerprint(data: dict) -> str:
    """Byte-for-byte what contact_fingerprint() computes in lib/contract.php.
    A delimited string rather than JSON, because PHP and Python do not spell a
    JSON document the same way; see the note in that function."""
    parts = ["email=" + email_of(data)]
    for o in shown_offices(data):
        s = o.get("schema", {})
        parts.append("|".join([
            str(o.get("name", "")).strip(),
            str(o.get("address", "")).strip(),
            ";".join(str(p).strip() for p in o.get("phones", []) if str(p).strip()),
            str(o.get("hours", "")).strip(),
            str(s.get("street", "")).strip(),
            str(s.get("locality", "")).strip(),
            str(s.get("region", "")).strip(),
            str(s.get("postal_code", "")).strip(),
            str(s.get("country", "")).strip().upper(),
        ]))
    return hashlib.sha256("\n".join(parts).encode("utf-8")).hexdigest()


def days_of(hours: str) -> str:
    """The day range out of an opening-hours line, spelled out in full.

    Everything before the first colon is the days — "Sun – Thu: 9:00 AM…" — and
    each abbreviation in it is expanded. Anything this cannot read confidently
    returns empty, and the note is simply left off rather than guessed at.
    """
    head = hours.split(":", 1)[0].strip()
    if not head or any(ch.isdigit() for ch in head):
        return ""

    out = []
    for token in re.split(r"(\W+)", head):
        key = token.strip().lower().rstrip(".")
        if key in DAY_NAMES:
            out.append(DAY_NAMES[key])
        elif re.fullmatch(r"[A-Za-z]+", token):
            return ""       # a word that is not a day: do not invent a range
        else:
            out.append(token)
    return "".join(out).strip()


def escape(value: str) -> str:
    return (value.replace("&", "&amp;").replace("<", "&lt;")
                 .replace(">", "&gt;").replace('"', "&quot;"))


# ---------------------------------------------------------------- the footer


def icon(name: str) -> str:
    return (f'<svg class="icon contact-item__icon" aria-hidden="true" '
            f'focusable="false"><use href="#{name}"></use></svg>')


def footer_block(data: dict, indent: str) -> str:
    """Rebuild the footer's contact block.

    Four items, in the order they are now: numbers, email, addresses, hours.
    Each office contributes to whichever of them it has something to say to, so
    an office with no hours simply does not appear in the last one.

    An office whose numbers are already listed is skipped rather than repeated
    — the Brussels office is reached on the Dhaka numbers, and printing them
    twice reads as two different offices sharing a typo.
    """
    offices = shown_offices(data)

    # The first line replaces the matched <address ...>, whose own indentation
    # is already in the file; only the lines after it need prefixing.
    out: list[str] = ['<address class="site-footer__contact">']

    def add(depth: int, text: str = "") -> None:
        out.append(f"{indent}{'  ' * depth}{text}" if text else "")

    lines = out

    # ---- phone numbers
    seen: list[list[str]] = []
    groups = []
    for office in offices:
        phones = [str(p).strip() for p in office.get("phones", []) if str(p).strip()]
        if not phones or phones in seen:
            continue
        seen.append(phones)
        groups.append((str(office.get("name", "")).strip(), phones,
                       days_of(str(office.get("hours", "")))))

    if groups:
        add(1, '<div class="contact-item">')
        add(2, icon("phone"))
        add(2, "<div>")
        for at, (name, phones, days) in enumerate(groups):
            if at:
                add(0)
            add(3, f'<span class="contact-item__label">{escape(name)}</span>')
            for i, phone in enumerate(phones):
                tail = "" if i == len(phones) - 1 else "<br>"
                add(3, f'<a href="tel:{escape(tel(phone))}">{escape(phone)}</a>{tail}')
            if days:
                add(3, f'<span class="contact-item__note">{escape(days)}</span>')
        add(2, "</div>")
        add(1, "</div>")

    # ---- email
    email = email_of(data)
    if email:
        add(0)
        add(1, '<div class="contact-item">')
        add(2, icon("envelope"))
        add(2, f'<a href="mailto:{escape(email)}">{escape(email)}</a>')
        add(1, "</div>")

    # ---- addresses
    addressed = [o for o in offices if str(o.get("address", "")).strip()]
    if addressed:
        add(0)
        add(1, '<div class="contact-item">')
        add(2, icon("map-marker-alt"))
        add(2, "<div>")
        for i, office in enumerate(addressed):
            tail = "" if i == len(addressed) - 1 else "<br>"
            add(3, '<span class="contact-item__label">'
                   f'{escape(str(office.get("name", "")).strip())}</span>')
            add(3, f'{escape(str(office["address"]).strip())}{tail}')
        add(2, "</div>")
        add(1, "</div>")

    # ---- opening hours
    houred = [o for o in offices if str(o.get("hours", "")).strip()]
    if houred:
        add(0)
        add(1, '<div class="contact-item">')
        add(2, icon("clock"))
        add(2, "<div>")
        for office in houred:
            add(3, '<span class="contact-item__label">'
                   f'{escape(str(office.get("name", "")).strip())} Office</span>')
            add(3, escape(str(office["hours"]).strip()))
        add(2, "</div>")
        add(1, "</div>")

    add(0, "</address>")
    return "\n".join(lines)


# -------------------------------------------------------------------- main


STAMP_TEMPLATE = """<?php
/**
 * Tech4TIME — what the site-wide footers currently say.
 *
 * GENERATED by tools/sync_site_contact.py. Do not edit by hand; run that.
 *
 * The footer's contact details are literal markup in all sixteen pages,
 * because the project forbids runtime partials. So the moment somebody edits
 * an address in the admin, the contact page is right and the footers are
 * behind — until the pages are rebuilt and deployed.
 *
 * This is the fingerprint of the details the footers were last rebuilt FOR.
 * api/publish.php returns it in every publish response, the backend records
 * what it was told, and the editor compares it against the details it now
 * holds. The frontend is the only side that knows what its own footers say,
 * so it is the side that answers.
 *
 * Not reachable over HTTP: .htaccess forbids /lib/.
 */

declare(strict_types=1);

const FOOTER_FINGERPRINT = '%s';
"""


def read_stamp() -> str:
    """The digest recorded in lib/footer-fingerprint.php, or ''."""
    if not STAMP.is_file():
        return ""
    found = re.search(r"FOOTER_FINGERPRINT\s*=\s*'([0-9a-f]*)'", STAMP.read_text())
    return found.group(1) if found else ""


def write_stamp(digest: str) -> None:
    STAMP.write_text(STAMP_TEMPLATE % digest)


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    data = load()

    if not shown_offices(data):
        raise SystemExit("No offices are shown — refusing to empty the footer.")

    # ---- 1. the footer template
    if not FOOTER.exists():
        raise SystemExit(f"Missing {FOOTER.relative_to(ROOT)}")

    footer = FOOTER.read_text()
    if not CONTACT_BLOCK.search(footer):
        raise SystemExit(
            "Could not find <address class=\"site-footer__contact\"> in the footer "
            "template. It has been restructured; update CONTACT_BLOCK in this file."
        )

    # The indentation is read out of the file rather than assumed: the block
    # sits at one depth in the template and at another in a page, and a
    # hard-coded value would be wrong in one of them.
    match = CONTACT_BLOCK.search(footer)
    indent = footer[:match.start()].rpartition("\n")[2]
    indent = indent if indent.strip() == "" else ""

    new_footer = (footer[:match.start()]
                  + footer_block(data, indent)
                  + footer[match.end():])
    footer_changed = new_footer != footer

    print("tools/templates/footer.html  —  "
          + ("contact block rewritten" if footer_changed else "already in step"))
    if footer_changed and not args.dry_run:
        FOOTER.write_text(new_footer)

    # ---- 2. push the footer template into every page
    if footer_changed and not args.dry_run:
        print("\nPropagating the footer:")
        subprocess.run([sys.executable, str(ROOT / "tools" / "propagate_shared.py")],
                       check=True)

    # ---- 3. stamp the fingerprint, so the editor stops warning
    digest = fingerprint(data)
    if read_stamp() != digest:
        print(f"\nfingerprint  —  {digest[:16]}…")
        if not args.dry_run:
            write_stamp(digest)
            print(f"    recorded in {STAMP.relative_to(ROOT)}")
            print("    the admin learns it from the next publish response")

    if args.dry_run:
        print("\nDry run: nothing written.")
        return

    print("\nNow run:  python3 tools/check_shared_markup.py")
    print("     and  python3 tools/audit_pages.py")


if __name__ == "__main__":
    main()
