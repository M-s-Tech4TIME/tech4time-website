<?php
/**
 * Tech4TIME — the <head>, emitted once instead of pasted seventeen times.
 *
 * WHY THIS FILE EXISTS
 * Every page carried its own head: between 222 and 308 lines each, about 4,250
 * lines in all, and no propagation tool and no drift check over any of it.
 * tools/check_shared_markup.py holds the header, footer, dock and hero-circuit
 * byte-identical; the head was never in that set, and the template it came
 * from -- tools/templates/head.html, deleted with this file's arrival -- was
 * read exactly once per page, at birth, by tools/assemble_page.py.
 *
 * It drifted, exactly as that arrangement guarantees. The Organization graph
 * carried three office addresses and four telephone numbers as literal JSON in
 * sixteen of the seventeen heads -- the contact page alone rendered them from
 * content/contact.json -- so editing an office in the admin left sixteen pages
 * advertising the old one, and tools/sync_site_contact.py was written to paste
 * the new values back in before a deploy. That whole mechanism is gone from
 * the head now: the graph is built here, on the request, from the document
 * that owns the facts.
 *
 * WHAT A PAGE PASSES, AND WHY IT IS NOT A KEY
 * A page hands its OWN ADDRESS and its OWN meta band:
 *
 *     seo_head('/pages/about/', $data['meta'], ['pages/about.css']);
 *
 * The address rather than a key from SEO_ROUTES, because a service page's
 * address is a row's slug and is in no constant -- and because the canonical
 * is then the argument itself, which tools/audit_pages.py can check against
 * the directory the file actually sits in. A miscopied argument is the one
 * mistake an emitter makes possible, and that is the check that catches it.
 *
 * The meta band rather than a lookup, because the page has already loaded its
 * document to render its body. No second read, no second source.
 *
 * THE ONE PAGE WITH NO ADDRESS is the 404, which passes '' and seo_notfound().
 * It gets no canonical and no og:url, because it is served at every address
 * that does not exist and has no URL of its own to name.
 *
 * NOTHING HERE IS EDITABLE THAT SHOULD NOT BE. The canonical is derived from
 * the address and cannot be typed; the CSP, the favicon list, the font preload
 * and the stylesheet order are code. An editor able to break the Content
 * Security Policy is a hazard, not a feature.
 */

declare(strict_types=1);

require_once __DIR__ . '/seo.php';

/**
 * The stylesheets every page loads, in cascade order, before its own.
 *
 * THE VERSION QUERY IS THE CACHE BUST AND IT IS NOT DECORATION. Filenames are
 * not content-hashed -- there is no build step to hash them -- and .htaccess
 * caches CSS for a year, so a changed stylesheet reaches nobody who has been
 * here before unless this string changes with it. It used to have to be bumped
 * in tools/templates/ and in all sixteen pages; it is bumped here now, once.
 * docs/20-deployment/routine-deploys.md, "Cache busting".
 */
const HEAD_STYLES = [
    'base.css',
    'theme.css',
    'layout.css?v=4',
    'components.css',
    'animations.css',
];

/**
 * The favicon set, in the order a browser reads it.
 *
 * Whole lines rather than an attribute table, because these are FILES and not
 * content: nothing here comes from a document, nothing is escaped, and the
 * only thing that could go wrong is naming a file that is not there --
 * tools/audit_pages.py resolves them. A table would also have to carry the
 * attribute ORDER to stay byte-identical with what these pages already send,
 * which is a lot of machinery for six constant lines.
 */
const HEAD_ICONS = [
    '<link rel="icon" href="/assets/images/favicon/favicon.ico" sizes="any">',
    '<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon/favicon-16.png">',
    '<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon/favicon-32.png">',
    '<link rel="icon" type="image/png" sizes="48x48" href="/assets/images/favicon/favicon-48.png">',
    '<link rel="icon" type="image/png" sizes="96x96" href="/assets/images/favicon/favicon-96.png">',
    '<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon/apple-touch-icon.png">',
];

/**
 * The Content Security Policy, as defence in depth.
 *
 * NOTE: X-Frame-Options and X-Content-Type-Options are ignored in <meta> by
 * every browser -- they are set for real in .htaccess, which is the
 * authoritative source. Referrer-Policy and CSP genuinely do work here, and
 * are kept in case the host strips response headers.
 */
