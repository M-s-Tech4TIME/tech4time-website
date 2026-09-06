<?php
/**
 * Tech4TIME — the site-wide SEO record, and what is derived from it.
 *
 * Reading the file is lib/store.php; escaping is lib/html.php; the SHAPE is
 * lib/contract.php, which the frontend and the backend hold byte-identical.
 * What is left here is this side's own business with that shape: turning it
 * into the canonical, the trail, the graph and the sitemap rows a crawler
 * receives. The markup itself is lib/head.php.
 *
 * Nothing here writes. The only thing on this host that writes content at all
 * is api/publish.php, landing a document the backend signed.
 *
 * A PAGE'S OWN METADATA IS NOT IN THIS DOCUMENT, and that is worth knowing
 * before reading any of it. Every page's title, description, share title,
 * breadcrumb, crawl directive and sitemap tuning live in that page's OWN
 * content document, in the meta band every document has. This file holds what
 * belongs to the site rather than to any one page -- the Organization graph,
 * the default share card, the colours, robots.txt, the manifest -- plus the
 * 404's record, because that page renders no content document.
 *
 * So a page hands its own meta band in:
 *
 *     seo_head('/pages/about/', $data['meta'], ['pages/about.css']);
 *
 * which is one array the page has already loaded, and no second read.
 *
 * THE ADDRESSES AND TELEPHONE NUMBERS IN THE GRAPH COME FROM THE CONTACT
 * DOCUMENT, not from here. They were pasted literally into seventeen heads and
 * went stale on sixteen of them the first time an office moved;
 * tools/sync_site_contact.py was written to paper over exactly that. They are
 * read at render time now, from the one document that owns them.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';
require_once __DIR__ . '/contact.php';

const SEO_FILE = __DIR__ . '/../content/seo.json';

/**
 * The document, with every field the renderer reads guaranteed present.
 *
 * MEMOISED, because unlike every other loader this one is asked for on every
 * page by the head, by the graph and by the breadcrumb, and re-reading and
 * re-normalising the same file three times per request would be three times
 * the work for one answer. seo_defaults() carries the real values, so a
 * missing file is a correct page and not a blank one.
 */
function seo_load(): array
{
    static $data = null;

    if ($data === null) {
        $data = seo_normalise(store_read(SEO_FILE) ?? []);
    }

    return $data;
}

/** What every page says about the site rather than about itself. */
function seo_site(): array
{
    return seo_load()['site'];
}

/** The Organization's own facts, minus the ones the contact page owns. */
function seo_identity(): array
{
    return seo_load()['identity'];
}

/** The 404 page's meta band. The same shape every page's is. */
function seo_notfound(): array
{
    return seo_load()['notfound'];
}

/** The document language, for <html lang>. */
function seo_lang(): string
{
    return seo_site()['lang'] !== '' ? seo_site()['lang'] : 'en';
}

/** An absolute URL for a root-relative path. '' stays '', and means "none". */
function seo_url(string $path): string
{
    return $path === '' ? '' : SEO_ORIGIN . $path;
}

/**
 * The share card a page actually uses: its own override, or the site's.
 *
 * Returns [] when there is no card at all, so the caller emits no og:image
 * rather than an empty one. No page overrides today, which is why all
 * seventeen carry the same four lines.
 */
function seo_share(array $meta): array
{
    $site  = seo_site();
    $image = trim((string)($meta['share']['src'] ?? '')) !== ''
        ? $meta['share'] : $site['share'];
    $alt   = trim((string)($meta['share']['src'] ?? '')) !== ''
        ? (string)($meta['share_alt'] ?? '') : (string)$site['share_alt'];

    if (trim((string)($image['src'] ?? '')) === '') {
        return [];
    }

    return [
        'url'    => seo_url((string)$image['src']),
        'width'  => (int)$image['width'],
        'height' => (int)$image['height'],
        'alt'    => $alt,
    ];
}

/* ------------------------------------------------------------ the trail */

/**
 * The BreadcrumbList items for a page, or [] when it should not have one.
 *
 * Built by prefix match over SEO_ROUTES, so the trail follows the address and
 * is never hand-numbered. Two pages get nothing, both deliberately: the home
 * page, because a one-item trail says nothing a crawler does not already know
 * from the URL, and the 404, because it has no address to be a place in.
 */
function seo_breadcrumb(string $route, string $name): array
{
    $ancestors = seo_ancestors($route);

    if ($route === '' || $ancestors === []) {
        return [];
    }

    $items    = [];
    $position = 0;

    foreach ($ancestors as $key) {
        [$path, $fallback, $document] = SEO_ROUTES[$key];
        $items[] = [
            '@type'    => 'ListItem',
            'position' => ++$position,
            'name'     => seo_route_name($key, $fallback),
            'item'     => seo_url($path),
        ];
    }

    $items[] = [
        '@type'    => 'ListItem',
        'position' => ++$position,
        'name'     => $name,
        'item'     => seo_url($route),
    ];

    return $items;
}

