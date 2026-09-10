<?php
/**
 * Tech4TIME — the chrome: header, footer and dock data access.
 *
 * Reading the file is lib/store.php; escaping is lib/html.php; the SHAPE is
 * lib/contract.php, which the frontend and the backend hold byte-identical.
 * What is left here is this side's own business with that shape.
 *
 * Nothing here writes. The only thing on this host that writes content at all
 * is api/publish.php, landing a document the backend signed.
 *
 * THE CHROME IS THE FURNITURE AROUND EVERY PAGE. It was literal markup in
 * seventeen page files, propagated by a script; it is one document now, and
 * lib/body.php turns it into the header, footer and dock a visitor receives.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":   set on every save
 *     "revision":  monotonic; see contract.php
 *     "header":    { brand_label, logo, nav: { items[] } }
 *     "footer":    { brand_label, logo, tagline, description,
 *                    links:    { heading, items[] },
 *                    services: { heading, index_label },
 *                    contact:  { heading, items[] },
 *                    legal:    { items[] },
 *                    copyright: { name, rights } }
 *     "dock":      { panel: { items[] }, bar: { items[] }, menu_label }
 *   }
 *
 * A LINK ROW POINTS AT A ROUTE, NOT A URL: { id, target, label, status },
 * where target is a key of chrome_targets() -- 'about', 'service:cybersecurity'
 * -- and an empty label means "whatever that page calls itself". A nav link
 * cannot 404, because there is no way to type an address into one.
 *
 * TWO COLUMNS OF THE FOOTER STORE NOTHING AND ARE DERIVED:
 *   the services list   from content/services.json, so a seventh service
 *                       appears by itself and a hidden one goes
 *   the social links    from the SEO document's sameas rows, so a URL is
 *                       changed in one place and the footer can never disagree
 *                       with the Organization graph
 *
 * AND THE CONTACT ROWS DELIBERATELY ARE NOT. They are the footer's own, typed
 * on the footer screen, and owe nothing to content/contact.json -- see the
 * note in lib/contract.php's section 11 for why, and what reports it when the
 * two drift.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/sprite.php';

const CHROME_FILE = __DIR__ . '/../content/chrome.json';

/**
 * The document, with every field the renderer reads guaranteed present.
 *
 * Memoised, unlike the page documents, because this one is read up to three
 * times in a single request -- once by the header, once by the footer and once
 * by the dock -- where a page's own document is read once and passed around.
 *
 * A MISSING FILE IS NOT AN ERROR. chrome_normalise() fills from
 * chrome_defaults(), which is the site's own header, footer and dock as they
 * shipped, so a host that has never received a publish still renders a correct
 * page rather than an empty one. That is not a nicety: the chrome is on every
 * page, and the failure mode it avoids is the whole site losing its navigation
 * because one file did not arrive.
 */
function chrome_load(): array
{
    static $data = null;

    if ($data === null) {
        $data = chrome_normalise(store_read(CHROME_FILE) ?? []);
    }

    return $data;
}

/** The header's band of the document. */
function chrome_header(): array
{
    return chrome_load()['header'];
}

/** The footer's band. */
function chrome_footer(): array
{
    return chrome_load()['footer'];
}

/** The dock's band. */
function chrome_dock(): array
{
    return chrome_load()['dock'];
}

/* -------------------------------------------------------------- resolution

   BETWEEN THE DOCUMENT AND THE MARKUP. A stored row says 'services' and
   'shown'; a rendered link needs an address and a name. That translation is
   here, and lib/body.php does nothing but write tags around what these return.

   Nothing below escapes anything. Escaping is the emitter's job, at the point
   the value meets a tag, which is where it can be seen to have happened. */

/**
 * Every destination a link row may point at, worked out once per request.
 *
 * chrome_targets() is in the contract and is handed the services document
 * rather than reading one, so that the shape stays a pure function of its
 * input and the backend can offer the same list from a document it holds in
 * memory. This is the frontend's side of that: read the file, once.
 */