const HEAD_CSP = "default-src 'self'; img-src 'self' data:; style-src 'self'; "
               . "script-src 'self'; font-src 'self'; form-action 'self'; "
               . "frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

/** The pretty-printing every JSON-LD block on this site uses. */
const HEAD_JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

/**
 * Everything between <head> and the page's own structured data.
 *
 * $route   this page's address, with its trailing slash. '' for the 404.
 * $meta    this page's meta band, out of its own content document.
 * $styles  its own stylesheets, as paths under /assets/css/.
 */
function seo_head(string $route, array $meta, array $styles = [],
                  string $updated = ''): void
{
    $site  = seo_site();
    $share = seo_share($meta);
    $url   = seo_url($route);

    $title       = (string)($meta['title'] ?? '');
    $description = (string)($meta['description'] ?? '');
    $shareTitle  = (string)($meta['share_title'] ?? '') !== ''
        ? (string)$meta['share_title'] : $title;
    $robots      = (string)($meta['robots'] ?? 'index');

    $out = [];
    $out[] = '<meta charset="utf-8">';
    $out[] = '<meta name="viewport" content="width=device-width, initial-scale=1">';
    $out[] = '';
    $out[] = '<title>' . h($title) . '</title>';
    $out[] = '<meta name="description" content="' . h($description) . '">';

    /* A canonical is a claim that this address is the right one for this page.
       The 404 is served at every address that does not exist, so it has no
       such claim to make and must not be given one. */
    if ($url !== '') {
        $out[] = '<link rel="canonical" href="' . h($url) . '">';
    }

    $out[] = '';
    $out[] = $robots === 'noindex'
        ? "<!-- An error page must never be indexed, but its links should still be\n"
        . "     followed so crawl equity flows back into the site. -->"
        : "<!-- Crawling. Large image previews and full snippets are allowed so rich\n"
        . "     results can use the branded share card. -->";
    $out[] = '<meta name="robots" content="' . h(seo_robots_directive($robots)) . '">';

    /* The two tags that let somebody claim this site in a search engine's
       console. Emitted only when there is a token: a verification tag naming
       nobody is noise in every head on the site. Without one of these there is
       no Search Console, and no way to see a query the site ranks for, a page
       that was refused indexing, or a structured-data error. */
    $verify = [
        'google-site-verification' => (string)(seo_load()['crawl']['verify_google'] ?? ''),
        'msvalidate.01'            => (string)(seo_load()['crawl']['verify_bing'] ?? ''),
    ];
    $verify = array_filter($verify, static fn(string $v): bool => trim($v) !== '');
    if ($verify !== []) {
        $out[] = '';
        $out[] = '<!-- Site ownership, for the search consoles. -->';
        foreach ($verify as $name => $token) {
            $out[] = '<meta name="' . h($name) . '" content="' . h(trim($token)) . '">';
        }
    }

    $out[] = '';
    $out[] = "<!-- Security. NOTE: X-Frame-Options and X-Content-Type-Options are ignored in\n"
           . "     <meta> by every browser — they are set for real in .htaccess, which is the\n"
           . "     authoritative source. Referrer-Policy and CSP genuinely do work here, and\n"
           . "     are kept as defence in depth in case the host strips response headers. -->";
    $out[] = '<meta name="referrer" content="strict-origin-when-cross-origin">';
    /* NOT through h(). The policy is a constant in this file, contains no
       stored value, and its apostrophes are syntax: htmlspecialchars would
       turn 'self' into &#039;self&#039; and the browser would refuse the whole
       policy. The rule that everything editable goes through h() is intact --
       nothing here is editable. */
    $out[] = '<meta http-equiv="Content-Security-Policy" content="' . HEAD_CSP . '">';

    $out[] = '';
    $out[] = '<!-- Open Graph -->';
    $out[] = '<meta property="og:type" content="' . h($site['og_type']) . '">';
    $out[] = '<meta property="og:locale" content="' . h($site['locale']) . '">';
    $out[] = '<meta property="og:site_name" content="' . h($site['name']) . '">';
    $out[] = '<meta property="og:title" content="' . h($shareTitle) . '">';
    $out[] = '<meta property="og:description" content="' . h($description) . '">';
    if ($url !== '') {
        $out[] = '<meta property="og:url" content="' . h($url) . '">';
    }
    /* WHEN THE PAGE LAST CHANGED, WHICH THE SITE HAS NEVER SAID. Every
       document has carried an `updated` stamp since api/publish.php started
       setting it, and every page threw it away. Freshness is a real input, and
       a page that says when it changed is a page a crawler can decide to
       revisit; one that says nothing has to be guessed at. Emitted only when
       there is a stamp -- a page that has never been published makes no
       claim, the same rule the sitemap's lastmod follows. */
    $day = seo_stamp($updated);
    if ($day !== '') {
        $out[] = '<meta property="og:updated_time" content="' . h($day) . '">';
    }
    if ($share !== []) {
        $out[] = '<meta property="og:image" content="' . h($share['url']) . '">';
        $out[] = '<meta property="og:image:width" content="' . h((string)$share['width']) . '">';
        $out[] = '<meta property="og:image:height" content="' . h((string)$share['height']) . '">';
        $out[] = '<meta property="og:image:alt" content="' . h($share['alt']) . '">';
    }

    $out[] = '';
    $out[] = '<!-- Twitter -->';
    $out[] = '<meta name="twitter:card" content="' . h($site['twitter_card']) . '">';
    $out[] = '<meta name="twitter:title" content="' . h($shareTitle) . '">';
    $out[] = '<meta name="twitter:description" content="' . h($description) . '">';
    if ($share !== []) {
        $out[] = '<meta name="twitter:image" content="' . h($share['url']) . '">';
        $out[] = '<meta name="twitter:image:alt" content="' . h($share['alt']) . '">';
    }

    $out[] = '';
    $out[] = '<!-- Icons -->';
    foreach (HEAD_ICONS as $icon) {
        $out[] = $icon;
    }
    $out[] = '<link rel="manifest" href="/site.webmanifest">';
    $out[] = '<meta name="theme-color" media="(prefers-color-scheme: light)" content="'
           . h($site['theme_light']) . '">';
    $out[] = '<meta name="theme-color" media="(prefers-color-scheme: dark)" content="'
           . h($site['theme_dark']) . '">';

    $out[] = '';
    $out[] = "<!-- Fonts. Preloaded because the latin subset is on the critical render path;\n"
           . "     the -ext subset is not preloaded since most pages never reference it. -->";
    $out[] = '<link rel="preload" href="/assets/fonts/inter-latin.woff2" as="font" '
           . 'type="font/woff2" crossorigin>';

    $out[] = '';
    $out[] = "<!-- Styles, in cascade order.\n"
           . "\n"
           . "     THE VERSION QUERY IS THE CACHE BUST, AND IT IS NOT DECORATION\n"
           . "     Filenames are not content-hashed — there is no build step to hash them —\n"
           . "     and .htaccess caches CSS for a year. A changed stylesheet does not reach\n"
           . "     anybody who has been here before unless this string changes with it, so\n"
           . "     bump it in the same breath as the file. Forget, and the release is for\n"
           . "     new visitors only, which looks like nothing at all from here.\n"
           . "     docs/20-deployment/routine-deploys.md, \"Cache busting\" -->";
    foreach ([...HEAD_STYLES, ...$styles] as $sheet) {
        $out[] = '<link rel="stylesheet" href="/assets/css/' . h($sheet) . '">';
    }

    $out[] = '';
    $out[] = "<!-- Colour mode, applied before first paint to avoid a flash of the wrong\n"
           . "     theme. Deliberately NOT deferred; see the comment in the file itself. -->";
    $out[] = '<script src="/assets/js/theme-init.js"></script>';

    echo implode("\n", $out), "\n";
}

