<?php
/**
 * Tech4TIME — resource certifications page data access.
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
 *     "certs":       { status, eyebrow, title, lead, items: [ group, ... ] }
 *     "cta":         { status, title, text, items: [ button, ... ] }
 *   }
 *
 * A group is a <details> holding role names and certifications:
 *   { id, slug, icon, blurb, status, open,
 *     roles: [ { id, name, status } ],
 *     items: [ { id, name, status } ] }
 *
 * THREE THINGS ARE DRAWN, NOT STORED
 * The count on each group's heading ("27 certifications") is the number of
 * certifications shown inside it. The glyph beside every certification is one
 * constant. The slash between two role names is emitted between them. Storing
 * any of the three would be storing an answer that can disagree with the
 * question, and the page has fifty-four chances to do it.
 *
 * THE TOTALS IN THE PROSE ARE DRAWN TOO
 * The lead and the meta description hold {certifications} and {groups-word},
 * which certifications_fill() replaces as the page renders. See the note on
 * CERTIFICATIONS_TOKENS in lib/contract.php for why they are not typed.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';
require_once __DIR__ . '/sprite.php';

const CERTIFICATIONS_FILE = __DIR__ . '/../content/certifications.json';

/** The document, with every field the renderer reads guaranteed present. */
function certifications_load(): array
{
    return certifications_normalise(store_read(CERTIFICATIONS_FILE) ?? []);
}

/**
 * The glyph markup, which is identical everywhere it appears bar the name.
 *
 * inject_icons.py scans for a literal href="#name", so every name that can
 * reach this function is listed in the comment at the top of
 * pages/resource-certifications/index.php where the scanner can see it.
 */
function certifications_glyph(string $name, string $class): string
{
    return '<svg class="' . h($class) . '" viewBox="0 0 512 512" aria-hidden="true"'
         . ' focusable="false"><use href="#' . h($name) . '"></use></svg>';
}

/**
 * The role names of a group, slashed.
 *
 * The separator is markup rather than a character in the text, because it is
 * hidden from a screen reader: "Security Analyst / Threat Analyst" should be
 * read as two roles, not as a fraction.
 */
function certifications_roles_html(array $group): string
{
    $out = [];
    foreach (certifications_rows_shown($group['roles'] ?? []) as $role) {
        $out[] = '<span class="cert-group__role">' . h((string)$role['name']) . '</span>';
    }

    return implode(
        '<span class="cert-group__role-sep" aria-hidden="true">/</span>' . "\n            ",
        $out
    );
}

/**
 * Every icon name this document actually puts on the page.
 *
 * The group glyphs it is wearing today, plus the two constants the renderer
 * emits for every group and every certification. Hidden rows are excluded:
 * a symbol nothing draws is bytes down the wire for nobody.
 */
function certifications_icons_used(array $data): array
{
    $names = [CERTIFICATIONS_CERT_GLYPH, 'chevron-right'];

    foreach (certifications_rows_shown(certifications_groups($data)) as $group) {
        $names[] = (string)($group['icon'] ?? '');
    }

    return array_values(array_filter(
        array_unique($names),
        static fn($n): bool => trim((string)$n) !== ''
    ));
}