/**
 * What a route in SEO_ROUTES calls itself, as its own document says.
 *
 * An ancestor's name is its breadcrumb, which is a field somebody edits, so
 * renaming "Services" in the SEO screen renames it in the trail of all six
 * service pages at once. Falls back to the constant's label if the document
 * cannot be read, because a trail with a name in it beats no trail.
 */
function seo_route_name(string $key, string $fallback): string
{
    static $cache = [];

    if (!isset($cache[$key])) {
        $meta  = seo_route_meta($key);
        $crumb = trim((string)($meta['breadcrumb'] ?? ''));
        $cache[$key] = $crumb !== '' ? $crumb : $fallback;
    }

    return $cache[$key];
}

/**
 * The meta band of a route in SEO_ROUTES, whichever document holds it.
 *
 * Reads the document rather than being handed it, because the callers that
 * need this -- the sitemap, and an ancestor's name -- are asking about pages
 * other than the one being rendered. A document that cannot be read answers
 * with defaults rather than throwing: a sitemap and a breadcrumb must not be
 * able to take a page down.
 */
function seo_route_meta(string $key): array
{
    if (!isset(SEO_ROUTES[$key])) {
        return [];
    }

    [$_path, $_name, $document] = SEO_ROUTES[$key];

    if ($document === '') {
        return seo_notfound();
    }

    try {
        $raw = store_read(contract_path($document));
    } catch (Throwable) {
        return [];
    }

    if (!is_array($raw)) {
        return [];
    }

    try {
        return contract_normalise($document, $raw)['meta'] ?? [];
    } catch (Throwable) {
        return [];
    }
}

/* ---------------------------------------------------------- the sitemap */

/**
 * Every URL the sitemap should carry: [route, lastmod, changefreq, priority].
 *
 * MEMBERSHIP IS DERIVED FROM robots AND NOTHING ELSE. There is no second
 * switch to disagree with the first, so a noindex page cannot be listed here
 * and a listed page cannot be noindex -- which is the pair a Search Console
 * warning is raised about.
 *
 * IT MUST NOT BE ABLE TO FAIL. A crawler asking for the sitemap gets a
 * sitemap: a document that is missing or unreadable contributes its defaults
 * rather than throwing, so the worst case is a stale date on one line.
 */
function seo_sitemap_entries(): array
{
    $entries = [];

    foreach (SEO_ROUTES as $key => [$path, $_name, $document]) {
        if ($path === '') {
            continue;                       // the 404 has no address to list
        }

        $meta = seo_route_meta($key);
        if (($meta['robots'] ?? 'index') === 'noindex') {
            continue;
        }

        $entries[] = [
            $path,
            seo_document_day($document),
            (string)($meta['changefreq'] ?? 'monthly'),
            (string)($meta['priority'] ?? '0.5'),
        ];

        /* The six or more service pages sit under the services index and are
           rows rather than files, so they are listed from the document the
           moment one is added. A service added in the editor has no file here
           to notice, which is why the sitemap stopped being a static file. */
        if ($key === 'services') {
            foreach (seo_service_entries($document) as $row) {
                $entries[] = $row;
            }
        }
    }

    return $entries;
}

/** The service pages, from the rows that are the only record of them. */
function seo_service_entries(string $document): array
{
    try {
        $raw = store_read(contract_path($document));
        $data = is_array($raw) ? contract_normalise($document, $raw) : [];
    } catch (Throwable) {
        return [];
    }

    $day = seo_document_day($document);
    $out = [];

    foreach (services_rows_shown(services_all($data)) as $service) {
        /* A slug the row has not been given yet would be listed as
           /pages/services// -- an address that answers 404, reported against
           the whole site as a crawl error. It is left out until it is real. */
        $slug = is_string($service['slug'] ?? null) ? $service['slug'] : '';
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            continue;
        }

        $meta = $service['meta'] ?? [];
        if (($meta['robots'] ?? 'index') === 'noindex') {
            continue;
        }

        $out[] = [
            '/pages/services/' . $slug . '/',
            $day,
            (string)($meta['changefreq'] ?? 'monthly'),
            (string)($meta['priority'] ?? '0.5'),
        ];
    }

    return $out;
}

/**
 * The day a document was last published, as YYYY-MM-DD, or '' if never.
 *
 * api/publish.php sets `updated` when the content arrives, so this is the date
 * the page actually changed rather than the date somebody last deployed.
 *
 * '' MEANS "MAKE NO CLAIM", AND THE SITEMAP OMITS THE ELEMENT. lastmod is
 * optional in the sitemap protocol, and the two alternatives are both worse
 * than silence. Today's date would say every page changed today, on every
 * request, which is how a site teaches Google to stop believing its lastmod
 * altogether. A date typed into this file would be a hand-maintained fact
 * about content this file does not own -- the ten dates that used to be here
 * were exactly that, and were already months stale.
 *
 * A document that has never been published is the ordinary state of a freshly
 * deployed host: content/ ships as a seed and carries no publish stamp until
 * the first save in the admin.
 */
function seo_document_day(string $document): string
{
    if ($document === '') {
        return '';
    }

    try {
        $raw = store_read(contract_path($document));
    } catch (Throwable) {
        return '';
    }

    $day = substr((string)($raw['updated'] ?? ''), 0, 10);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 ? $day : '';
}