/* ------------------------------------------------------ structured data */

/**
 * The graph every page carries: who this is, what the site is, what it sells.
 *
 * The addresses and telephone numbers come from content/contact.json through
 * contact_addresses() and contact_points(), which the contact page has always
 * used and the other sixteen pages never did. That is the whole of the fix for
 * a graph that went stale sixteen pages at a time.
 */
function seo_graph(): array
{
    $site     = seo_site();
    $identity = seo_identity();
    $contact  = contact_load();
    $home     = seo_url('/');
    $share    = seo_share([]);

    $organization = [
        '@type'         => 'Organization',
        '@id'           => SEO_ORIGIN . '/#organization',
        'name'          => $site['name'],
        'alternateName' => $identity['alternate_name'],
        'url'           => $home,
        'logo'          => [
            '@type'  => 'ImageObject',
            'url'    => seo_url((string)$identity['logo']['src']),
            'width'  => (int)$identity['logo']['width'],
            'height' => (int)$identity['logo']['height'],
        ],
        'image'         => $share['url'] ?? '',
        'description'   => $identity['description'],
        'slogan'        => $identity['slogan'],
        'foundingDate'  => $identity['founded'],
        'email'         => contact_email($contact),
        'areaServed'    => $identity['area_served'],
        'knowsLanguage' => $site['lang'],
        'address'       => contact_addresses($contact),
        'contactPoint'  => contact_points($contact),
        'sameAs'        => array_values(array_map(
            static fn(array $row): string => (string)$row['url'],
            array_filter(seo_shown(seo_load(), 'sameas'),
                         static fn(array $row): bool => trim((string)$row['url']) !== '')
        )),
    ];

    $website = [
        '@type'       => 'WebSite',
        '@id'         => SEO_ORIGIN . '/#website',
        'url'         => $home,
        'name'        => $site['name'],
        'description' => $site['description'],
        'publisher'   => ['@id' => SEO_ORIGIN . '/#organization'],
        'inLanguage'  => $site['lang'],
    ];

    $service = [
        '@type'                    => 'ProfessionalService',
        '@id'                      => SEO_ORIGIN . '/#service',
        'name'                     => $site['name'],
        'url'                      => $home,
        'image'                    => $share['url'] ?? '',
        'parentOrganization'       => ['@id' => SEO_ORIGIN . '/#organization'],
        'priceRange'               => $identity['price_range'],
        'areaServed'               => $identity['area_served'],
        'openingHoursSpecification' => array_values(array_map(
            static fn(array $row): array => array_filter([
                '@type'       => 'OpeningHoursSpecification',
                'dayOfWeek'   => $row['days'],
                'opens'       => $row['opens'],
                'closes'      => $row['closes'],
                'description' => $row['label'],
            ], static fn($v): bool => $v !== '' && $v !== []),
            array_filter(seo_shown(seo_load(), 'hours'),
                         static fn(array $row): bool => $row['days'] !== [])
        )),
        'serviceType'              => $identity['service_types'],
        'knowsAbout'               => $identity['knows_about'],
    ];

    $graph = [];
    foreach ([$organization, $website, $service, ...seo_offices($contact)] as $node) {
        $graph[] = array_filter(
            $node,
            static fn($v): bool => $v !== '' && $v !== [] && $v !== null
        );
    }

    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

/**
 * One LocalBusiness node per office, which this site has never had either.
 *
 * The three offices existed only as PostalAddress entries inside the
 * Organization -- correct, and not what a local result reads. A local pack is
 * built from places, and a place needs its own @id, its own address, its own
 * telephone and its own hours. Everything below already exists in
 * content/contact.json and is already on the contact page; nothing here is a
 * new fact, only a shape a search engine has a definition for.
 *
 * The hours are matched to an office by the label somebody typed on the SEO
 * screen -- "Bangladesh office" against the office named "Bangladesh". A row
 * that matches nothing is left off that office rather than attached to all of
 * them, because opening hours on the wrong continent are worse than none.
 */
function seo_offices(array $contact): array
{
    $hours = seo_shown(seo_load(), 'hours');
    $out   = [];

    foreach (contact_shown_offices($contact) as $office) {
        $name    = trim((string)$office['name']);
        $schema  = $office['schema'];
        $country = strtoupper(trim((string)$schema['country']));

        $address = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => trim((string)$schema['street']),
            'addressLocality' => trim((string)$schema['locality']),
            'addressRegion'   => trim((string)$schema['region']),
            'postalCode'      => trim((string)$schema['postal_code']),
            'addressCountry'  => $country,
        ], static fn(string $v): bool => $v !== '');

        /* A country on its own is not a place anybody can visit. Same test
           contact_addresses() applies, and for the same reason. */
        if ($name === '' || count($address) <= 2) {
            continue;
        }

        $node = [
            '@type'              => 'LocalBusiness',
            '@id'                => SEO_ORIGIN . '/#office-' . rawurlencode(strtolower($name)),
            'name'               => seo_site()['name'] . ' — ' . $name,
            'parentOrganization' => ['@id' => SEO_ORIGIN . '/#organization'],
            'url'                => seo_url('/pages/contact/'),
            'address'            => $address,
            'telephone'          => array_values(array_map(
                static fn($phone): string => contact_tel((string)$phone),
                $office['phones']
            )),
            'email'              => contact_email($contact),
            'priceRange'         => seo_identity()['price_range'],
        ];

        $matched = array_values(array_filter(
            $hours,
            static fn(array $row): bool =>
                $row['days'] !== []
                && stripos((string)$row['label'], $name) !== false
        ));

        if ($matched !== []) {
            $node['openingHoursSpecification'] = array_map(
                static fn(array $row): array => array_filter([
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => $row['days'],
                    'opens'     => $row['opens'],
                    'closes'    => $row['closes'],
                ], static fn($v): bool => $v !== '' && $v !== []),
                $matched
            );
        }

        $out[] = $node;
    }

    return $out;
}

