<?php
/**
 * Tech4TIME — the site's identity: the mark, the icons, the colours, data access.
 *
 * Reading the file is lib/store.php; escaping is lib/html.php; the SHAPE is
 * lib/contract.php, which the frontend and the backend hold byte-identical.
 * What is left here is this side's own business with that shape.
 *
 * Nothing here writes. The only thing on this host that writes content at all
 * is api/publish.php, landing a document the backend signed.
 *
 * WHAT THIS DOCUMENT IS, AND WHY IT IS NOT PART OF ANOTHER. Everything in it
 * is read by several pages and owned by none. The logo alone is drawn in the
 * header, the footer, the About page and the admin's own rail, and named in
 * Organization.logo, in JobPosting's hiring organisation, in the favicon set
 * and in the branding kit. Put it in any one document and the other eight
 * consumers read a document about something else.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":   set on every save
 *     "revision":  monotonic; see contract.php
 *     "logo":      { light: <picture>, dark: <picture> }
 *     "icon":      { master: <picture>, generated: { ico, png16 … apple } }
 *     "colours":   { light: { <token>: "#rrggbb" }, dark: { … } }
 *     "contact":   { mail_to, mail_subject }
 *   }
 *
 * A <picture> here is the record contract_image_defaults() fills, the same as
 * every uploaded picture on the site: { src, webp, width, height, srcset,
 * webp_srcset }. That is what lets the sweep, the publish channel and the
 * renderers treat the logo as an ordinary picture rather than a special case
 * -- which is what the chrome's own logo fields were, and what they will stop
 * being.
 *
 * A MISSING FILE IS NOT AN ERROR, and here that matters more than anywhere
 * else. settings_normalise() fills from settings_defaults(), which is the
 * site's own mark, its own icons and its own colours exactly as they ship. A
 * host that has never received a publish therefore renders what it renders
 * today, byte for byte -- tools/test_settings.py asserts it.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';

const SETTINGS_FILE = __DIR__ . '/../content/settings.json';

/**
 * The document, with every field a renderer reads guaranteed present.
 *
 * Memoised, like the chrome's and for the same reason: this one is read many
 * times in a single request -- the head asks for the icons, the header and the
 * footer each ask for a logo, the About page asks for another -- where a
 * page's own document is read once and passed around.
 */
function settings_load(): array
{
    static $data = null;

    if ($data === null) {
        $data = settings_normalise(store_read(SETTINGS_FILE) ?? []);
    }

    return $data;
}

/**
 * The mark for one theme, falling back to the light one.
 *
 * AN EMPTY DARK HALF IS AN ANSWER, NOT AN OMISSION. Plenty of marks are a
 * single colour and read on both grounds, so requiring two uploads would be
 * friction for no gain. What must not happen is the other failure: an empty
 * dark half rendering as NOTHING, which would put a hole in the header of
 * every page in dark mode. So absent means "use the light one", and the
 * editor carries a standing notice saying so in words -- because a light-ink
 * mark on a dark ground is invisible, and only the person who drew it knows
 * whether theirs is.
 *
 * $mode is anything but 'dark' meaning light, rather than being validated,
 * because every caller passes a literal and the fallback is the safe half.
 */
function settings_logo(array $settings, string $mode = 'light'): array
{
    $light = $settings['logo']['light'] ?? [];

    if ($mode !== 'dark') {
        return contract_image_defaults($light);
    }

    $dark = $settings['logo']['dark'] ?? [];

    return contract_image_defaults(
        trim((string)($dark['src'] ?? '')) === '' ? $light : $dark
    );
}

/** True when dark mode is showing the light mark because nothing else was set. */
function settings_logo_is_shared(array $settings): bool
{
    return trim((string)($settings['logo']['dark']['src'] ?? '')) === '';
}

/**
 * The largest rendition of the mark: what the About page's lockup, the
 * Organization graph and every job posting name.
 *
 * THREE PLACES DRAW THIS MARK BIG AND ONE DRAWS IT SMALL. The header wants the
 * rung its 113px slot can use; the About row draws it at up to 693px, and the
 * two structured-data graphs want one absolute URL for a consumer that picks
 * nothing. So the largest rung is asked for rather than assumed — the record's
 * src is the 360px file, and the ladder goes on to 540.
 *
 * The returned record carries no ladder of its own: everything that asks for
 * this wants ONE file. Its height is scaled from the record's, which is exact
 * rather than approximate — every rung of a ladder is the same picture, so the
 * ratio is the same, and 128 × 540 ÷ 360 is 192 on the nose.
 */
function settings_logo_largest(array $settings, string $mode = 'light'): array
{
    $image = settings_logo($settings, $mode);
    $top   = contract_srcset_top((string)$image['srcset']);

    if ($top['src'] === '' || (int)$image['width'] <= 0
            || $top['width'] <= (int)$image['width']) {
        /* No ladder, or none of it wider than src: src IS the largest there
           is. That is the case for every uploaded mark, because upload_store()
           names the top rung as src. */
        return ['src' => $image['src'], 'webp' => $image['webp'],
                'width' => $image['width'], 'height' => $image['height'],
                'srcset' => '', 'webp_srcset' => ''];
    }

    $webp = contract_srcset_top((string)$image['webp_srcset']);

    return [
        'src'    => $top['src'],
        'webp'   => $webp['width'] === $top['width'] ? $webp['src'] : '',
        'width'  => $top['width'],
        'height' => (int)round((int)$image['height'] * $top['width'] / (int)$image['width']),
        'srcset' => '',
        'webp_srcset' => '',
    ];
}

/**
 * The colour tokens for one theme, as name => '#rrggbb'.
 *
 * Always the full set: settings_normalise() fills any token a document is
 * missing from the shipped value, so a caller writing a stylesheet never has
 * to decide what to do about a gap.
 */
function settings_colours(array $settings, string $mode = 'light'): array
{
    $mode = $mode === 'dark' ? 'dark' : 'light';

    return is_array($settings['colours'][$mode] ?? null)
        ? $settings['colours'][$mode]
        : SETTINGS_COLOURS[$mode];
}
