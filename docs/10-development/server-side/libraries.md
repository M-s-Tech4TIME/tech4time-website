# The libraries — `lib/`

**Applies to:** both

Eleven PHP files. What each owns, and which one to open.

None of them is reachable over HTTP: `.htaccess` has `RewriteRule ^lib/ - [F,L]`, and the private
store they read from is outside the document root entirely.

---

## At a glance

| File | Owns | Depends on |
|---|---|---|
| [`html.php`](#htmlphp) **shared** | escaping, and the rich-text sanitiser | — |
| [`store.php`](#storephp) | reading and writing JSON atomically | — |
| [`contract.php`](#contractphp) **shared** | the shape of every editable document | `html` |
| [`careers.php`](#careersphp) | what this side does with a job post | `contract`, `store` |
| [`contact.php`](#contactphp) | what this side does with the contact page | `contract`, `store` |
| [`company.php`](#companyphp) | what this side does with the company profile | `contract`, `store` |
| [`about.php`](#aboutphp) | what this side does with the about page | `contract`, `store` |
| [`home.php`](#homephp) | what this side does with the home page | `contract`, `store` |
| [`services.php`](#servicesphp) | the services index and its six detail pages | `contract`, `store`, `html` |
| [`certifications.php`](#certificationsphp) | the resource certifications page | `contract`, `store`, `html` |
| [`branding.php`](#brandingphp) | the branding & advertisement page | `contract`, `store`, `html` |
| [`privacy.php`](#privacyphp) | the privacy policy | `contract`, `store`, `html` |
| [`seo.php`](#seophp) | the site-wide SEO record, and what is derived from it | `contract`, `store`, `html`, `contact` |
| [`chrome.php`](#chromephp) | the header, footer and dock every page carries | `contract`, `store`, `html`, `services` |
| [`head.php`](#headphp) *(frontend)* | the `<head>` every page emits, and its structured data | `seo` |
| [`svg.php`](#svgphp) **shared** | what a publishable vector file is |
| [`publish.php`](#publishphp) **shared** | how a document is signed and checked on the wire | `private`, `contract` |
| [`publish_client.php`](#publish_clientphp) *(backend)* | sending one | `publish` |
| [`footer-fingerprint.php`](#footer-fingerprintphp) *(frontend, generated)* | what this site's footers currently say | — |
| [`private.php`](#privatephp) | where the secrets are, and key derivation | — |
| [`totp.php`](#totpphp) | RFC 6238 authenticator codes | — |
| [`throttle.php`](#throttlephp) | counting attempts | `private`, `store` |
| [`mailer.php`](#mailerphp) | the one place mail leaves this site | — |
| [`auth.php`](#authphp) | accounts, hashing, sessions, the audit log | `private`, `totp`, `store` |
| [`reset.php`](#resetphp) | the emailed one-time code | `auth`, `mailer`, `throttle` |
| [`admin.php`](#adminphp) | the section registry and page furniture | `auth`, `html` |

Roughly bottom-up: `html` and `store` know nothing about anything; `admin` sits on top of all of it.

---

## Content and rendering

### `html.php`

`h()` · `rt_sanitise_html()` · `rt_safe_href()` · `rt_plain()`

Escaping on output, and the sanitiser that decides what HTML an editor may write.

**Written by hand because there is no DOM extension on this host** — `DOMDocument` does not exist.
So it parses the markup itself, and the way it stays safe without a parser is worth understanding:

> It never passes anything through. It walks the input and, for each tag it recognises, **writes a
> new one** from an allow-list of names and attributes. Anything unrecognised — a tag, an attribute,
> a stray angle bracket — is discarded rather than copied.

So the output cannot contain a construct this file does not explicitly know how to emit. That is a
much smaller thing to get right than trying to spot every dangerous input.

**No `style` attribute, ever.** The CSP is `style-src 'self'`, so an editor that wrote
`style="text-align:center"` would look correct in the admin and do nothing on the public page.
Alignment is a class from a fixed list — which is why `class` is allow-listed *by value*, not merely
by name.

`h()` is what you call on every value you print. Always. Do not assume something was cleaned earlier.

### `store.php`

`store_read()` · `store_write()` · `store_edit()`

Reading and writing a JSON file.

`store_write()` is **atomic**: it writes a temp file in the same directory and renames it over the
target. A rename within a filesystem is atomic, so a visitor loading the page mid-save reads either
the old file or the new one, never half of one. It also keeps one generation of `.bak`.

`store_edit()` is read-modify-write **under a single exclusive lock**. Use it for anything that
counts.

> `store_read()` then `store_write()` is two steps with a gap. That is fine for a person saving a
> form and wrong for a counter: two failures landing together would each read 3, each write 4, and
> one would vanish. That is not a rounding error — it is the attacker's best move.

`store_read()` returns `null` for a missing file **and** for malformed JSON — the right shape for
site copy, where both mean "fall back to defaults" and the page still renders. Callers that must
tell them apart use **`store_state()`**, which answers `ok`, `missing`, `unreadable` or `corrupt`.

`auth_problem()` uses it to refuse rather than present a damaged account file as a fresh install,
and `store_write()` uses it to make sure a damaged file never becomes the `.bak` — the copy that
damage is recovered from. `tools/test_store.py` covers both.

### `contract.php`

**Shared — byte-identical in `tech4time-website-frontend` and `tech4time-website-backend`.**

`CONTRACT_VERSION` · `CONTRACT_DOCUMENTS` · `CONTRACT_BOOKKEEPING` · `contract_path()` ·
`careers_normalise()` · `contact_normalise()` · `contact_defaults()` · `contact_fingerprint()` ·
`contract_sanitise()` · `contract_next_revision()` · …

`contract_path()` gives a document's record path — `content/<name>.json`, the same rule on both
hosts. It exists for the things that have to work over *all* the documents without knowing their
names in advance: the deploy's seed, built by looping over `CONTRACT_DOCUMENTS`, and the editor's
warning when a host has no record for the page being edited. A list of documents kept anywhere but
here is a list that goes out of step, and it did — see
[ci-cd.md](../../20-deployment/ci-cd.md#it-was-a-list-and-the-list-went-out-of-step).

**The shape of a document, and nothing else.** Field lists, the defaults a missing key falls back
to, the normalising that turns whatever arrived into that shape, and the queries that read it. Both
halves must agree on all of it or they are not describing the same job post.

What is deliberately *not* here:

| | goes to | because |
|---|---|---|
| validation with readable messages, the form model, the flag picker | backend | the frontend has no form to validate |
| `JobPosting` / `ContactPage` schema, flag `<picture>`, `tel:` hrefs | frontend | the backend does not render the public page |

The line is: **if the two sides disagreeing about it would corrupt a document, it is here.** If
disagreeing would only make one side's own page look wrong, it is not.

`contact_defaults()` and `contact_office_defaults()` are **the definition of the shape** —
`check_content_model.py` reads the field list out of those functions rather than out of
`content/contact.json`, because the file is one instance of the shape and an optional field that
happens to be absent from it is still a field.

`CONTRACT_BOOKKEEPING` names the fields a document keeps about *itself* — `updated`, `revision`,
`footer_synced`. Nothing edits them and nothing renders them, so both directions of
`check_content_model.py` and the round trip in `test_careers_admin.py` exempt them, and all three
read the one list. They did not, once: `revision` was added, the careers test treated it as a
site-wide setting, posted it on its own, and blanked `cv_form_url` doing so.

`contract_sanitise()` runs every rich field back through `html.php`, driven off
`CAREERS_RICH_FIELDS` / `CONTACT_RICH_FIELDS` rather than a list of its own — so a rich field added
to the contract is sanitised on receipt *by having been added*.

**Bump `CONTRACT_VERSION`** when a change would make a document written by one version render
wrongly under the other: a field renamed, a field's meaning changed, a list that becomes a scalar.
Not for a new optional field older code simply ignores.

### `careers.php`

`careers_load()` · `careers_save()` · `careers_validate()` *(backend)* · `careers_job_posting()` *(frontend)* · …

What **this side** does with the shape `contract.php` defines. `careers_sanitise_html()` and
`careers_safe_href()` are one-line aliases kept from before that code moved to `html.php`, so the
move changed no caller.

`careers_save()` mints the next `revision` itself rather than trusting a caller to. On the backend it
also publishes — a save that wrote the record and forgot to send it is a save nobody would
investigate.

### `contact.php`

`contact_load()` · `contact_save()` · `contact_validate()` · `contact_flags()` *(backend)* ·
`contact_page_schema()` · `contact_flag_picture()` · `contact_reach_href()` *(frontend)* · …

The same division for the contact page.

The footer-drift banner is powered by `contact_footer_in_step()` in `contract.php`, comparing the
details now held against `footer_synced` — which after the split is **what the frontend reported in
the last publish response**, not something this side computed. See
[`footer-fingerprint.php`](#footer-fingerprintphp).

### `company.php`

`company_load()` · `company_save()` · `company_validate()` *(backend)* ·
`company_page_schema()` · `company_picture()` *(frontend)*

The same division again, for the company profile. The shape itself is the largest of the three and
lives entirely in `contract.php`: six repeatable lists — milestones, figures, clients,
photographs, technology, principles — plus the copy around them.

Two things here are worth knowing before changing either half:

**`company_picture()` decides between `<picture>` and a bare `<img>`,** on whether the row carries
a WebP sibling. An SVG or an AVIF has none, and a `<picture>` holding one `<img>` and no `<source>`
is markup claiming a choice is being made when none is. It is emitted on one line with no
whitespace between the tags, because those elements are inline and a newline between them is a
space the browser renders.

**`company_validate()` refuses a figure that does not start with a digit.** `animations.js` counts
it up by reading the number off the front, so `"Over 100"` silently never animates. That is the
kind of thing an editor should say out loud rather than let somebody discover.

### `about.php`

`about_load()` · `about_save()` · `about_validate()` *(backend)* ·
`about_page_schema()` · `about_picture()` · `about_photograph()` ·
`about_logo_lockup()` · `about_reveal_paragraphs()` *(frontend)*

The same division again, for the about page: three repeatable lists — the story sections, the
specialities and the why-us cards — plus the copy around them. The shape is in `contract.php`.

Three things here are worth knowing before changing either half:

**A story row's `layout` chooses between a photograph and a logo pair.** `about_logo_lockup()`
emits both colour variants, each falling back to the lockup that ships with the site — so the row
works with nothing uploaded, and a new mark can be put there without a deploy. With only the light
half given it is used in both modes, deliberately: the alternative is the old logo beside the new
one. Both variants carry the same `alt`, because exactly one is displayed at a time and two
different names for one logo is what a screen reader would otherwise announce.

**Every story row carries two picture records, whichever layout it uses.**
`about_logo_lockup()` draws the pair for a logo row and `about_photograph()` for a photograph one.
The dark half is optional and almost always empty: `.about-split__image` keeps the illustrations on
a white plate in both colour modes by design, so one picture is the normal case. With none uploaded
the markup is exactly what it was before the slot existed — one `<picture>`, no theme-swap classes,
no second element. Uploading one produces the pair and takes the dark half off the white plate. This
is `home_destination_art()` in `home.php` for the row shape this page uses; the two are separate
rather than shared because they take different classes.

**`about_reveal_paragraphs()` puts the scroll markers back on each paragraph.** The prose is one
rich-text field, so the markers cannot be typed into it, and how many paragraphs there are is a
property of the content rather than of the template. It runs on already-sanitised HTML and only
ever adds two valueless attributes to an opening `<p>`; if it matched nothing the paragraphs would
arrive un-animated rather than invisible.

**`tools/apply_reveals.py` no longer governs this page.** It reports and skips any page that builds
part of itself with a loop, which this one now does — see [motion.md](../frontend/motion.md).

### `home.php`

`home_load()` · `home_save()` · `home_validate()` *(backend)* ·
`home_hero_title()` · `home_terminal_lines()` · `home_cta_title()` ·
`home_picture()` · `home_destination_art()` · `home_service_schema()` *(frontend)*

The same division once more, for the home page — **six** repeatable lists, more than any other
document: the hero's badges and tags, the terminal's lines, the technical domains, the service
cards and the Get to Know Us cards. The shape is in `contract.php`.

**Every field on this page is plain text.** There is no rich-text field at all, because every lead
and card body is a single styled `<p>`; see `HOME_ROW_RICH_FIELDS` in `contract.php`. The three
functions that return markup build it from values they escape themselves.

**`home_hero_title()` wraps one phrase of the heading in the accent colour.** The title is stored as
plain text with the phrase to emphasise beside it, so nobody types a tag and the class name stays in
the stylesheet. The split is made on the raw strings and each part escaped afterwards — searching
escaped text for an escaped needle works until the phrase contains an `&`. A phrase that is not in
the title renders the heading plain; the editor refuses to save that, so it is a safety net rather
than the plan.

**`home_terminal_lines()` emits at column 0, and owns the caret.** `.terminal__line` is
`white-space: pre-wrap`, so source indentation would appear as leading spaces on the page. The
blinking cursor is emitted after the last line and is not a row in the document: an operator cannot
delete it, end up with two, or strand it in the middle.

**`home_destination_art()` emits one picture unless a dark half exists.** With none — which is every
card today — the markup is exactly what the page carried before it rendered from a document, with no
theme-swap classes and no second element. The illustrations are line art that the stylesheet keeps
on a light plate in both colour modes, so the dark slot is an option nobody has taken rather than a
gap. Uploading one produces the pair and takes the dark half off that plate.

**`home_service_schema()` generates the `Service` ItemList from the cards.** It was literal markup
maintained beside them, and the two had already drifted: the card read "SOC & CIRT" and the schema
read "SOC and CIRT". A seventh card is now a seventh entry by being a seventh card, and a hidden one
is absent from both.

**`tools/apply_reveals.py` does not govern this page either**, for the same reason.

### `services.php`

`services_load()` · `services_save()` · `services_validate()` *(backend)* ·
the renderers *(frontend)*

**This is the only library that draws more than one page.** One document,
`content/services.json`, holds the services index *and* all six detail pages beneath it. That is
forced rather than chosen: a seventh service has to be addable from the editor, and
`CONTRACT_DOCUMENTS` is a constant in code — so a service cannot be its own document and has to be
a row in a list. See the note over `services_defaults()` in `contract.php`.

Keeping the drawing in one file follows from the same fact. The six detail pages are one template
with six sets of words: they load the same stylesheet,
`assets/css/pages/service-detail.css`, and it contains no per-service rule. A seventh service
needs no new CSS.

**Every field on these pages is plain text.** There is no rich-text field at all — seven pages of
headings, one-line summaries and short list entries, and not one of the 137 solution cards holds a
paragraph anybody would want a link inside. See the `services` branch of `contract_sanitise()`.

**Three things are drawn and never stored**, which is what keeps them from drifting out of step
with the cards they describe:

- the detail card beside each ring is that layer's **first card**, redrawn;
- every node on the ring is a projection of a card — its id, its icon, and its name for the screen
  reader;
- *"12 Solutions"* under a layer heading is the **card count**.

All three were verified against the shipped markup at the migration: 24 layers, 24 rings, no
exceptions.

**A solution card's id is stored, never minted from its name.** Sixty-three of the 137 cards carry
an id that does not follow from the card's title — `sol-cloud-design-private-cloud` on a card
called *"Private Cloud Design & Implementation"*. They were written by hand, and they are the
fragment a saved deep link holds the card by, so `services_identify()` leaves a real id alone and
mints only into empty ones.

**The index's group lists are authored, not derived from the detail pages.** The index says
*"Offensive Security & Penetration Testing (Metasploit, Burp Suite)"* where the detail page says
*"Offensive Security & Penetration Testing"*, and the HRaaS block lists four engagement models
against the detail page's thirty-three resource types. They are two different summaries of one
practice; flattening them into one would lose the shorter.

**`tools/apply_reveals.py` does not govern these pages**, for the reason it does not govern the
home page: it skips anything built with a PHP loop. The reveal markers are emitted by the renderer.

### `certifications.php`

`certifications_load()` · `certifications_save()` · `certifications_validate()` *(backend)* ·
the renderers *(frontend)*

One document, `content/certifications.json`, holding the page's four role groups, the ten role
names spread across them and the fifty-four certifications inside them. Groups, roles and
certifications are each a list: any of them can be added to, reordered, renamed or hidden, and a
role group added in the editor arrives hidden so a half-filled category is never live.

**Every field is plain text.** No rich text anywhere on the page — see the `certifications` branch
of `contract_sanitise()`.

**Three things are drawn and never stored:**

- *"27 certifications"* on a group heading is the count of the certifications **shown** inside it;
- every certification's glyph is one constant, `CERTIFICATIONS_CERT_GLYPH` — all 54 carry the same
  one and always did, so a per-certification icon field would be 54 chances to disagree;
- the `/` between two role names is emitted between them, as markup rather than text, because a
  screen reader should hear two roles and not a fraction.

**The totals in the prose are drawn too.** The lead and the meta description hold
`{certifications}` and `{groups-word}`, which `certifications_fill()` replaces as the page
renders. A typed number goes stale the moment somebody adds a certification, and nothing on the
page or in any check would notice — it is a true sentence that has quietly stopped being true.
Both digit and spelled forms exist because the page writes one of its numbers as a numeral and the
other as a word, and a token that could only produce digits would have reworded the page.
See `CERTIFICATIONS_TOKENS` in `contract.php`.

**The icons come from a second sprite.** A group's glyph is chosen in the editor, so
`inject_icons.py` cannot see it; `certifications_sprite()` emits what the document actually uses,
the same arrangement `services.php` has and for the same reason.

### `branding.php`

`branding_load()` · `branding_save()` · `branding_validate()` *(backend)* · the renderers *(frontend)*

One document, `content/branding.json`, holding the page's logo variants, the files each one offers
for download, and the disclaimer. Variants and their files are each a list: either can be added to,
reordered, renamed or hidden, and a variant added in the editor arrives hidden so a half-filled
card is never live.

**The preview and the download are not the same picture.** `image` is the small thing drawn on the
card; `files[]` is what a visitor came for. On the page as it ships those are an 800px preview and
a 1600px download of the same mark, so collapsing them would either serve the big file to everyone
who merely looks at the page or hand out the small one to everyone who came for the logo.

**Three things are drawn and never stored:**

- the dimensions in a meta line come off that file's own record, so they cannot claim
  *1600 × 570* about a file that is no longer that size — the adjective beside them
  (*"Transparent"*) stays authored, because that part is editorial;
- *"Download PNG"* states the file's own format, read from its extension;
- the glyph on every button is one constant, `BRANDING_DOWNLOAD_GLYPH`.

**The disclaimer is rich text**, and the only rich text on the page — see the `branding` branch of
`contract_sanitise()`. It is a legal notice, and the sentence asking a rights holder to get in
touch is a link waiting to happen.

**The breadcrumb carries its own name.** Unlike the about, company and certifications pages, whose
breadcrumb follows `hero.title`, this page is titled *"Branding Assets & Guidelines"* and named
*"Branding & Advertisement"* everywhere it is linked from. Both are authored; see `meta.breadcrumb`.

**No second sprite.** Nothing on this page picks an icon at run time, so `inject_icons.py` sees
every glyph it draws.


### `privacy.php`

`privacy_load()` · `privacy_save()` · `privacy_validate()` · `privacy_facts()` *(backend)* · the renderers *(frontend)*

One document, `content/privacy.json`, holding the whole privacy policy: twelve headed sections, a
summary callout, a retention table and an address block. It was the last hand-written page on the
site, and the one that most needed not to be — a privacy policy is the page most likely to need a
correction at short notice, and every correction used to need a developer and a deploy.

**Structure is a kind, not markup.** `rt_sanitise_html()` allows nine tags and no heading, no
`<address>` and no `<table>` among them, so structure cannot live in a rich field: somebody typing
`<h3>` into one would watch it disappear on save with no way to tell that from a bug. Each block
instead declares which of six kinds it is — `paragraph`, `list`, `subheading`, `note`, `address`,
`table` — and the renderer owns the markup. See `PRIVACY_BLOCK_KINDS` and `privacy_block()`.

**Every rich field is inline-only**, through `rt_sanitise_inline()`. All of them render *inside* an
element the renderer supplies — a `<p>`, a `<p class="legal__notice">`, an `<address>`, an `<li>` —
so a `<p>` arriving from the editor is not emphasis somebody added, it is a paragraph inside a
paragraph. Pressing Enter in a textarea is how it would arrive, which is not a corner case.

**Three lists deep**, one deeper than any editor before it: sections hold blocks, and a list, an
address or a table holds rows. The verbs carry the parents in the band name — `block-3-up:2`,
`row-3-2-remove:1` — because the index is cast to an int.

**A section's id is its anchor, and an anchor is a promise.** Ids are assigned by
`contract_identify_rows()`, which claims every id somebody already chose *before* it mints anything
new. The one-pass version has a bug that only bites a page whose ids are anchors: a section added
above an existing one with the same heading takes the existing one's fragment, and the incumbent is
silently renamed. Nine of the twelve shipped ids are hand-authored and are not what the slug
algorithm would produce, so there is nothing to recover them from.

**The effective date is never stamped.** `updated` records when the document was last published; an
effective date is a claim about when the *policy* changed. Fixing a typo is not a new policy, so
nothing writes that field but a person.

**The policy band cannot be hidden.** `PRIVACY_BANDS` holds only `cta`. Hiding the policy would
leave a page headed *"Privacy Policy"* with no policy on it, still linked from the footer of all
sixteen pages and still in the sitemap — not a configuration anybody wants. The callout and any
single section can be hidden.

**What it repeats from the contact page is compared, never enforced.** The policy states the
offices, the email and the telephone, and so does `content/contact.json`. `privacy_shared_facts()`
asks by containment whether the policy still states the current values, on a normalised form —
`&nbsp;` and whitespace collapsed, commas dropped, case folded — so it reports a different street
and stays quiet about a different comma. The editor draws it as a standing notice.
**It never refuses a save**: after an office move whichever page you edited first could not be
saved, and an unrelated typo fix would be blocked by an address that drifted months earlier.

### `svg.php`

**Shared — byte-identical in both repositories.**

`svg_sanitise()` · `svg_problem()` · `svg_looks_like()`

What a publishable vector file is. ADR 0019 refused SVG outright and its reasoning was right — an
SVG is a document, and re-encoding does not make it not one. This answers that rather than avoiding
it, twice over.

**It is read and replaced, not checked and kept.** The same rule the raster path follows: the file
is parsed into a DOM, walked against an allow-list, and re-serialised, and what is stored is *that*
— never the bytes that arrived. Anything outside the list makes the whole file refused, with a
sentence naming what was found, because silently dropping an element would hand somebody back a
different logo than the one they published.

**And it is never served as a document.** `/uploads/*.svg` goes out with `Content-Disposition:
attachment` and `default-src 'none'; sandbox` on both hosts, so it downloads and never renders in
this origin. The branding page links to it and no page ever draws one.

**It is idempotent, and that is load-bearing.** `svg_sanitise(svg_sanitise(x))` equals
`svg_sanitise(x)`. The receiving host relies on it: it sanitises what arrived and refuses anything
that is not already its own output — proving the bytes are clean *without changing them*, which it
could not do otherwise, because the file's name is a hash of its contents and both hosts compute it
independently.

**It needs `ext-dom`**, which the live hosts have and Ubuntu's `php-cli` does not. `svg_problem()`
says so plainly and the byte-level refusals still hold without it; CI installs `php-xml`.

### `publish.php`

**Shared — byte-identical in both repositories.**

`publish_problem()` · `publish_fingerprint()` · `publish_envelope()` · `publish_body()` ·
`publish_sign()` · `publish_verify()` · `publish_check_envelope()` · `publish_reason()`

The format content travels in, and only the format — sending is
[`publish_client.php`](#publish_clientphp), receiving is the frontend's `api/publish.php`. Full
description: [the publish API](publish-api.md).

The four checks are not interchangeable, and it is worth knowing which does what:

| check | answers |
|---|---|
| the signature | this came from something holding the key — **not** that it is safe |
| the timestamp | it was sent in the last five minutes |
| the revision | it is newer than what is here — this is what makes a replay a no-op |
| `contract_version` | this side implements the shape it is written in |

The key is `publish.key` in the private store: 32 random bytes, **the same bytes on both hosts**,
never derived from `secret.key` (the two stores have different master keys, so anything derived
would differ by construction). It is never created on demand — see
[`make_publish_key.py`](../../40-reference/tools.md).

### `seo.php`

`seo_load()` · `seo_site()` · `seo_identity()` · `seo_notfound()` · `seo_breadcrumb()` ·
`seo_sitemap_entries()` *(frontend)* · `seo_edit()` · `seo_meta_edit()` · `seo_validate()` ·
`seo_pages()` *(backend)*

One document, `content/seo.json`, and it is deliberately **not** where a page's own metadata
lives. Every page's title, search description, share title, breadcrumb, crawl setting and sitemap
row are in **that page's own document**, in the `meta` band every document has — the About page's
title is in `content/about.json`, beside the About page's content, and always was. What moved is
the editing: one screen, `?s=seo`, edits all of them. See
[ADR 0020](../../90-decisions/0020-page-metadata-is-content.md).

What `content/seo.json` holds is what belongs to the site rather than to any one page: the
Organization / WebSite / ProfessionalService graph, the default share card, the colours, the
`<html lang>`, `robots.txt`'s extra rules, the search-console verification tokens and the web
manifest — plus the 404's own record, because that page renders no content document and never
will.

**`SEO_ROUTES` is code, not content.** It maps a route key to an address, a name and the document
that holds that page's `meta`. The editor cannot add, rename, remove or reorder a row: adding a
page stays a code change, and its card then appears by itself, which is what makes it impossible
to orphan a record or point one at a URL that does not resolve. The service pages are not in it,
because a service is a row of `content/services.json` and a seventh can be added at any time — its
metadata is its row's own `meta` band, so there is nothing to keep in step and a slug rename
cannot orphan anything.

**Sitemap membership is derived from `robots`.** One control, not two, so a page cannot be listed
in the sitemap and asking not to be indexed at the same time — which is a warning raised against
the whole file.

The backend copy adds the two saves. `seo_edit()` writes `content/seo.json`; `seo_meta_edit()` and
`seo_service_meta_edit()` write **one band of another document** under `store_edit()`'s lock,
because the screen holds one band of a document whose other twenty were never in the form. A
whole-document rebuild there would empty the page.

### `chrome.php`

`chrome_load()` · `chrome_header()` · `chrome_footer()` · `chrome_dock()`

One document, `content/chrome.json`, holding the furniture around every page: the header's logo
and nav, the footer's four columns, and the small-screen dock. It was literal markup in seventeen
page files — about 6,800 lines of duplication kept in step by a propagation script — and the
duplication had already produced three live defects: a footer service list that disagreed with
`content/services.json`, a service that could never appear in the footer at all, and phone numbers
that went stale because a script had to be run by hand before a deploy.

**A link points at a route, never at a URL.** Every destination is a key of `chrome_targets()` in
`contract.php` — `about`, `service:cybersecurity` — built from `SEO_ROUTES` and
`content/services.json`. There is no way to type an address into a nav link, so a nav link cannot
404, in the one component that appears on every page. An empty label means *whatever that page
calls itself*, which is how renaming a page in `?s=seo` renames it in the header, the footer and
the dock at once.

**Two columns of the footer store nothing.** The services list is read from
`content/services.json`, so a seventh service appears by itself and a hidden one goes; the social
links are read from the SEO document's `sameas` rows, so a profile URL is changed in one place and
the footer cannot disagree with the Organization graph.

**The footer's contact rows deliberately are not.** They are the footer's own — added, worded,
ordered, shown and hidden on the footer screen — and owe nothing to `content/contact.json`. The
contact page holds every detail in full; a footer holds the part worth putting in a footer. What
keeps the two honest is a notice the editor draws, never a refusal, for the reason the privacy
policy's duplicated facts are reported rather than forbidden: requiring the two to agree before
either could be saved means that after an office move, whichever page you edited first could not
be saved.

**A missing file is not an error.** `chrome_load()` fills from `chrome_defaults()`, which is the
site's own header, footer and dock as they shipped, extracted from the markup rather than typed.
A host that has never received a publish still renders a correct page — the failure that avoids is
the whole site losing its navigation because one file did not arrive.

### `head.php`

`seo_head()` · `seo_jsonld()` · `seo_graph()` · `seo_offices()` · `seo_lang()` — frontend only.

The `<head>` of every page, emitted once. It used to be pasted: seventeen copies of between 222
and 308 lines, about 4,250 lines in all, with no propagation tool and no drift check over any of
it — `check_shared_markup.py` covers the header, footer, dock and hero-circuit, and the head was
never in that set.

It drifted exactly as that guarantees. The Organization graph carried three office addresses and
four telephone numbers as literal JSON in sixteen of the seventeen heads; only the contact page
rendered them from `content/contact.json`. Editing an office in the admin left sixteen pages
advertising the old one, and `sync_site_contact.py` was written to paste the new values back
before a deploy. The graph is built here now, on the request, from the document that owns the
facts — and that half of `sync_site_contact.py` is gone.

A page hands in **its own address** and **its own `meta` band**:

```php
seo_head('/pages/about/', $data['meta'], ['pages/about.css'], $data['updated']);
seo_jsonld('/pages/about/', $data['meta'], $data['updated']);
```

The address rather than a key, because a service page's address is a row's slug and is in no
constant — and because the canonical is then the argument itself, which `audit_pages.py` checks
against the directory the file actually sits in. A miscopied argument is the one mistake an
emitted head makes possible, and that is the check that catches it.

Nothing editable reaches the head unescaped, and the canonical, the CSP, the favicon list, the
font preload and the stylesheet order are code. An editor able to break the Content Security
Policy is a hazard, not a feature.

### `publish_client.php`

**Backend only.** `publish_push()` · `publish_endpoint()`

Sends one document and returns what the editor should show. Never throws for a network problem: an
unreachable site is a thing to report in the editor, not a stack trace over a form somebody has just
filled in.

The certificate is verified and there is no option to turn that off; redirects are not followed,
because a redirect on this route would post a signed document wherever it pointed.

`$T4T_PUBLISH_URL` overrides the endpoint — how `test_publish.py` points it at a local server.

### `footer-fingerprint.php`

**Frontend only, and generated** by `tools/sync_site_contact.py`. One constant,
`FOOTER_FINGERPRINT`.

The footer's contact details are literal markup in all sixteen pages, because the project forbids
runtime partials. So the moment somebody edits an address in the admin, the contact page is right
and the footers are behind — until the pages are rebuilt and deployed.

This records the fingerprint the footers were last rebuilt **for**. It used to be stamped into
`contact.json`, which stopped being possible when the backend took ownership of that file: the
frontend's copy is a replica, and the next publish overwrites anything written into it. So the
frontend keeps its own record, reports it in every publish response, and the backend compares. The
side that knows what its own footers say is the side that answers.

---

## The sign-in

Full design: *authentication.md* (in tech4time-website-backend).

### `private.php`

`t4t_private_dir()` · `t4t_private_path()` · `t4t_master_key()` · `t4t_key()` · `t4t_assert_outside_document_root()`

Where the secrets are, and where every key comes from.

`t4t_private_dir()` resolves the store, **refuses if it is inside the document root**, creates it
0700, and caches the result. The containment check runs *before* `mkdir` — a safety check that
leaves a new folder in the web root on its way out is doing the opposite of its job — and again on
the resolved path, because `realpath()` follows symlinks.

`t4t_master_key()` creates `secret.key` with `fopen(…, 'x')`, which fails if the file exists. That
makes "create only if absent" one atomic step, and the creation path is written to **lose** a race
rather than win one: regenerating the key would invalidate every stored password at a stroke.

`t4t_key($purpose)` derives a per-purpose key by HMAC. The key that peppers passwords is not the key
that hashes reset codes, and neither is the key that will sign a publish request — so a weakness in
how one is used cannot be carried into another.

### `totp.php`

`totp_secret()` · `totp_code()` · `totp_verify()` · `totp_uri()` · `totp_format()` · base32 both ways

RFC 6238, about ninety lines: base32, HMAC-SHA1 dynamic truncation, a 30-second step, 6 digits, and
one step of drift either side for a phone clock that is slightly out.

Hand-written for the same reason `html.php` is — there is nothing to install on this host and no
build step to install it with. **It is checked against all six test vectors published in the RFC**,
including the one past 2^32 that catches a 32-bit counter. That is the only reason to trust an
implementation like this one.

### `auth.php`

The largest file here. Accounts, hashing, sessions, the audit log, and the setup token.

```
accounts    auth_accounts  auth_find  auth_put  auth_defaults  auth_has_accounts
passwords   auth_pepper  auth_password_hash/verify/needs_rehash/dummy/problem
recovery    auth_recovery_make/hash/use
sessions    auth_boot  auth_session_user  auth_login  auth_logout
            auth_invalidate_sessions  auth_sweep_sessions  auth_end_session
requests    auth_csrf  auth_check_csrf  auth_fingerprint
            auth_is_https  auth_is_local  auth_is_loopback
the log     auth_log  auth_recent
setup       auth_setup_token  auth_setup_token_check  auth_setup_done
gates       auth_problem  auth_attempt  auth_second_factor
```

> **`auth_second_factor()` takes the account by reference.** It spends a recovery code and advances
> the TOTP counter on the caller's copy. It took it by value once, and `auth_login()` wrote its own
> stale copy over the top one line later — silently restoring the spent code and the old counter.
> Recovery codes worked forever and a captured code could be replayed. If you refactor here, keep
> the reference.

### `throttle.php`

`throttle_ip()` · `throttle_key()` · `throttle_fail()` · `throttle_retry_after()` · `throttle_quota()` · `throttle_clear()`

Counting attempts, so guessing costs something. Five failures are free, then each waits longer than
the last, capped at `THROTTLE_MAX_BLOCK` (one hour).

`throttle_ip()` reads `REMOTE_ADDR` and **never** `X-Forwarded-For`, which a stranger sets.
`throttle_key()` HMACs the identifier, so usernames never land on disk in the counter file.

### `reset.php`

`reset_begin()` · `reset_verify()` · `reset_finish()` · `reset_forget()` · `reset_tries_left()`

The emailed one-time code: ten minutes, five guesses, single use, and bound to the browser that
asked for it. Rationed three times an hour per account, five per address, twenty overall — the last
because cPanel caps outbound mail per hour and somebody hammering the page could use the allowance
up, stopping the genuine reset from being delivered.

### `mailer.php`

`mail_send()` · `mail_problem()` · `mail_header_safe()`

The one place mail leaves this site, so the envelope sender is set in one place. It sends with
`-f no-reply@tech4time.bd` and retries once without it, because some hosts refuse the flag outright.

> The `-f` envelope sender is what SPF and DMARC are checked against. The `From:` header is not.

---

## The admin shell

### `admin.php`

`admin_start_session()` · `admin_require_auth()` · `admin_section()` · `admin_head()` / `admin_foot()` · `admin_shell_head()` / `admin_shell_foot()` · `admin_icons()` · `admin_csrf()`

The section registry, the icon rail, the page furniture, and the gate.

`ADMIN_SECTIONS` is the registry the rail draws itself from — adding an editable page is a row here
plus a file beside the others. `ADMIN_PAGE_SECTIONS` names the subset that edits a page of the
website, so anything counting "the pages you can edit" asks here rather than filtering the registry
by hand in three places.

`admin_shell_*` are the furniture for the pages that have **no** session yet — login, forgot, reset,
setup. They exist because `admin_head()` fatals on a section that is not in the registry, and those
pages are not sections.

*adding-an-editor.md* (in tech4time-website-backend)

---

## Adding a library

Rare. Most things belong in an existing file.

If you do: a header comment saying **what it owns and why it exists**, `declare(strict_types=1)`,
functions prefixed with the file's concern, no global state beyond a `static` cache, and no output.
Then add it to the table at the top of this page — `check_docs.py` fails until you do.
