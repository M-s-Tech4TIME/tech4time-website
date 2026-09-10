# Shared markup — what is still pasted, and what stopped being

**Applies to:** frontend

Almost nothing is pasted any more. This page is short now, and most of it is about where the things
that used to be here went.

---

## The problem this solves

Runtime `fetch()` partials are forbidden — every page must be a complete, self-contained document
that a crawler receives in one request. For a long time that was answered by *typing* the same
markup into every page: the `<head>`, the header, the footer, the dock, the hero circuit and the
script tags all existed as literal markup in sixteen pages, and in `pages/services/detail.php`,
which draws the services that have no directory of their own — seventeen files.

A page can be self-contained without its markup having been typed seventeen times, because PHP
composes it before the response leaves the server. That is what the two emitters do:

| | |
|---|---|
| `lib/head.php` | every page's `<head>` — [ADR 0020](../../90-decisions/0020-page-metadata-is-content.md) |
| `lib/body.php` | the header, the footer and the dock — [ADR 0023](../../90-decisions/0023-the-header-and-footer-are-emitted-once.md) |

What is left pasted is the hero circuit and the script tags, and two tools still exist for them:

| | |
|---|---|
| `tools/templates/` | the single source of truth for the blocks that are still copied |
| `tools/propagate_shared.py` | pushes a template change out to every page |
| `tools/check_shared_markup.py` | proves no page has drifted |

**`tools/templates/` is never deployed.** It is a build input.

---

## The header, footer and dock are a document now

They render on the request from `content/chrome.json`, and everything in them is editable at
`https://admin.tech4time.bd/?s=chrome`: the nav links, the tagline, the footer's contact rows, the
copyright name, the four dock keys. A page calls three functions:

> **The screen is not built yet.** `content/chrome.json` already renders every page's header,
> footer and dock, and it is what the site shipped with. The editor that will change them —
> `?s=chrome`, referred to throughout this page — is the next piece of work, and until it exists
> a change to the chrome is a change to `chrome_defaults()` in `lib/contract.php` and a deploy.

```php
<?php body_header('/pages/about/'); ?>
<main class="page__main" id="main"> … </main>
<?php body_footer(); ?>
<?php body_dock('/pages/about/'); ?>
```

`$route` is the same address the page hands `seo_head()`. It decides one thing — which link carries
`aria-current="page"` — and the 404 passes `''` and marks nothing.

Two columns of the footer store nothing at all. The services list is read from
`content/services.json` at render time and the social links from the SEO document's `sameas` rows,
so neither can go stale and neither is edited twice.
[libraries.md](../server-side/libraries.md) · [content-schemas.md](../../40-reference/content-schemas.md)

`body_header()` also emits the chrome's icon sprite, because `inject_icons.py` reads page *source*
and the chrome is not in any page's source any more. [icons.md](icons.md)

---

## The `<head>` is not shared markup either

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

---

## What is still copied

| File | What it is |
|---|---|
| `hero-circuit.html` | the circuitry framing the title band: four corner clusters and a chevron band top and bottom, with a charge on every trace, painted by `circuit.js` on a canvas; the SVG carries 24 as the fallback |
| `scripts.html` | the deferred script tags, in dependency order |

The circuit is decoration: aria-hidden, no text in it, sixty lines of path coordinates and nothing
anybody should be offered a form for. So it did not become a document, it can still drift, and
`propagate_shared.py` is still what puts it back in step.

`propagate_shared.py` does **not** carry `scripts.html`, which is read once by `assemble_page.py`
when a page is created; after that each page holds its own copy and a script change is edited in
every page.

> **Never edit the hero circuit in a page file.** Edit the template, then propagate.

```bash
$EDITOR tools/templates/hero-circuit.html
python3 tools/propagate_shared.py --dry-run   # see what would change
python3 tools/propagate_shared.py             # apply
python3 tools/check_shared_markup.py          # prove it
```

---

## The two assertions about the emitters

`check_shared_markup.py` has no copies to compare for the header, footer, dock or head — there is
one of each. It asserts something else instead, in both cases the same shape: a line whose absence
nothing else on this site would notice.

- **`lib/head.php` emits `theme-init.js`.** That script is what prevents a flash of the wrong theme
  on first paint, and it is the only line of the head whose absence is invisible in a diff and
  obvious to a visitor.
- **`lib/body.php` emits all six `data-` hooks** the scripts bind to: `data-theme-toggle`,
  `data-dock`, `data-nav-drawer`, `data-nav-toggle`, `data-back-to-top`, `data-current-year`.
  `audit_pages.py` reads rendered output and would still find one `<header>`, one `<footer>` and
  every link resolving; the page would be valid and the behaviour would simply be gone. The suites
  that *would* notice — `test_nav.py`, `test_theme.py` — need Firefox, so they are not in the run
  before a commit.

---

## `aria-current="page"`

The one per-page difference in the chrome, and the thing the old arrangement got wrong.

`propagate_shared.py` used to read the marker out of each page before copying and re-apply it
afterwards — to **every** `<a>` whose href was in the marked set, which is why `index.php` sent
`aria-current` on its logo link as well as its Home nav link. Nobody designed that; it fell out of
the regex.

`lib/body.php` decides it now, from the route the page passes, and applies it to one nav link and
never to the brand. A prefix counts: on `/pages/services/cybersecurity/` the marked link is
Services, because that is the nav entry the visitor is inside. `/` is excluded from the prefix rule,
or Home would be current everywhere.

`404.php` passes `''` and marks nothing. It is served at every address that does not exist and has
no address of its own.

---

## The footer's contact details

They are the **footer's own**, edited at `https://admin.tech4time.bd/?s=chrome`, and they owe
nothing to `content/contact.json`.

That is deliberate and is the opposite of the services column beside them. The contact page holds
everything, in full; the footer holds the part worth putting in a footer, in whatever order and
wording suits it, with rows that can be hidden without hiding anything on the contact page. What
keeps the two honest is a standing **notice** in the editor, which never refuses a save.
[ADR 0023](../../90-decisions/0023-the-header-and-footer-are-emitted-once.md)

There used to be a build tool here — `sync_site_contact.py` — that pushed `content/contact.json`
into every page's footer markup, and a fingerprint the frontend reported back so the editor could
say whether the two were in step. Both are deleted. Closing that gap used to be a deploy; there is
no gap now, and changing a footer detail is a save.

---

## Navigation

The header carries six routes. Three pages (Branding & Advertisement, Resource Certifications,
Privacy Policy) and the six services sub-pages are reachable from the footer and from the services
hub — so no page is orphaned while the header stays legible.

Those are rows of `content/chrome.json` now rather than markup, so the shape above is what the
document ships with rather than what the site must have. `audit_pages.py` checks that every page is
reachable from somewhere.

---

## If `check_shared_markup.py` fails

It names the page and the block. For the circuit, almost always: somebody edited a page directly.

```bash
python3 tools/propagate_shared.py --dry-run   # see what differs
python3 tools/propagate_shared.py             # put it back in step
```

If the *intended* change was in the page rather than the template, move it into
`tools/templates/` first — otherwise the next propagate discards it.

If it fails on `lib/body.php`, a `data-` hook has been dropped from the emitter. Put it back; the
message names which one.
