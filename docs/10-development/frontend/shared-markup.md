# Shared markup — the header, footer and script block

**Applies to:** frontend

The one place where a careless edit does damage that is invisible until a check catches it.

---

## The problem this solves

Runtime `fetch()` partials are forbidden — every page must be a complete, self-contained document
that a crawler receives in one request. So the site header, the footer, the dock, the hero circuit
and the script tags exist as **literal markup in all sixteen pages**, and in
`pages/services/detail.php`, which draws the services that have no directory of their own —
seventeen files.

That is seventeen copies of the same header, free to drift apart.

**The `<head>` is no longer one of them** — see below. It is rendered on the request by
`lib/head.php`, which is a different answer to the same problem: a page can be self-contained
without its markup having been *typed* seventeen times, because PHP composes it before the response
leaves the server.

Three tools close the gap:

| | |
|---|---|
| `tools/templates/` | the single source of truth for those blocks |
| `tools/propagate_shared.py` | pushes a template change out to every page |
| `tools/check_shared_markup.py` | proves no page has drifted |

**`tools/templates/` is never deployed.** It is a build input.

---

## The rule

> **Never edit a header or footer in a page file.** Edit the template, then propagate.

A hand edit to one page's footer will pass every visual check and be silently reverted by the next
propagate — or, worse, survive as the one page that differs.

```bash
# 1. edit the template
$EDITOR tools/templates/footer.html

# 2. see what would change
python3 tools/propagate_shared.py --dry-run

# 3. apply
python3 tools/propagate_shared.py

# 4. prove it
python3 tools/check_shared_markup.py
```

---

## The templates

| File | What it is |
|---|---|
| `header.html` | skip link, sticky header, nav drawer, theme toggle |
| `footer.html` | the footer, **including the contact details**, and the back-to-top control |
| `dock.html` | the floating dock |
| `scripts.html` | the deferred script tags, in dependency order |
| `hero-circuit.html` | the circuitry framing the title band: four corner clusters and a chevron band top and bottom, with a charge on every trace, painted by `circuit.js` on a canvas; the SVG carries 24 as the fallback |

`propagate_shared.py` carries the header, footer, dock and hero circuit.
It does **not** carry `scripts.html`, which is read once by `assemble_page.py` when a page is
created; after that each page holds its own copy and a script change is edited in every page.

---

## The `<head>` is not shared markup any more

There were two more templates here: `head.html`, with `{{TITLE}}`, `{{DESCRIPTION}}`,
`{{CANONICAL}}` and `{{OG_TYPE}}` placeholders, and `jsonld-base.html`, the Organization schema.
Both were read **once per page, at birth**, by `assemble_page.py`, and never again. Nothing
propagated them and `check_shared_markup.py` did not cover them, so seventeen heads of 222–308
lines each were free to drift with nothing watching — and they did.

They are gone. A page's head is two calls:

```php
<html lang="<?= h(seo_lang()) ?>">
<head>
<?php seo_head('/pages/about/', $data['meta'], ['pages/about.css'], $data['updated']); ?>
<?php seo_jsonld('/pages/about/', $data['meta'], $data['updated']); ?>
```

`seo_head()` writes the charset, the title, the description, the canonical, the crawl directive, the
share card, the icons, the stylesheets and `theme-init.js`. `seo_jsonld()` writes the graph — built
from `content/contact.json` and `content/seo.json` rather than pasted, so it cannot go stale.

The values are content: they are edited at `https://admin.tech4time.bd/?s=seo`, and a page's own
title and description live in that page's own document.
[seo.md](../../40-reference/seo.md) · [ADR 0020](../../90-decisions/0020-page-metadata-is-content.md)

`check_shared_markup.py` still has one assertion about the head, and only one: that `theme-init.js`
is emitted, once, for all seventeen files. That script is what prevents a flash of the wrong theme,
and it is the only line of the head whose absence is invisible in a diff and obvious to a visitor.

---

## The one thing that is not copied

`aria-current="page"`.

That marker is the single legitimate per-page difference in shared markup, and a blind copy would
wipe it from every page and mark the active link nowhere. `propagate_shared.py` reads it out of each
page first — as the set of hrefs that page marks — and re-applies it afterwards. A page with no
marker, like `404.php`, keeps none.

If you add another legitimate per-page difference, it has to be taught to the propagator the same
way. Prefer not to.

`404.php` is the page with no marker. It was `404.html` until the head moved into PHP; it is the
only page not reachable from the nav, and the propagator gives it none.

---

## The footer's contact details

The footer repeats the company's phone number, email and address. **The admin cannot reach them** —
they are markup, not content, and the editor writes `content/contact.json`.

So after changing contact details at `https://admin.tech4time.bd/?s=contact`:

```bash
python3 tools/sync_site_contact.py     # push them from the JSON into every page
python3 tools/check_shared_markup.py   # confirm
```

Then redeploy the pages. The admin shows a banner when the JSON and the pages have drifted, so the
gap is never invisible — but closing it is a deploy, not a save.

> On the host, the server's `content/contact.json` is the real one. Download it before running the
> sync, or you will push stale details into every page.
> *content-runbook.md* (in tech4time-website-backend)

---

## Navigation

The header carries six routes. Three ported pages (Branding & Advertisement, Resource
Certifications, Privacy Policy) and the six services sub-pages are reachable from the footer and
from the services hub — so no page is orphaned while the header stays legible.

`audit_pages.py` checks that every page is reachable from somewhere.

---

## If `check_shared_markup.py` fails

It names the page and the block. Almost always: somebody edited a page directly.

```bash
python3 tools/propagate_shared.py --dry-run   # see what differs
python3 tools/propagate_shared.py             # put it back in step
```

If the *intended* change was in the page rather than the template, move it into
`tools/templates/` first — otherwise the next propagate discards it.