function chrome_target_list(): array
{
    static $targets = null;

    if ($targets === null) {
        $targets = chrome_targets(services_load());
    }

    return $targets;
}

/**
 * One link row as an address and a name, or null when it resolves to neither.
 *
 * Returns ['href' => …, 'label' => …].
 *
 * NULL IS THE ANSWER TO A ROW THAT CANNOT BE DRAWN, and there are two of
 * those. A target key nothing answers to -- a service deleted after the row
 * was written -- is skipped rather than rendered as a dead link; the contract
 * keeps the row so the editor can show it and somebody can fix it. A target
 * that is a HIDDEN service is skipped too: hiding a service takes its page out
 * of the sitemap, and a footer that went on linking to it would be the only
 * thing left pointing at a page nobody is meant to find.
 *
 * AN EMPTY LABEL IS NOT A MISSING LABEL. It means "whatever that page calls
 * itself", which is seo_route_name() -- the page's own breadcrumb, falling
 * back to the name in SEO_ROUTES. That is what makes renaming a page in the
 * editor rename its nav link, and it is why the shipped rows carry no labels
 * at all.
 */
function chrome_link(array $row): ?array
{
    $key = trim((string)($row['target'] ?? ''));
    $all = chrome_target_list();

    if ($key === '' || !isset($all[$key])) {
        return null;
    }

    $target = $all[$key];
    if ($target['hidden']) {
        return null;
    }

    $label = trim((string)($row['label'] ?? ''));
    if ($label === '') {
        $label = $target['service']
            ? $target['name']
            : seo_route_name($key, $target['name']);
    }

    return ['href' => $target['route'], 'label' => $label];
}

/**
 * The footer's services column: the shown services, in document order.
 *
 * Returns [['href' => …, 'label' => …], …]. The row above them -- "All
 * Services", pointing at the index -- is the emitter's, built from
 * footer.services.index_label, because it is a label rather than a service.
 *
 * NOTHING IS STORED FOR THIS. It is content/services.json read at render
 * time, which is the whole point: a seventh service appears here by itself and
 * a hidden one goes, with nobody editing a footer.
 */
function chrome_services(): array
{
    $out = [];

    foreach (chrome_target_list() as $target) {
        if ($target['service'] && !$target['hidden']) {
            $out[] = ['href' => $target['route'], 'label' => $target['name']];
        }
    }

    return $out;
}

/**
 * The footer's social links, derived from the SEO document's sameAs rows.
 *
 * Returns [['url' => …, 'label' => …, 'icon' => …], …].
 *
 * ONE PLACE FOR A PROFILE URL. These used to be literal markup in the footer
 * template beside the same two URLs in the Organization graph, and
 * tools/check_shared_facts.py existed partly to report when the two parted.
 * They cannot part now: there is one copy, and the footer is a view of it.
 *
 * The mark is chosen from the host, because a sameAs row holds a URL and a
 * label and nothing about how to draw one. A host the sprite has no mark for
 * gets a globe rather than nothing -- see CHROME_SOCIAL_ICONS.
 */
function chrome_social(): array
{
    $out = [];

    foreach (chrome_rows_shown(seo_sameas()['items']) as $row) {
        $url = trim((string)($row['url'] ?? ''));
        if ($url === '') {
            continue;
        }

        $host  = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        $icon  = CHROME_SOCIAL_FALLBACK;
        foreach (CHROME_SOCIAL_ICONS as $suffix => $mark) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                $icon = $mark;
                break;
            }
        }

        $out[] = [
            'url'   => $url,
            'label' => trim((string)($row['label'] ?? '')) ?: $host,
            'icon'  => $icon,
        ];
    }

    return $out;
}

/**
 * The footer's contact rows, grouped the way they render.
 *
 * Returns [['kind' => …, 'icon' => …, 'rows' => [row, …]], …] -- consecutive
 * shown rows sharing a kind in one group, under one icon, which is what
 * `.contact-item__label ~ .contact-item__label` in layout.css is written
 * against. Three phone rows are one telephone icon and three labelled groups
 * beneath it, not three icons.
 *
 * CONSECUTIVE, not sorted: the order is the operator's, and a phone row moved
 * below the addresses is meant to be below the addresses. Grouping by kind
 * across the whole list would silently undo a deliberate reorder.
 */
