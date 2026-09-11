<?php
/**
 * Tech4TIME — the brand colours, as a stylesheet.
 *
 * Served at /assets/css/brand.css, loaded from HEAD_STYLES immediately after
 * theme.css so it overrides the tokens that ship. .htaccess rewrites that URL
 * here internally, so it does not change.
 *
 * WHY A FILE AND NOT A style ATTRIBUTE. The Content Security Policy is
 * style-src 'self' — no <style> block, no style= attribute, no exceptions
 * (ADR 0004). A colour somebody picked has to reach the page as a stylesheet
 * or it does not reach the page. Same recipe as sitemap.php, robots.php,
 * manifest.php and favicon.php: a PHP file behind a static-looking address.
 *
 * IT EMITS ONLY WHAT DIFFERS FROM WHAT SHIPS. With the document absent, or
 * holding the palette it was seeded with, this file is EMPTY — no rules, not
 * even a :root block — so a host that has never received a publish serves an
 * empty stylesheet and renders exactly what theme.css says. That is not an
 * optimisation: it is what makes the whole of this stage provable, because a
 * page whose bytes did not move cannot have had its colours moved.
 *
 * THREE BLOCKS, BECAUSE THE THEME HAS THREE STATES. theme.css declares the
 * light palette on :root, redeclares the dark one under
 * prefers-color-scheme: dark guarded against an explicit light choice, and
 * redeclares it again under [data-theme="dark"] so the switch wins in both
 * directions. An override that wrote only :root would repaint dark mode with
 * light colours, so this mirrors all three.
 */

declare(strict_types=1);

require __DIR__ . '/../../lib/settings.php';

/**
 * A year, matching .htaccess's rule for the rest of assets/.
 *
 * Safe because the URL moves with the palette: head_styles() appends the
 * settings document's own revision, so a changed colour is a changed address
 * and a year-long cache never hides one. That is the same bargain every other
 * stylesheet here takes; the only difference is that this one's version is
 * minted by a save rather than typed by a developer.
 *
 * The ETag is for the case the query does NOT change — a request for the bare
 * address, from a browser that kept an old page, or a probe. It costs one
 * round trip and answers 304.
 */
const BRAND_MAX_AGE = 31536000;

/** Only the tokens this palette states differently from the shipped one. */
function brand_overrides(array $colours, string $mode): array
{
    $out = [];

    foreach (SETTINGS_COLOURS[$mode] as $token => $shipped) {
        $held = strtolower(trim((string)($colours[$token] ?? '')));

        if ($held !== '' && $held !== $shipped) {
            $out[$token] = $held;
        }
    }

    return $out;
}

/** One block of custom properties, or '' when there is nothing to say. */
function brand_block(string $selector, array $overrides, string $indent = ''): string
{
    if ($overrides === []) {
        return '';
    }

    $out = $indent . $selector . " {\n";

    foreach ($overrides as $token => $value) {
        $out .= $indent . '  --' . $token . ': ' . $value . ";\n";
    }

    return $out . $indent . "}\n";
}

$settings = settings_load();
$light    = brand_overrides(settings_colours($settings, 'light'), 'light');
$dark     = brand_overrides(settings_colours($settings, 'dark'), 'dark');

$css = brand_block(':root', $light);

if ($dark !== []) {
    $css .= "\n@media (prefers-color-scheme: dark) {\n"
          . brand_block(':root:not([data-theme="light"])', $dark, '  ')
          . "}\n\n"
          . brand_block(':root[data-theme="dark"]', $dark);
}

/* A version that moves with the document rather than with this file, because
   this file will not change again and the colours will. It is the revision,
   which contract_next_revision() makes monotonic, so a browser holding last
   week's palette asks again the moment a new one is published. */
$revision = (int)($settings['revision'] ?? 0);

header('Content-Type: text/css; charset=utf-8');
header('Content-Length: ' . strlen($css));
header('Cache-Control: public, max-age=' . BRAND_MAX_AGE);
header('X-Content-Type-Options: nosniff');
header('ETag: "brand-' . $revision . '"');

echo $css;
