<?php
/**
 * Tech4TIME — branding & advertisement page data access.
 *
 * Reading the file is lib/store.php; escaping is lib/html.php; the SHAPE of
 * the page is lib/contract.php, which the frontend and the backend hold
 * byte-identical. What is left here is this side's own business with that
 * shape: turning a document into the markup a visitor receives.
 *
 * Nothing here writes. The only thing on this host that writes content at all
 * is api/publish.php, landing a document the backend signed.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":     set on every save
 *     "revision":    monotonic; see contract.php
 *     "meta":        { title, description, share_title }
 *     "hero":        { title, subtitle }
 *     "assets":      { status, eyebrow, title, lead, items: [ asset, ... ] }
 *     "legal":       { status, title, items: [ { id, text, status } ] }
 *     "cta":         { status, title, text, items: [ button, ... ] }
 *   }
 *
 * An asset is one logo variant — a card with a preview and the files to take:
 *   { id, title, text, alt, plate, status,
 *     image: { src, webp, width, height },
 *     files: [ { id, label, filename, status,
 *                file: { src, webp, width, height } } ] }
 *
 * THREE THINGS ARE DRAWN, NOT STORED
 * The dimensions in a file's meta line come off that file's own record, so
 * they cannot claim 1600 x 570 about something that is no longer that size.
 * "Download PNG" states the file's own format. The glyph on every button is
 * one constant. See branding_meta_line() and BRANDING_DOWNLOAD_GLYPH.
 *
 * THE PREVIEW AND THE DOWNLOAD ARE NOT THE SAME PICTURE
 * 'image' is the small thing drawn on the card; 'files' is what a visitor
 * came for, and on the page as it ships those are 800px and 1600px versions
 * of the same mark. See branding_asset_defaults() in lib/contract.php.
 *
 * NOTHING HERE RENDERS AN SVG
 * A vector file may be offered for download — it is linked, never drawn. The
 * preview stays raster, so no SVG is ever parsed by a visitor's browser as a
 * consequence of loading this page. See ADR 0019.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';

const BRANDING_FILE = __DIR__ . '/../content/branding.json';

/** The document, with every field the renderer reads guaranteed present. */
function branding_load(): array
{
    return branding_normalise(store_read(BRANDING_FILE) ?? []);
}

/**
 * A preview picture, or '' when the asset has none.
 *
 * The same function as about_picture() and company_picture(), for the same
 * reasons: width and height come from the document and are OMITTED rather
 * than guessed when it does not carry them, which is why this site's
 * Cumulative Layout Shift is zero rather than nearly zero; and an empty
 * 'webp' means "no WebP sibling, emit a bare <img>" rather than a <picture>
 * wrapping a source that points at nothing.
 *
 * One line, with no whitespace between the tags, because <picture> and <img>
 * are inline and a newline between them is a space on the page.
 */
function branding_picture(array $image, string $class, string $alt): string
{
    $src = trim((string)($image['src'] ?? ''));
    if ($src === '') {
        return '';
    }

    $size = '';
    if (($image['width'] ?? 0) > 0 && ($image['height'] ?? 0) > 0) {
        $size = ' width="' . (int)$image['width'] . '" height="' . (int)$image['height'] . '"';
    }

    $img = '<img class="' . h($class) . '" src="' . h($src) . '"'
         . ' alt="' . h($alt) . '"' . $size . ' loading="lazy" decoding="async">';

    $webp = trim((string)($image['webp'] ?? ''));
    if ($webp === '') {
        return $img;
    }

    return '<picture><source srcset="' . h($webp) . '" type="image/webp">' . $img . '</picture>';
}

/**
 * The glyph on a download button.
 *
 * A constant rather than a field — see BRANDING_DOWNLOAD_GLYPH — and written
 * out here as a literal href="#arrow-down" only in the page itself, where
 * tools/inject_icons.py can see it. This function is not where the scanner
 * looks, so it takes the name rather than spelling it.
 */
function branding_glyph(string $name): string
{
    return '<svg class="icon icon--sm" aria-hidden="true" focusable="false">'
         . '<use href="#' . h($name) . '"></use></svg>';
}
