# Adding a page

**Applies to:** frontend

From nothing to a page that passes every check. About twenty minutes.

---

## There is no static half any more

Every page on this site is `.php` and renders from a document in `content/`. `404.html` was the last
static one and became `404.php` when the head moved into `lib/head.php`; nothing static remains, and
a new page should not be the first.

That is not a style preference. A static page's title and description can only be changed by a
developer with a deploy, and the site's own SEO screen would have nothing to show for it — the page
would be the one row on `?s=seo` that says "edit the file". Making a page dynamic means giving it a
content model and an editor: see *adding-an-editor.md* (in tech4time-website-backend).

**A page does not always need a document of its own.** The six service pages are rows of
`content/services.json`, one template with six sets of words, because a seventh service has to be
addable from the editor and `CONTRACT_DOCUMENTS` is a constant in code. If the page you are adding
is another of something that already exists, add a row rather than a document.

**A row is a URL.** The six service pages each keep a real `pages/services/<slug>/index.php`,
because `audit_pages.py`, `check_shared_markup.py` and `inject_icons.py` all enumerate pages by
walking the filesystem, and a page they can see is a page they check. A service added in the admin
at a slug with **no** directory is served by `pages/services/detail.php` instead: `.htaccess`
rewrites `/pages/services/<slug>/` onto it when the request matches no file and no directory, and
`tools/dev-router.php` carries the same route for the local server. The page it draws is
byte-for-byte the page a directory would have drawn — same renderer, same chrome — and a slug the
document does not have, or whose service is hidden, answers 404.

So adding a service needs no developer at all, and it gets an SEO card on `?s=seo` by itself,
because that screen reads the services document rather than a list of keys. What a directory still
buys is the filesystem walk: `audit_pages.py` reads the document and audits directory-less services
through `detail.php` anyway, but `check_shared_markup.py` and `inject_icons.py` have nothing to look
at. Promoting a service to its own directory is optional tidying, not a prerequisite.

---

## 1. Write the `<main>`

Just the `<main>` element — the header, footer and scripts come from the templates, and the whole
`<head>` is emitted by `lib/head.php`.

```html
<main id="main">
  <section class="page-band">
    <div class="container">
      <h1>Managed Detection and Response</h1>
      <p class="lede">…</p>
    </div>
  </section>
  …
</main>
```

Rules the audit will hold you to: exactly one `<h1>`, headings in order with no level skipped, an
`alt` on every image, and an accessible name on every control.

Save it somewhere temporary — `/tmp/mdr-main.html`.

## 2. Write a spec

```json
{
  "out":         "pages/services/managed-detection/index.php",
  "main":        "/tmp/mdr-main.html",
  "route":       "/pages/services/managed-detection/",
  "document":    "mdr",
  "page_css":    "service-detail",
  "nav_current": "/pages/services/"
}
```

**There is no `title`, `description` or `canonical` here.** The first two are content: they are
seeded in that document's `*_defaults()` in `lib/contract.php` and edited from then on at
`https://admin.tech4time.bd/?s=seo`. The canonical is derived from `route` and is not editable
anywhere — one page, one address.

`page_css` is optional and names a file in `assets/css/pages/`. `nav_current` marks the header link
that should show as active.

## 3. Assemble

```bash
python3 tools/assemble_page.py /tmp/mdr-spec.json
```

This composes the page from `tools/templates/` so the shared blocks are byte-identical by
construction, and `check_shared_markup.py` passes on the first try. It prints the four things it
cannot do for you; they are steps 4 and 5 below.

> **Once the page exists, edit the file directly.** Re-running `assemble_page.py` discards hand
> edits to `<main>`. It is for creating a page, not maintaining one.

## 4. Give it a model

In `lib/contract.php` — **and the same file in tech4time-website-backend, byte-identical**:

1. the document in `CONTRACT_DOCUMENTS`, a `*_defaults()`, a `*_TEXT_FIELDS`, a `contract_normalise()`
   arm and a `contract_sanitise()` branch. The `default` of each throws, deliberately, so a document
   that is half-registered fails loudly rather than silently rendering nothing;
