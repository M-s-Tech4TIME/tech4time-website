<?php
/**
 * Tech4TIME — site.webmanifest.
 *
 * Served at /site.webmanifest, the address every page's <link rel="manifest">
 * names; .htaccess rewrites that URL here internally, so it does not change.
 *
 * The name, description and colours are content and come from
 * content/seo.json. THE ICON LIST IS CODE, and stays here: it names files that
 * must exist at those exact paths and sizes, and a manifest pointing at an
 * icon that is not there is an install prompt that fails silently on a
 * stranger's phone.
 */

declare(strict_types=1);

require __DIR__ . '/lib/seo.php';

$site     = seo_site();
$manifest = seo_load()['manifest'];

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
            'src'     => '/assets/images/favicon/favicon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => '/assets/images/favicon/favicon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
