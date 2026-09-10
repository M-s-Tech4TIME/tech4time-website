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
