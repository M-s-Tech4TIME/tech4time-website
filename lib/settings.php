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
 * Where one generated icon is, or the committed file it falls back to.
 *
 * THE FALLBACK IS NOT A NICETY. Until somebody uploads a square mark there is
 * nothing generated at all, and every page still has to name a favicon -- so
 * an empty slot answers with the file that ships, which is what makes a host
 * that has never received a publish look exactly like one that has.
 *
 * $name is a key of SETTINGS_ICON_SIZES; $shipped is the committed file's path.
 */
function settings_icon(array $settings, string $name, string $shipped): string
{
    $held = trim((string)($settings['icon']['generated'][$name] ?? ''));

    return $held !== '' ? $held : $shipped;
}

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