/**
 * The base graph, and this page's trail.
 *
 * A page's own schema -- Service, JobPosting, ContactPage, AboutPage -- is
 * generated by that page from its own document and printed after this.
 */
function seo_jsonld(string $route, array $meta, string $updated = ''): void
{
    echo "<!-- Who this is, what the site is, and what it sells. Identical on every\n",
         "     page, and generated rather than pasted: the addresses and telephone\n",
         "     numbers come from content/contact.json, so editing an office in the\n",
         "     admin changes every page at once. Per-page schema goes after it. -->\n";
    echo '<script type="application/ld+json">', "\n";
    echo json_encode(seo_graph(), HEAD_JSON_FLAGS), "\n";
    echo '</script>', "\n";

    $crumbs = seo_breadcrumb($route, (string)($meta['breadcrumb'] ?? ''));

    if ($crumbs !== []) {
        echo "\n<!-- Where this page sits, built from its address rather than numbered by\n",
             "     hand. See seo_breadcrumb(). -->\n";
        echo '<script type="application/ld+json">', "\n";
        echo json_encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        ], HEAD_JSON_FLAGS), "\n";
        echo '</script>', "\n";
    }

    $page = seo_page_node($route, $meta, $updated, $crumbs);
    if ($page === []) {
        return;
    }

    echo "\n<!-- The page itself, as a node of the graph above. Without it each block\n",
         "     on the page is an island: this is what says the trail, the share card\n",
         "     and the site all belong to THIS address, and what carries the date the\n",
         "     page last changed. -->\n";
    echo '<script type="application/ld+json">', "\n";
    echo json_encode($page, HEAD_JSON_FLAGS), "\n";
    echo '</script>', "\n";
}