2. a `meta` band in its defaults — `title`, `description`, `share_title`, `breadcrumb`, `robots`,
   `changefreq`, `priority`. `contract_meta_defaults()` fills the shape; you supply the words;
3. **a `SEO_ROUTES` entry**: `'mdr' => ['/pages/services/managed-detection/', 'Managed Detection',
   'mdr']`. Without it the page has no canonical, no sitemap row and no card on the SEO screen —
   and none of those fails loudly, which is why the tool prints a reminder.

Then a model file in `lib/`, named for the document, with a `<document>_load()` function — copy the
shape from `lib/about.php` — and the section that edits it in the backend.

The description's limits live in the model too, and are read from there rather than retyped:
`SEO_TITLE_MAX` is 65, `SEO_DESC_MIN` 50 and `SEO_DESC_MAX` 165, with 150–160 the *ideal* the editor
hints at and neither it nor `audit_pages.py` refuses. Uniqueness across the site is required by
both.

## 5. Icons and reveals

```bash
python3 tools/inject_icons.py           # inline the symbols the page references
python3 tools/apply_reveals.py --write  # mark the scroll-reveal targets
```

## 6. Link it up

A page nothing links to is a page nobody finds, and `audit_pages.py` reports it as orphaned.

- **A services sub-page** → add it to the services hub, and to the footer via
  `tools/templates/footer.html` + `propagate_shared.py`
- **A top-level page** → the header nav in `tools/templates/header.html`, if it belongs there. The
  header carries six routes and stays legible on purpose; the footer is where the rest live.

**The sitemap needs no edit.** `sitemap.php` walks `SEO_ROUTES` and the services document, reads
each page's `meta` for `changefreq` and `priority`, and omits anything set to `noindex`. There is no
list to keep in step — that list used to be `SITEMAP_STATIC` and its ten `lastmod` dates were months
stale before anybody noticed.

## 7. Check

```bash
python3 tools/audit_pages.py            # SEO, a11y, structure, links, canonicals
python3 tools/test_sitemap.py           # the page is in the sitemap, once
python3 tools/check_shared_markup.py    # no drift
python3 tools/inject_icons.py --check
python3 tools/check_contrast.py
python3 tools/check_content_model.py    # every field the model defines is rendered
python3 tools/check_docs.py             # the repository map lists every page
```

Then look at it:

```bash
python3 tools/serve.py
python3 tools/check_dark_mode.py        # both themes
python3 tools/test_motion.py            # nothing left hidden
```

## 8. Document it

Add the page to the table in
[00-orientation/repository-map.md](../../00-orientation/repository-map.md). `check_docs.py` fails
until you do — deliberately, because an undocumented page is one nobody knows to maintain.

---

## Page-specific CSS

Only when the styles are genuinely used on one page.

```
assets/css/pages/<name>.css      ← create
```

Link it via `page_css` in the spec, which becomes the third argument of the page's `seo_head()`
call. It loads **last**, after `animations.css`, so it can override anything. Shared furniture
belongs in `components.css` instead — a second page needing the same card is the signal to move it.

If you later change that file, bump its version query in the `seo_head()` call — the home page
carries `pages/home.css?v=2` for exactly this reason. `check_cache_bust.py` refuses a release where
a changed stylesheet is still served from an unchanged URL, and it reads both the `seo_head()` calls
and `HEAD_STYLES`.

---

## The checklist

- [ ] One `<h1>`, headings in order
- [ ] `alt` on every image, accessible names on every control
- [ ] The page is `.php` and renders from a document
- [ ] A `meta` band in its defaults: title ≤ 65, description 50–165 and unique
- [ ] **A `SEO_ROUTES` entry** — no canonical, no sitemap row and no SEO card without it
- [ ] `lib/contract.php` copied to the backend, `check_shared_lib.py --update` run
- [ ] Linked from the hub, the footer, or the header
- [ ] Icons injected, reveals applied
- [ ] Listed in `repository-map.md`
- [ ] Every check passes
