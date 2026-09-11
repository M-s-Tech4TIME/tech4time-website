<?php
/**
 * Tech4TIME — site.webmanifest.
 *
 * Served at /site.webmanifest, the address every page's <link rel="manifest">
 * names; .htaccess rewrites that URL here internally, so it does not change.
 *
 * The name, description and colours are content and come from
 * content/seo.json, and the two app icons from content/settings.json -- the
 * same two the editor generates from one square master, each falling back to
 * the file that ships.
 *
 * WHICH SIZES ARE HERE IS STILL CODE. A manifest naming an icon that is not
 * there is an install prompt that fails silently on a stranger's phone, so the
 * list is 192 and 512 because those are the two a manifest is read for -- not
 * because a document said so.
 */

declare(strict_types=1);

require __DIR__ . '/lib/seo.php';
/* _once because lib/seo.php brings it in too — the Organization graph's
   logo is derived from the same document. */
require_once __DIR__ . '/lib/settings.php';

$site     = seo_site();
$manifest = seo_load()['manifest'];
$settings = settings_load();

/* The registered type. Not application/json: some browsers will not read a
   manifest served as anything else, and nosniff means nothing will guess. */
header('Content-Type: application/manifest+json; charset=UTF-8');

echo json_encode([
    'name'             => $site['name'],
    'short_name'       => $manifest['short_name'],
    'description'      => $site['description'],
    'start_url'        => '/',
    'scope'            => '/',
    'display'          => $manifest['display'],
    'background_color' => $manifest['background'],
    'theme_color'      => $manifest['theme'],
    'icons'            => [
        [
            'src'     => settings_icon($settings, 'png192',
                                       '/assets/images/favicon/favicon-192.png'),
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => settings_icon($settings, 'png512',
                                       '/assets/images/favicon/favicon-512.png'),
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
