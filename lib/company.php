<?php
/**
 * Tech4TIME — company profile page data access.
 *
 * Reading the file is lib/store.php; escaping and rich-text sanitising is
 * lib/html.php; the SHAPE of the page is lib/contract.php, which the frontend
 * and the backend hold byte-identical. What is left here is this side's own
 * business with that shape.
 *
 * On THIS side that is: the AboutPage structured data and the <picture> a row
 * of artwork turns into. Validation, the uploads and saving are the backend's
 * — nothing here writes the company profile. The only thing on this host that
 * writes content at all is api/publish.php, landing a document the backend
 * signed.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":    set on every save
 *     "revision":   monotonic; see contract.php
 *     "meta":       { title, description, share_title }
 *     "hero":       { title, subtitle }
 *     "milestones": { status, eyebrow, title, lead,
 *                     items[ { id, year, title, text, status } ] }
 *     "background": { status, eyebrow, title }
 *     "experience": { status, title, items[ { id, figure, label, status } ] }
 *     "clients":    { status, title, items[ { id, name, image{}, status } ] }
 *     "journey":    { status, title, lead, interval,
 *                     items[ { id, alt, image{}, status } ] }
 *     "excellence": { status, eyebrow, title, lead }
 *     "technology": { status, title, items[ { id, name, image{}, status } ] }
 *     "principles": { status, title, items[ { id, icon, title, text, status } ] }
 *     "cta":        { status, title, text, label, href, icon }
 *   }
 *
 * and an image{} is { src, webp, width, height } — see contract_image_defaults().
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
/* The site's name, origin and founding date. The schema on this page must say
   what the Organization graph on the same page says, and that one reads the
   document -- so this must too, rather than keeping its own copy. */
require_once __DIR__ . '/seo.php';

const COMPANY_FILE = __DIR__ . '/../content/company.json';

/* THE FOUNDING DATE IS NOT HERE, AND THE ARGUMENT FOR PUTTING IT HERE WAS
   WRONG. It read: a fact about the company rather than copy about it, so it is
   not in the editor. It IS in the editor -- ?s=seo&site=identity, as
   identity.founded -- and has been since that screen shipped. So this file
   held a second copy of an editable fact, and the Organization graph in
   lib/head.php read the editable one while the AboutPage graph on this page
   read the constant. Two graphs on one page, disagreeing the moment anybody
   touched the field. seo_identity()['founded'] is what both read now. */

/**
 * The company profile as it should be rendered.
 *
 * Never throws. A missing, unreadable or damaged file falls back field by
 * field to what company_defaults() ships with, so the page is stale at worst
 * and never blank.
 */
function company_load(): array
{
    return company_normalise(store_read(COMPANY_FILE) ?? []);
}

/* ------------------------------------------------------------- the artwork */

/**
 * One picture, as the markup the page has always carried.
 *
 * Emitted as a single line with no whitespace between the tags, because
 * <picture> and <img> are inline and a newline between them is a space the
 * browser renders. The attribute order is fixed for the same reason every
 * other repeated block here is: so a diff shows a change rather than a
 * reshuffle.
 *
 * A row with no WebP sibling gets a bare <img> and NO <picture> wrapper. That
 * is not a shortcut — it is what an SVG or an AVIF entry has always been, and
 * a <picture> holding one <img> and no <source> would be markup that says a
 * choice is being made when none is.
 *
 * width and height are omitted only when the document does not have them,
 * which the editor does not allow and a hand-edited file might. Guessing would
 * move the page; leaving them out says "unknown", which is true.
 */
function company_picture(array $image, string $class, string $alt,
                         string $slot = ''): string
{
    $src = trim((string)($image['src'] ?? ''));
    if ($src === '') {
        return '';
    }

    $size = '';
    if (($image['width'] ?? 0) > 0 && ($image['height'] ?? 0) > 0) {
        $size = ' width="' . (int)$image['width'] . '"'
              . ' height="' . (int)$image['height'] . '"';
    }

    /* One picture at several widths, when this one was stored that way and
       the slot says how wide it is drawn. Both halves or neither: see
       contract_picture_ladder(). THE SLOT IS A PARAMETER HERE and a literal in
       the other four renderers, because this page draws three different
       pictures -- a client's logo, a journey photograph and a technology mark
       -- at three different widths, through one function. */
    $ladder = contract_picture_ladder($image, $slot);
    $rungs  = $ladder['srcset'] === '' ? ''
            : ' srcset="' . h($ladder['srcset']) . '"'
            . ' sizes="' . h($ladder['sizes']) . '"';

    $img = '<img class="' . h($class) . '" src="' . h($src) . '"' . $rungs
         . ' alt="' . h($alt) . '"' . $size
         . ' loading="lazy" decoding="async">';

    $webp = trim((string)($image['webp'] ?? ''));
    if ($webp === '') {
        return $img;
    }

    $source = $ladder['webp_srcset'] === ''
        ? '<source srcset="' . h($webp) . '" type="image/webp">'
        : '<source srcset="' . h($ladder['webp_srcset']) . '"'
        . ' sizes="' . h($ladder['sizes']) . '" type="image/webp">';

    return '<picture>' . $source . $img . '</picture>';
}

/* --------------------------------------------------------- structured data */

/**
 * The AboutPage graph for this page.
 *
 * Kept here rather than in the contract because the backend does not render
 * the page and has no use for it — the same line careers_job_posting() and
 * contact_page_schema() sit on.
 */
function company_page_schema(array $data): array
{
    $graph = [
        '@context' => 'https://schema.org',
        '@type'    => 'AboutPage',
        'url'      => seo_url('/pages/company-profile/'),
        'name'     => (string)$data['hero']['title'],
        'description' => rt_plain((string)$data['meta']['description']),
        'about'    => [
            '@type' => 'Organization',
            'name'  => seo_site()['name'],
            'url'   => seo_url('/'),
            'foundingDate' => seo_identity()['founded'],
        ],
    ];

    /* The milestones, as the events they describe. Only the ones a visitor can
       see: a hidden entry is hidden from a crawler too, or the markup and the
       graph would disagree about what the page says. */
    $events = [];
    foreach (company_shown($data, 'milestones') as $row) {
        $year = trim((string)$row['year']);
        $event = [
            '@type' => 'Event',
            'name'  => (string)$row['title'],
            'description' => rt_plain((string)$row['text']),
        ];
        if (preg_match('/^\d{4}$/', $year)) {
            $event['startDate'] = $year;
        }
        $events[] = $event;
    }

    if ($events) {
        $graph['mainEntity'] = [
            '@type' => 'ItemList',
            'name'  => (string)$data['milestones']['title'],
            'itemListElement' => array_map(
                static fn(int $i, array $e): array => [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'item'     => $e,
                ],
                array_keys($events),
                $events
            ),
        ];
    }

    return $graph;
}