function chrome_contact_groups(): array
{
    $out = [];

    foreach (chrome_rows_shown(chrome_footer()['contact']['items']) as $row) {
        $kind = (string)($row['kind'] ?? '');
        if (!isset(CHROME_CONTACT_ICONS[$kind])) {
            continue;
        }

        $last = count($out) - 1;
        if ($last >= 0 && $out[$last]['kind'] === $kind) {
            $out[$last]['rows'][] = $row;
            continue;
        }

        $out[] = ['kind' => $kind, 'icon' => CHROME_CONTACT_ICONS[$kind],
                  'rows' => [$row]];
    }

    return $out;
}

/**
 * The href one of a contact row's lines carries, or '' when it carries none.
 *
 * A phone number is written the way a person reads it and dialled the way a
 * phone dials it, so the separators come out of the href and stay in the text.
 * contact_tel() is the contact page's own rule for that, in the contract,
 * used here so the two cannot answer differently.
 */
function chrome_contact_href(string $kind, string $line): string
{
    $line = trim($line);
    if ($line === '') {
        return '';
    }

    if ($kind === 'phone') {
        return 'tel:' . contact_tel($line);
    }

    if ($kind === 'email' && filter_var($line, FILTER_VALIDATE_EMAIL)) {
        return 'mailto:' . $line;
    }

    return '';
}

/**
 * Whether a chrome link is the page being looked at.
 *
 * $route is the page's own address, the same string it hands seo_head().
 *
 * A PREFIX COUNTS, and that is not an accident of the implementation: on
 * /pages/services/cybersecurity/ the marked link is Services, because that is
 * the nav entry the visitor is inside. It is what the site has always sent --
 * tools/propagate_shared.py re-applied whatever markers a page already had --
 * and it is what a nav with no entry of its own for a service should say.
 *
 * '/' is excluded from the prefix rule, or Home would be current everywhere.
 * The 404 passes '' and marks nothing, because it has no address of its own.
 */
function chrome_is_current(string $route, string $href): bool
{
    if ($route === '' || $href === '') {
        return false;
    }

    return $href === $route || ($href !== '/' && str_starts_with($route, $href));
}

/* ------------------------------------------------------------- the sprite */

/**
 * Every symbol the chrome draws, on every page.
 *
 * WHY THE CHROME NEEDS A RUN-TIME SPRITE AT ALL. tools/inject_icons.py reads a
 * page's SOURCE for <use href="#name">. The chrome left the source when it
 * became a document, so eleven of the seventeen pages would otherwise get an
 * empty block -- every icon they carry comes from the header, footer or dock.
 * Nothing changed in that tool; it simply stops finding these, and this
 * supplies them. See lib/sprite.php.
 *
 * The set is the same on every page, unlike services' and certifications',
 * because the chrome is the same on every page. It still depends on the
 * DOCUMENT -- a dock key's icon, a social row's host -- so it cannot be a
 * constant, which is exactly why inject_icons.py could never have seen it.
 */
function chrome_icons_used(): array
{
    /* The theme toggle's two states, the back-to-top key, and the dock menu
       button's open and close marks. Code, not content: no field turns any of
       them off, and none of them is in the document. */
    $names = ['moon', 'sun', 'arrow-up', 'grid-dots', 'times'];

    foreach (chrome_contact_groups() as $group) {
        $names[] = $group['icon'];
    }

    foreach (chrome_social() as $link) {
        $names[] = $link['icon'];
    }

    foreach (chrome_dock()['bar']['items'] as $key) {
        $names[] = (string)($key['icon'] ?? '');
    }

    $names = array_filter(array_unique($names),
                          static fn($n): bool => trim((string)$n) !== '');

    return array_values($names);
}

/** Those symbols, inlined. Emitted once, by body_header(). */
function chrome_sprite(): string
{
    return sprite_block(chrome_icons_used());
}
