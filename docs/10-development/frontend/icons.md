# Icons

**Applies to:** both

Self-hosted SVG symbols, inlined per page. Not a webfont, not a CDN, not a shared sprite file.

---

## How it works

`assets/icons/sprite.svg` holds the master set — 119 symbols, cut from Font Awesome Free metadata by
`tools/build_icon_sprite.py`.

Pages do **not** link to it. Each page carries the handful of symbols it actually uses, inlined at
the top of `<body>` between two markers:

```html
<!-- icon-sprite:start -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none">
  <symbol id="shield-alt" viewBox="0 0 512 512">…</symbol>
  <symbol id="envelope"   viewBox="0 0 512 512">…</symbol>
</svg>
<!-- icon-sprite:end -->
```

and reference them as same-document fragments:

```html
<svg class="icon" aria-hidden="true"><use href="#shield-alt"></use></svg>
```

`tools/inject_icons.py` keeps the block in sync with what the page references.

---

## Why not a shared sprite file

The obvious approach:

```html
<svg><use href="/assets/icons/sprite.svg#shield-alt"></use></svg>
```

**Chromium and WebKit do not resolve `<use>` across documents.** That markup renders nothing outside
Firefox. The workarounds are a JavaScript polyfill — which makes icons vanish without script,
breaking the progressive-enhancement rule — or inlining.

Inlining wins on every axis that matters here: no extra request, no script dependency, and each page
carries only what it uses. A page with 30 icons costs roughly 15 KB before gzip, against 64 KB for
the full set.

---

## Adding an icon to a page

1. Reference it in the markup:
   ```html
   <svg class="icon" aria-hidden="true"><use href="#calendar-alt"></use></svg>
   ```
2. Inject:
   ```bash
   python3 tools/inject_icons.py
   ```
3. Verify:
   ```bash
   python3 tools/inject_icons.py --check
   ```

The script reads every `<use href="#…">`, pulls the matching `<symbol>` out of the master sprite,
and rewrites the block. It also removes symbols no longer referenced, so the block never accumulates
dead weight.

**If the symbol is not in the master sprite**, the script says so. Add it with
`tools/build_icon_sprite.py`, then inject.

---

## Icons chosen at run time

`inject_icons.py` can only see a name written into a page as a literal `<use href="#…">`. An icon
that comes out of a content document is chosen after the page was written, so the scan never finds
it and the symbol is never inlined — the page draws an empty square.

The answer is a **second sprite**, built as the page renders, holding exactly the symbols that page
will actually draw. It is written by `sprite_block()` in `lib/sprite.php`, and three callers work
out what to hand it:

| | |
|---|---|
| `services_icons_used()` | the icons on the services index and its detail pages |
| `certifications_icons_used()` | the icons a role group chose in the editor |
| `chrome_sprite()` | the chrome's fifteen: the theme toggle, the footer's contact and social marks, the four dock keys, the menu button, back to top |

Its markers are `content-sprite:start` / `content-sprite:end` and **not** `icon-sprite:…`,
deliberately — a second pair of those would make `inject_icons.py`'s non-greedy match end in the
wrong place, swallowing everything between the first start and this end.

**The chrome is the reason `inject_icons.py` now finds nothing on eleven pages.** The header, footer
and dock left page source when they became `content/chrome.json`
([ADR 0023](../../90-decisions/0023-the-header-and-footer-are-emitted-once.md)), taking their
`<use>` references with them; on the eleven pages whose only icons were the chrome's,
`inject_icons.py` drops its block entirely and `body_header()` supplies the symbols instead. The
tool needed no change at all.

Two `<symbol>` elements may share an id, and several do. The first definition wins, the markup is
identical either way, and `tools/audit_pages.py` exempts symbol ids from its duplicate-id check for
exactly this reason. It also audits **rendered** output, so "every `<use>` resolves to an inlined
`<symbol>`" is proved on the text a visitor actually receives, whichever sprite supplied it.

The alternative — naming every icon the model offers in a comment the scan can see — is what the
about, contact and company pages do, and it is right where the list is short. It was measured
against seventy-six for the services model and cost **+7 to +10 KB gzipped per page**, so the rule
of thumb is: a handful, name them; a model's worth, build the sprite.

### The allow-lists

Which icons a document may offer is part of the model, in `lib/contract.php`:
`CONTACT_ICONS`, `COMPANY_ICONS`, `ABOUT_ICONS`, `HOME_ICONS`, `SERVICES_ICONS`, and
`CHROME_BAR_ICONS` with the fixed marks beside it (`CHROME_CONTACT_ICONS`, `CHROME_SOCIAL_ICONS`).
They are in the shared contract because the backend offers the choice and the frontend has to be
able to draw whatever was chosen.

`python3 tools/check_content_model.py` asserts every name in all of them is in the master sprite,
and — run in the backend, where the editor lives — that it is in `ADMIN_ICONS` too.

---

## Icons in the admin

The admin's pages are PHP, so their icon block is generated at request time by `admin_icons()` in
`tech4time-website-backend/lib/admin.php` rather than injected by the script. The set is the `ADMIN_ICONS` constant in the same
file.

**Adding an icon to an admin page means adding its name to `ADMIN_ICONS`.** Nothing else — the shell
inlines the whole list on every admin page, because the contact editor renders a live preview of
every icon it offers and cannot know in advance which will be used.

---

## Accessibility

**Decorative** — the icon repeats adjacent text:

```html
<svg class="icon" aria-hidden="true"><use href="#phone"></use></svg>
<span>+880 …</span>
```

**Meaningful** — the icon is the only label:

```html
<button aria-label="Close">
  <svg class="icon" aria-hidden="true"><use href="#times"></use></svg>
</button>
```

The icon itself is always `aria-hidden="true"`; the accessible name goes on the control. A bare
`<svg>` with no name inside an interactive element is a finding `audit_pages.py` reports.

---

## Checks

```bash
python3 tools/inject_icons.py --check   # every page's block is current
python3 tools/audit_pages.py            # icon accessibility, among much else
```

`inject_icons.py --check` is in the pre-commit list. It fails when a page references a symbol it does
not carry — which renders as an empty box, and is very easy to miss by eye.
