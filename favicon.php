<?php
/**
 * Tech4TIME — /favicon.ico.
 *
 * Served at /favicon.ico, the address a browser probes BLINDLY: before it has
 * read a line of the page, before any <link>, and on requests that have no
 * page at all. .htaccess rewrites that URL here internally, so it does not
 * change.
 *
 * IT DID NOT EXIST. There was no file at the root and no rewrite, so every one
 * of those probes got a 404 — an error in the log on every cold visit, and a
 * blank mark in any client that asks this way and does not parse <link>.
 *
 * ASSEMBLED, NOT STORED, and that is not a shortcut. The asset channel carries
 * what getimagesizefromstring() recognises — PNG, JPEG, WebP — and an .ico is
 * none of them; widening that list so one file could travel would also widen
 * what an editor can upload as page artwork. This host already holds the three
 * PNGs an .ico is made of, so it builds the container here:
 * contract_ico_container(), the same function the editor would have used.
 *
 * WITH NOTHING UPLOADED IT SENDS THE COMMITTED FILE, byte for byte. A host
 * that has never received a publish answers this address exactly as one that
 * has, which is the same rule every other part of the settings document
 * follows.
 */

declare(strict_types=1);

require __DIR__ . '/lib/settings.php';

/** The committed set, which is what ships and what everything falls back to. */
const FAVICON_SHIPPED = __DIR__ . '/assets/images/favicon/favicon.ico';

/**
 * A year, because this address cannot be content-addressed.
 *
 * Every other picture on this site is served from a URL computed from its own
 * bytes, so a year-long cache is safe: a different picture is a different URL.
 * This one is /favicon.ico or it is nothing — a browser probing blindly has no
 * way to be told a version. So the trade is the other way round: cache it hard
 * because it is requested on cold visits to every page, and accept that a
 * changed mark reaches a returning visitor when their cache lets go.
 *
 * A week rather than a year for exactly that reason. Long enough that the
 * probe costs nothing in practice; short enough that a company changing its
 * mark does not wait a year to see it in the tab.
 */
const FAVICON_MAX_AGE = 604800;

/**
 * The .ico bytes: assembled from what was published, or the committed file.
 *
 * ALL OR NOTHING. A container built from two of the three sizes is a valid
 * file holding the wrong set, and one built from a rung that did not arrive is
 * a valid file holding nothing. Either is worse than the mark that ships, so
 * any gap at all falls back to the whole committed file.
 */
function favicon_bytes(): string
{
    $settings = settings_load();
    $images   = [];

    foreach (SETTINGS_ICON_ICO as $size) {
        $path = trim((string)($settings['icon']['generated']['png' . $size] ?? ''));

        if ($path === '') {
            return (string)@file_get_contents(FAVICON_SHIPPED);
        }

        $bytes = @file_get_contents(__DIR__ . $path);

        if ($bytes === false || $bytes === '') {
            return (string)@file_get_contents(FAVICON_SHIPPED);
        }

        $images[$size] = $bytes;
    }

    return contract_ico_container($images);
}

$ico = favicon_bytes();

if ($ico === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "No icon.\n";
    exit;
}

header('Content-Type: image/vnd.microsoft.icon');
header('Content-Length: ' . strlen($ico));
header('Cache-Control: public, max-age=' . FAVICON_MAX_AGE);
header('X-Content-Type-Options: nosniff');

echo $ico;