/**
 * The WebPage node, which this site has never had.
 *
 * It ties the four things a crawler otherwise has to guess are related: this
 * URL, the WebSite it is part of, the trail that leads to it and the picture
 * that represents it -- plus dateModified, which is the freshness signal every
 * document has carried and no page has ever emitted.
 *
 * Returns [] for a page with no address. The 404 is served everywhere and is
 * a page about nothing; a WebPage node for it would name a URL it does not
 * have.
 */
function seo_page_node(string $route, array $meta, string $updated, array $crumbs): array
{
    if ($route === '') {
        return [];
    }

    $url   = seo_url($route);
    $share = seo_share($meta);
    $day   = seo_stamp($updated);

    $node = [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebPage',
        '@id'         => $url . '#webpage',
        'url'         => $url,
        'name'        => (string)($meta['title'] ?? ''),
        'description' => (string)($meta['description'] ?? ''),
        'isPartOf'    => ['@id' => SEO_ORIGIN . '/#website'],
        'about'       => ['@id' => SEO_ORIGIN . '/#organization'],
        'inLanguage'  => seo_site()['lang'],
    ];

    if ($day !== '') {
        $node['dateModified'] = $day;
    }
    if ($share !== []) {
        $node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $share['url']];
    }
    if ($crumbs !== []) {
        $node['breadcrumb'] = [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        ];
    }

    return $node;
}

/**
 * A publish stamp as a date, or '' when there has never been one.
 *
 * Same rule as seo_document_day(): a page that has never been published says
 * nothing about when it changed, rather than saying today.
 */
function seo_stamp(string $updated): string
{
    $day = substr(trim($updated), 0, 10);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 ? $day : '';
}
