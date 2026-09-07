# Content schemas

**Applies to:** both

The **six** JSON files the dynamic pages render from, field by field. Twelve of the sixteen pages,
because `content/services.json` carries seven of them.

**The defaults functions are the definition of the shape**, not these files — `careers_defaults()`,
`contact_defaults()`, `company_defaults()`, `about_defaults()`, `home_defaults()` and
`services_defaults()`, all in **`lib/contract.php`**, which the two repositories hold
byte-identical. (They lived in `lib/careers.php` and `lib/contact.php` until the repository split;
the prose here said so for some time after it stopped being true.) A JSON file is one instance of a
shape, and an optional field that happens to be absent from it is still a field.

Changing a shape means changing three things together — the model, the form and the renderer.
[content-model.md](../10-development/server-side/content-model.md).

---

## `content/careers.json`

```json
{
  "cv_form_url": "https://forms.gle/…",
  "updated": "2026-08-23T02:10:00+00:00",
  "meta": { … },
  "jobs": [ { … } ]
}
```

| Field | Type | |
|---|---|---|
| `cv_form_url` | string | one link for the whole page, for speculative applications |
| `updated` | string | ISO 8601, written on save. Bookkeeping — nothing renders it |
| `meta` | object | everything the `<head>` says about this page — see [The `meta` band](#the-meta-band-on-every-document). This document **gained** one: the careers page's title and description were literal strings in the page file, and the only two on the site nobody could change |
| `jobs` | array | job posts, **in display order** |

### A job

| Field | Type | |
|---|---|---|
| `id` | string | generated on creation, never typed |
| `title` | string | the role |
| `employment_type` | string | full-time, part-time, contract… |
| `work_arrangement` | string | on-site, hybrid, remote |
| `location` | string | |
| `salary` | string | free text — may be a range or blank |
| `posted` | string | date |
| `closes` | string | date; drives `careers_open_jobs()` |
| `status` | string | `shown` or hidden |
| `apply_url` | string | the role's own application form — applications never post to this site |
| `about` | rich text | the description |
| `responsibilities` | rich text | |
| `requirements` | rich text | |
| `must_have` | rich text | |
| `certifications` | rich text | |
| `offers` | rich text | what the company offers |

Rich-text fields go through `rt_sanitise_html()` on save. `careers_job_posting()` emits `JobPosting`
structured data from these.

---

## `content/contact.json`

```json
{
  "updated": "…",
  "footer_synced": "…",
  "meta":    { … },
  "hero":    { "title": "…", "subtitle": "…" },
  "form":    { "title": "…", "lead": "…", "subject_hint": "…", "note": "…",
               "service_types": [] },
  "reach":   { "status": "shown", "title": "…", "items": [] },
  "offices": { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…",
               "items": [] }
}
```

| Field | |
|---|---|
| `updated` | ISO 8601, written on save. Bookkeeping |
| `footer_synced` | the fingerprint of the contact details as last pushed into the pages' footers. Drives the drift banner — [shared-markup.md](../10-development/frontend/shared-markup.md) |
| `meta` | everything the `<head>` says about this page — see [The `meta` band](#the-meta-band-on-every-document) |
| `hero` | the page's heading and subheading |
| `form` | the enquiry form's copy, and `service_types` — the subject options offered |
| `reach` | direct contact methods. `status` switches the whole band off |
| `offices` | the office list. `status` switches the whole band off |

**A band's `status` and a row's are separate switches, and both are honoured.**
`contact_shown_reach()` and `contact_shown_offices()` answer for both, which is why the structured
data cannot advertise a band the page does not draw. Only these two bands have a switch: the banner
and the enquiry form do not, because a contact page with no way to make contact is not a page
anybody meant to publish.

### A reach item

| Field | |
|---|---|
| `icon` | an icon name from `ADMIN_ICONS` |
| `label` | "Phone", "Email", … |
| `type` | `phone`, `email`, `url` or `text` — decides how `contact_reach_href()` links it |
| `values` | array of strings; several numbers under one label |
| `text` | free text, when `type` is `text` |
| `status` | `shown` or `hidden` — `contact_shown_reach()` filters on it |

### An office

| Field | |
|---|---|
| `id` | generated on creation, never typed |
| `name` | the city or office name |
| `flag` | a slug naming a flag that ships with the public site — `bangladesh`, `belgium`, `malaysia`. Cannot grow without a deploy, which is what `image` is for |
| `image` | an uploaded flag: `src`, `webp`, `width`, `height`, the same record a company logo uses. **Wins over `flag` when set.** Paths are checked against `CONTRACT_IMAGE_ROOTS` |
| `address` | |
| `phones` | array of strings |
| `hours` | opening hours |
| `languages` | array of strings |
| `status` | `shown` or hidden — `contact_shown_offices()` filters on it |
| `schema` | `street`, `locality`, `region`, `postal_code`, `country` — for `PostalAddress` structured data |

`contact_page_schema()` emits `ContactPage` and `PostalAddress` from these, and so does the
`Organization` graph at the top of `pages/contact/index.php` — `contact_addresses()` and
`contact_points()` are spliced into it. That block used to write the three offices out by hand,
which meant hiding an office took its card off the page and left its address being advertised to
Google. `contact_points()` emits one point per **phone**, not per office: an office listing three
numbers had two of them reachable on the page and invisible to a search engine.

---

## `content/about.json`

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":        { … },
  "hero":        { "title": "…", "subtitle": "…" },
  "story":       { "status": "shown", "items": [] },
  "specialties": { "status": "shown", "title": "…", "interval": 10000, "items": [] },
  "whyus":       { "status": "shown", "title": "…", "items": [] },
  "cta":         { "status": "shown", "title": "…", "label": "…", "href": "…", "icon": "…" }
}
```

| Field | |
|---|---|
| `updated` · `revision` | bookkeeping — see *Rules that apply to both* |
| `meta` | everything the `<head>` says about this page — see [The `meta` band](#the-meta-band-on-every-document) |
| `hero` | the page's heading and subheading. No `status`: a page with no title is not a page with a section switched off |
| `story` | the image-and-prose sections. `status` switches the whole run of them off |
| `specialties` | the slideshow. `interval` is milliseconds, clamped 2000–60000 |
| `whyus` | the grid of short reasons |
| `cta` | the closing band and its one button |

**`story` has no `title` of its own.** Every heading on that part of the page belongs to a row,
which is what lets a section be added, reordered or hidden on its own.

### A story section

| Field | |
|---|---|
| `id` | minted from the heading; also the `<h2>`'s id and what the section's `aria-labelledby` points at |
| `heading` | the section's `<h2>` |
| `body` | sanitised HTML — one or two `<p>`. The only rich field on this page |
| `layout` | `photograph`, or `logo` for the light/dark wordmark lockup |
| `side` | `left` or `right`; `right` renders `.about-split--reverse` |
| `alt` | what the picture shows. Required even for `logo`, because the lockup is what gets announced |
| `image` | `{ src, webp, width, height }`. The picture, or the light half of a logo pair |
| `image_dark` | the dark half of a logo pair. Only `layout: "logo"` draws it |
| `status` | `shown` or `hidden` |

**A `logo` row draws a pair, and each half falls back on its own:**

| uploaded | light mode | dark mode |
|---|---|---|
| nothing | the shipped lockup | the shipped lockup |
| `image` only | the upload | **the same upload** |
| both | `image` | `image_dark` |

The middle row is the one worth explaining. Falling back to the shipped *dark* logo there would
put the old mark beside the new one, which is the one outcome nobody wants from "we changed our
logo". A new light logo may read poorly on a dark background; the previous brand does not read
poorly, it is wrong. The editor says so and offers the second slot.

**This is the logo in that section and nowhere else.** The header, the footer, the browser tab,
the social share card and `Organization.logo` in the structured data are shared markup and build
artefacts, not content, and still need a developer and a deploy.

A picture record is kept rather than cleared on a row whose layout is not `logo`, so switching back
does not lose it — which is also why `about_images()` counts both halves when the unused-upload
sweep asks what is in use.

**The light and shaded backgrounds alternate by position, not by a stored field.** It is a rhythm
down the page, so a reordered or added section keeps the stripe instead of carrying a stale copy
of it.

### A speciality, and a why-us card

The same shape.

| Field | |
|---|---|
| `id` | minted from the title |
| `icon` | a name from `ABOUT_ICONS`. Anything else is dropped on save and on receipt |
| `title` | the card's heading |
| `text` | one paragraph, plain text |
| `status` | `shown` or `hidden` |

**The specialities repeat the six service names that also appear on the home page and
`/pages/services/`.** All three now have a content source, so the taxonomy lives in **three**
documents — `content/about.json`, `content/home.json` and `content/services.json` — and still has
no owner. The home page's `Service` ItemList used to be a fourth and is generated from
`services.items`; each detail page's `Service` block used to be another and is now generated from
its own layers.

Bringing the services pages under management did not reconcile the three, and deliberately so: they
are three different summaries at three different lengths, and the known disagreement is real
content rather than drift — `content/home.json` calls one service *Human Resource Provision* where
the services index calls it *HRaaS*. Nothing enforces that they agree, and a rename in one is still
worth a look at the other two.

**About's why-us cards and the company profile's `principles` express overlapping ideas** — Robust
Security and Security First, Client-Centric Approach and Client Partnership — and are deliberately
separate: different wording, different icons, different markup, on different pages. Editing one is
worth a look at the other.

## `content/home.json`

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":         { … },
  "hero":         { "title": "…", "accent": "…", "cta_label": "…", "cta_href": "…" },
  "badges":       { "status": "shown", "items": [] },
  "tags":         { "status": "shown", "items": [] },
  "terminal":     { "status": "shown", "title": "…", "summary": "…", "items": [] },
  "capabilities": { "status": "shown", "title": "…", "lead": "…", "items": [] },
  "services":     { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…",
                    "schema_name": "…", "schema_description": "…", "items": [] },
  "destinations": { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…", "items": [] },
  "cta":          { "status": "shown", "icon": "…", "title": "…", "text": "…",
                    "label": "…", "href": "…" }
}
```

| Field | |
|---|---|
| `updated` · `revision` | bookkeeping — see *Rules that apply to both* |
| `meta` | everything the `<head>` says about this page — see [The `meta` band](#the-meta-band-on-every-document) |
| `hero` | the page's only `<h1>`, the phrase drawn in the accent colour, and one button. No `status`: a front page with no heading is not a page with a section switched off |
| `badges` · `tags` | `{ id, icon, label, status }` — the pills under the heading |
| `terminal` | the decorative SOC console. `summary` is the one line a screen reader is given instead of it |
| `capabilities` | `{ id, icon, title, status }` — the technical domains |
| `services` | `{ id, icon, title, text, href, label, link_hint, status }` |
| `destinations` | the same plus `alt`, `image{}` and `image_dark{}` |
| `cta` | the closing panel. `title` holds a newline, which becomes the `<br>` |

**Six lists, the most of any document here.** `HOME_LISTS` names them and `home_normalise()` drives
itself off that, so a seventh is added by being added there.

**A terminal line is `{ id, kind, tone, prompt, text, status }`.** `kind` is `command` or `output`;
`tone` is `plain`, `success` or `alert` and is ignored on a command. **The blinking caret is not a
row** — it is emitted after the last line by `home_terminal_lines()`, so it cannot be deleted,
duplicated or stranded in the middle.

**`hero.accent` is a phrase, not markup.** The renderer wraps its first exact occurrence in the
title. It has to match exactly, capitals included; the editor refuses a save where it does not, and
the page falls back to a plain heading if one ever gets through.

**`link_hint` is the visually-hidden tail on a card's link** — "for Cybersecurity". It is a field
rather than something derived from the title, because the wording differs from it: the card titled
"IT Consultancy & Training" reads "and", not "&".

**`schema_name` and `schema_description` are the only fields here nobody sees on the page.** They
name the `Service` ItemList in the `<head>`. Each service's own entry is generated from its card, so
there is no second copy of the six to keep true — there was, and it had drifted.

**Both halves of a destination picture are counted by `home_images()`**, so an unused-file sweep
cannot offer to delete a dark image the moment it is uploaded.

## `content/services.json`

**One document, seven pages.** The services index *and* every service page under it. That is forced
rather than chosen: a seventh service has to be addable from the editor, and `CONTRACT_DOCUMENTS` is
a constant in code — so a service cannot be its own document and has to be a row in a list.

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":     { … },
  "hero":     { "title": "…", "subtitle": "…" },
  "nav":      { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…", "items": [] },
  "blocks":   { "status": "shown", "items": [] },
  "ossf":     { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…", "items": [] },
  "cta":      { "status": "shown", "title": "…", "text": "…",
                "label": "…", "href": "…", "icon": "…" },
  "services": { "items": [] }
}
```

A `nav` row is `{ id, block, icon, title, text, status }` — `block` names the **block** it scrolls
to, which is not the service's slug: the HRaaS block is `id="hraas"` and its service is
`hr-solutions`.

A `blocks` row is `{ id, service, icon, title, intro, status, groups[], buttons[] }`, where a group
is `{ id, title, width, items[], status }` and a button is
`{ id, label, href, icon, style, status }`. `service` names the row in `services.items` the block
belongs to.

An `ossf` row is `{ id, icon, title, text, status }`. The number beside a stage is its position.

A `services` row is **one whole page**:

```json
{
  "id": "…", "slug": "…", "name": "…", "status": "shown",
  "schema_type": "…", "schema_description": "…",
  "meta":   { … },
  "hero":   { "title": "…", "subtitle": "…" },
  "core":   { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…",
              "note": { "text": "…", "link_label": "…", "link_href": "…" },
              "items": [] },
  "layers": { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…",
              "labels": { "purpose": "…", "features": "…", "tags": "…",
                          "count_one": "…", "count_many": "…" },
              "items": [] },
  "cta":    { "status": "shown", "title": "…", "text": "…",
              "label": "…", "href": "…", "icon": "…" }
}
```

A `layers` row is `{ id, icon, title, tab_text, text, hub_label, status, cards[] }` and a card is
`{ id, icon, name, category, desc, purpose, features[], tags[], status }`.

**Four things are drawn and never stored.** They are what keeps them from drifting out of step with
what they describe, which is what they are for:

| Drawn | From |
|---|---|
| the card beside each ring | that layer's **first** shown card |
| every node on the ring | a card — its id, its icon, and its name for the screen reader |
| *"12 Solutions"* under a layer heading | the shown card count, in `count_one` / `count_many` |
| the `Service` graph's offer catalogue | the layer list — empty when the band is hidden |

**A solution card's `id` is stored, never minted from its name.** Sixty-three of the 137 that
shipped carry an id that does not follow from the card's title —
`sol-cloud-design-private-cloud` on a card called *"Private Cloud Design & Implementation"*. They
were written by hand, and they are the fragment a saved deep link holds the card by.

**A layer's `id` is stored bare and rendered with a prefix** — `reactive` becomes
`id="layer-reactive"`, which is what the tab links to.

**`labels` are per page, not per card.** Every card on the cloud page is headed *"What it
includes"* and *"Technologies"*. An empty `features` label means the page's cards show no ticked
list at all; three of the six do not.

**`schema_type` is not the name.** It is schema.org's word for the practice, and on two of the six
it differs: HRaaS is `IT Staffing`, IT Consultancy & Training is `IT Consulting`.

**The index's group lists are authored, not derived from the detail pages.** The index says
*"Offensive Security & Penetration Testing (Metasploit, Burp Suite)"* where the detail page says
*"Offensive Security & Penetration Testing"*, and the HRaaS block lists four engagement models
against that page's thirty-three resource types. They are two summaries of one practice at two
lengths; flattening them into one would lose the shorter.

**Hiding a service hides the whole of it.** Its page answers 404, and its block and the nav card
that jumps to that block both leave the index — otherwise hiding would only produce a broken link.
The alternating tint follows the blocks a visitor can **see**, so the stripe stays correct when one
is switched off.

**There are no pictures on any of these pages, and no rich text anywhere in the document.**

## `content/certifications.json`

The resource certifications page: role groups, the roles each covers, and the certifications the
people in those roles hold. **A list inside a list** — the only document shaped that way.

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":     { … },
  "hero":     { "title": "…", "subtitle": "…" },
  "certs":    { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…", "items": [] },
  "cta":      { "status": "shown", "title": "…", "text": "…", "items": [] }
}
```

### A role group

```json
{
  "id":     "security-analyst",
  "slug":   "security-analyst",
  "icon":   "shield-halved",
  "blurb":  "…",
  "status": "shown",
  "open":   true,
  "roles":  [ { "id": "…", "name": "Security Analyst", "status": "shown" } ],
  "items":  [ { "id": "…", "name": "CompTIA Security+",  "status": "shown" } ]
}
```

`slug` is the anchor the group's `<details>` carries, so `#security-analyst` links to it. It is
minted from the **first role name** — a group has no title of its own, it *is* its roles — and then
frozen, because a link into the page is a promise. A group still carrying the placeholder id it was
created with is the one exception: that was never a real address.

`open` is the group that starts expanded. More than one may be, and none has to be.

### Three things are NOT in the file

- **the count on a group heading** — *"27 certifications"* is however many are **shown** in it;
- **a certification's icon** — all of them carry `certificate`, so it is a constant in the renderer;
- **the `/` between role names** — markup, emitted between them, and hidden from a screen reader so
  two roles are not read as a fraction.

### The totals in the prose are not in the file either

The lead and the search description may hold `{certifications}`, `{groups}` and `{roles}`, and the
renderer replaces each with the live figure as it draws. Every one has a `-word` form as well —
`{groups-word}` is *"four"* — because the page writes one of its numbers as a numeral and the other
as a word, and a token that could only produce digits would have reworded the page the first time
it rendered.

A typed number is wrong the moment somebody adds a certification, and nothing on the page or in any
check would notice: it is a true sentence that has quietly stopped being true. Counting and
substitution live in `lib/contract.php` rather than in either renderer, so the editor's preview and
the published page cannot disagree about what a token means.

## `content/branding.json`

The branding & advertisement page: the logo files people download, and the terms covering their
use. Edited at `/?s=branding`.

```
meta    { … }
hero    { title, subtitle }
assets  { status, eyebrow, title, lead, items[] }
legal   { status, title, items[] }
cta     { status, title, text, items[] }
```

One `assets.items[]` row is a logo variant:

| Field | Type | Notes |
|---|---|---|
| `id` | string | minted from the title |
| `title` `text` | string | the card's heading and its one line of description |
| `alt` | string | read instead of the preview picture |
| `plate` | string | `light`, `dark` or `neutral` — the background behind the preview |
| `status` | string | `shown` or hidden |
| `image` | picture | the preview drawn on the card |
| `files[]` | list | what a visitor can download |

And one `files[]` row:

| Field | Type | Notes |
|---|---|---|
| `id` | string | minted from the label, or the format when there is none |
| `label` | string | the adjective in the meta line — *"Transparent PNG"* |
| `filename` | string | the `download=` attribute: what the visitor's computer calls it. No separators; `branding_safe_filename()` empties anything with one |
| `status` | string | `shown` or hidden |
| `file` | picture | the file itself, which may be a vector |

`legal.items[]` rows are `{ id, text, status }` and `text` is **rich text** — the only rich text on
the page, because it is a legal notice and the sentence asking a rights holder to get in touch is a
link waiting to happen. It goes through `rt_sanitise_html()` on save and again on receipt.

### The preview and the download are two different pictures

`image` is the small thing on the card; `files[].file` is what somebody came for. On the page as it
ships those are an 800px preview and a 1600px download of the same mark. Collapsing them would
either serve the big file to everyone who merely looks at the page, or hand out the small one to
everyone who came for the logo.

A download may be an **SVG**; a preview may not. The page *links* to a vector file and never draws
one — see [0019](../90-decisions/0019-uploaded-images-travel-their-own-channel.md) and `lib/svg.php`.
A download is also allowed up to `UPLOAD_MAX_DOWNLOAD_DIMENSION` (3000px) rather than the 1600px
every displayed picture is reduced to, because it is the deliverable rather than decoration.

### Three things are not in the file

- **the size in a meta line** — *"1600 × 570"* is read off the file's own record, so it cannot claim
  a size the file no longer has. Only the adjective beside it is stored, because *"Transparent"* is
  editorial;
- **the words on a download button** — *"Download PNG"* states the file's own format;
- **the glyph on it** — every button carries `arrow-down`, so it is a constant in the renderer.

### The breadcrumb is its own field

The page is titled *"Branding Assets & Guidelines"* and called *"Branding & Advertisement"*
everywhere it is linked from. The about, company and certifications pages let their breadcrumb
follow `hero.title` because on those three the two strings are the same; here they differ, so a
breadcrumb that followed the hero would quietly rename the page in every search result that shows a
trail.

## `content/privacy.json`

The privacy policy: twelve headed sections, a summary callout, a retention table and an address
block. The last page on the site to stop being hand-written. Edited at `/?s=privacy`.

```
meta    { … }
hero    { title, subtitle }
policy  { label, effective, callout{…}, sections[] }
cta     { status, title, text, items[] }
```

| Field | Type | Notes |
|---|---|---|
| `policy.label` | string | the visually-hidden `<h2>` that names the region for a screen reader, bound to it by `aria-labelledby` |
| `policy.effective` | string | the whole line at the top — *"Effective 21 August 2026"*. The wording is authored: *"Effective"* and *"Last updated"* do not mean the same thing |

One `policy.sections[]` row is a headed part of the policy:

| Field | Type | Notes |
|---|---|---|
| `id` | string | **the anchor**, minted from the heading and then frozen for good |
| `heading` | string | the `<h2>` |
| `status` | `shown` \| `hidden` | |
| `blocks[]` | list | in the order they render |

One `blocks[]` row is one shape, and `kind` decides which:

| `kind` | Carries | Renders |
|---|---|---|
| `paragraph` | `text` | `<p>` |
| `note` | `text` | `<p class="legal__notice">` |
| `address` | `text` | `<address class="legal__address">` |
| `subheading` | `text` *(plain)* | `<h3 class="legal__subheading">` |
| `list` | `rows[]` of `{ id, text, status }` | `<ul class="legal__list">` |
| `table` | `caption`, `columns[2]`, `rows[]` of `{ id, label, value, status }` | `.legal__table-wrap > table` |

`policy.callout` is `{ status, title, items[], note }` — the *"short version"* box, whose `items[]`
are `{ id, text, status }`.

### Structure is a kind, not markup

`rt_sanitise_html()` allows nine tags — `p br strong em u ul ol li a` — and no heading, no
`<address>` and no `<table>` among them. A person typing `<h3>` into a rich field would watch it
disappear on save with no way to tell that from a bug. So every block declares what it **is**, and
the renderer owns the markup for that kind. A seventh shape costs a row in `PRIVACY_BLOCK_KINDS`
and an arm in `privacy_block_defaults()`.

A block is also **narrowed** to the fields its kind uses. A block that was a `list` and is now a
`paragraph` does not keep its `rows[]` — invisible on the page, carried in the document and
published every time.

### Every rich field here is inline-only

`paragraph`, `note`, `address`, a list row and a callout point all go through
`rt_sanitise_inline()`, not `rt_sanitise_html()`. Each renders *inside* an element the renderer
supplies, so a `<p>` arriving from the editor is not emphasis somebody added — it is a paragraph
inside a paragraph, and pressing Enter in a textarea is how it would arrive.

### An anchor is a promise

A section's `id` is the fragment somebody links to. Ids are assigned by `contract_identify_rows()`,
which claims every id already chosen **before** minting anything new — because the obvious one-pass
version lets a section added above an existing one with the same heading take that section's
fragment and silently rename the incumbent. Nine of the twelve shipped ids are hand-authored
(`who-we-are`, not `who-is-responsible-for-your-data`) and there is nothing to recover them from.

### The effective date is never stamped

`updated` records when the document was last published. `policy.effective` is a claim about when the
**policy** changed, and fixing a typo is not a new policy — so nothing writes it but a person.

### The policy band cannot be hidden

`PRIVACY_BANDS` holds only `cta`. Hiding the policy would leave a page headed *"Privacy Policy"*
with no policy on it, still linked from the footer of all sixteen pages and still in the sitemap.
The callout, any section, any block and any row can each be hidden.

### What it repeats from the contact page is compared, never enforced

The policy states the offices, the email and the telephone; so does `content/contact.json`. They are
kept separately on purpose — a controller's details are a legal statement, and one that changed
because somebody edited another page would be a statement nobody made. `privacy_shared_facts()`
asks by containment whether the policy still states the current values, on a form with `&nbsp;` and
whitespace collapsed, commas dropped and case folded, so it reports a different street and stays
quiet about a different comma. The editor draws it as a standing notice and **never refuses a
save**.

## Which pictures get a light/dark pair, and which do not

Asked and settled on 2026-08-31. Every managed picture on the site, and why it is or is not a pair:

| Page | What | n | Treatment | Pair? |
|---|---|---|---|---|
| Home | Get to Know Us cards | 3 | white plate, both modes | **yes** |
| About | photograph sections | 4 | white plate, both modes | **yes** |
| About | the logo section | 1 | themed surface | **yes** |
| Company | client logos | 9 | white plate, both modes | no |
| Company | technology logos | 50 | white plate, both modes | no |
| Company | journey photographs | 3 | no plate, full-bleed | no |

**The three that are pairs are line art or a wordmark** — dark ink that needs a light ground, kept on
`--artwork-plate` in both modes. If the company ever has artwork drawn for a dark page, the slot is
there. **With nothing uploaded the markup is exactly what it was before the slot existed**: one
`<picture>`, no theme-swap classes, no second element. The page does not pay for an unused feature.

**The client and technology logos are deliberately NOT pairs.** They are other companies' brand
marks and the white plate is a legibility guarantee, not a default — several client marks are close
to solid black and vanished into the dark theme's elevated surface at about 1.4:1 before the plate
was introduced. A dark slot there would invite somebody to break that guarantee with artwork this
company does not own. One generic, consistent presentation is the right answer for a logo wall.

**The journey photographs are NOT pairs either, for a different reason.** They have no plate at all
— `object-fit: cover`, full-bleed — and they are photographs. A photograph carries its own content
edge to edge and reads correctly in either mode, so a second version would be two copies of the same
picture. Full-bleed is also simply how they look best.

The rule, stated once: **a picture gets a second slot when the page has to supply its background.
It does not when the picture is its own background, or when a fixed plate is a guarantee rather
than a default.**

## The `meta` band, on every document

Every document has one, including `content/careers.json`, which never used to — its title and
description were literal strings in the page file and were the only two on the site nobody could
change.

```json
"meta": {
  "title":       "About Tech4TIME | Trusted IT & Cybersecurity Solutions",
  "description": "Founded in 2018, Tech4TIME delivers …",
  "share_title": "About Tech4TIME",
  "keywords":    "about Tech4TIME, IT company Bangladesh, cybersecurity company Dhaka",
  "breadcrumb":  "About Us",
  "robots":      "index",
  "changefreq":  "monthly",
  "priority":    "0.8",
  "share":       { "src": "", "webp": "", "width": 0, "height": 0 },
  "share_alt":   ""
}
```

| Field | |
|---|---|
| `title` | the browser tab and the search result's heading. At most `SEO_TITLE_MAX` (65) characters |
| `description` | the search result's paragraph. `SEO_DESC_MIN`–`SEO_DESC_MAX` (50–165); 150–160 is the ideal the editor hints at and nothing refuses |
| `keywords` | a comma-separated list, tidied on save: empties and case-insensitive repeats dropped, one space after each comma. **Omitted from the page entirely when empty.** Google has ignored the tag since 2009 and Bing treats a stuffed one as spam — a handful of true words is worth more than a long list |
| `share_title` | the heading on a shared link. Usually the title without the brand suffix |
| `breadcrumb` | the page's name in the BreadcrumbList. **Pure SEO** — there is no visible breadcrumb anywhere on the site |
| `robots` | `index` or `noindex`. **Also decides the sitemap**: one control, not two, so the two cannot contradict each other |
| `changefreq`, `priority` | the sitemap's hints for this page |
| `share` | a per-page share card. Empty means the site-wide one in `content/seo.json`, which is what every page uses today |
| `share_alt` | its alt text |

**A service row carries the same band**, so a seventh service arrives with sensible defaults and a
sitemap entry without anyone opening a second screen.

**Only `title`, `description`, `share_title` and `breadcrumb` are in `*_TEXT_FIELDS`.** The rest are
enumerated or structured, and are validated against their allowed values rather than trimmed as
free text.

### The band is edited on one screen, and by nothing else

`?s=seo&page=<key>` writes it. The nine page editors do not: they render a link to that screen where
the fieldset used to be, and their `*_from_post()` loops iterate `contract_page_bands()`, which is
`*_TEXT_FIELDS` **minus** `meta`.

That subtraction is load-bearing. A form that stops *rendering* a field while its band is still
named in the loop reads `$_POST['meta']['title']` as absent, `?? ''` supplies an empty string, and
the page's title is blanked on every save — silently, because empty is a valid title.
`tech4time-website-backend/tools/test_seo_admin.py` saves each page editor untouched and requires
every meta value to survive. [ADR 0020](../90-decisions/0020-page-metadata-is-content.md)

---

## `content/seo.json`

The site-wide half: what is true of the whole site rather than of one page, plus the 404's own
record, because that page renders no content document and never will.

```json
{
  "updated":  "…",
  "revision": 0,
  "site":     { "name": "…", "lang": "en", "locale": "en_US", "og_type": "website",
                "twitter_card": "summary_large_image", "theme_light": "#…",
                "theme_dark": "#…", "share": {}, "share_alt": "…" },
  "identity": { "legal_name": "…", "alternate_name": "…", "slogan": "…",
                "description": "…", "founded": "…", "price_range": "…",
                "area_served": "…", "logo": {}, "service_types": [],
                "knows_about": [] },
  "sameas":   { "items": [] },
  "hours":    { "items": [] },
  "crawl":    { "robots_extra": [], "verify_google": "", "verify_bing": "" },
  "manifest": { "name": "…", "short_name": "…", "description": "…",
                "background": "#…", "theme": "#…", "display": "standalone" },
  "notfound": { "title": "…", "description": "…", "robots": "noindex" }
}
```

| Band | | Edited at |
|---|---|---|
| `site` | the defaults every page inherits: `<html lang>`, `og:locale`, `og:type`, the card shape, the theme colours, the share card | `?s=seo&site=identity` |
| `identity` | the Organization node — legal name, slogan, founding year, the services it offers, what it knows about | `?s=seo&site=identity` |
| `sameas` | the profiles that are this company elsewhere. Rows, so they add, reorder and **hide** | `?s=seo&site=identity` |
| `hours` | opening hours as machine-readable rows — `days[]`, `opens`, `closes`. The office rows in `content/contact.json` carry hours as prose, which a search engine cannot read | `?s=seo&site=identity` |
| `crawl` | extra `Disallow` paths, the Search Console and Bing verification tokens, and **`analytics_id`** — a Google measurement id. Empty means no analytics and no external origin; anything that is not the shape Google issues is refused rather than escaped, because it lands inside a `<script src>`. [ADR 0021](../90-decisions/0021-analytics-is-off-until-somebody-turns-it-on.md) | `?s=seo&site=crawl` |
| `manifest` | what `manifest.php` renders at `/site.webmanifest`. The **icon list is not here** — it names files that must exist | `?s=seo&site=crawl` |
| `notfound` | the 404's title, description and crawl directive. It has no canonical and no `og:url`, by design | `?s=seo&page=notfound` |

**The offices are not here.** The addresses and telephone numbers in the Organization graph come
from `content/contact.json`, through `contact_addresses()` and `contact_points()` — the same
functions the contact page renders from, so the graph and the visible page cannot disagree.

**`seo_defaults()` carries the real values, not placeholders.** A host with no `content/seo.json`
still emits the correct graph and share card, exactly as `contact_defaults()` already does for its
page.

---

## Rules that apply to both

**Written atomically.** `store_write()` writes a temp file and renames it over the target, keeping
one `.bak`. A visitor loading the page mid-save reads either the old file or the new one.

**Rich text is sanitised on save** by `rt_sanitise_html()`, which writes new tags from an allow-list
rather than passing anything through. There is no `style` attribute — the CSP blocks inline styles,
so alignment is a class from a fixed list.

**Everything is escaped on output** with `h()`, regardless of having been sanitised on the way in.

**Bookkeeping fields** — `updated`, `footer_synced` — are exempt from the content-model check in
both directions. Nothing renders them and the form does not write them.

**Ids are generated, not typed.** `careers_slug()` and `contact_slug()` make them.

**`.htaccess` redirects `index.php` as well as `index.html`** to the directory that holds it. It
covered only `.html` until the home page became PHP, and `https://tech4time.bd/pages/about/index.php`
answered 200 — a second URL for a page that already had one, which is the duplicate-content problem
those rules exist to prevent. Do not simplify the rule back to one extension.

---

## On the host, these files are the real data

Written by people through `https://admin.tech4time.bd/`. **Never upload them to a live server** —
[routine-deploys.md](../20-deployment/routine-deploys.md).

The repository's copies are development data, kept deliberately rich because an empty file exercises
no renderer. [environments.md](../20-deployment/environments.md)
