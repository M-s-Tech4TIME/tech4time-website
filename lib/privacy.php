<?php
/**
 * Tech4TIME — privacy policy page data access.
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
 *     "meta":        { title, description, share_title, breadcrumb }
 *     "hero":        { title, subtitle }
 *     "policy":      { label, effective, callout, sections: [ section, ... ] }
 *     "cta":         { status, title, text, items: [ button, ... ] }
 *   }
 *
 * A section is one headed part of the policy, and its id is its ANCHOR:
 *   { id, heading, status, blocks: [ block, ... ] }
 *
 * A block is one of six shapes, and the KIND decides the markup:
 *   paragraph  note  address     { text }        rich
 *   subheading                   { text }        plain
 *   list                         { rows[] }      rich rows
 *   table                        { caption, columns[2], rows[] }   plain
 *
 * WHY STRUCTURE IS A KIND AND NOT MARKUP
 * rt_sanitise_html() allows nine tags and no heading, no <address> and no
 * <table> among them. A person typing <h3> into a rich field would watch it
 * disappear on save with no way to tell that from a bug. So the renderer owns
 * the structure and the rich field carries only what belongs in a paragraph.
 *
 * WHAT IS PRINTED BARE
 * A paragraph, a note, an address and a list row are rich text and go out
 * unescaped — sanitised on the way in by contract_sanitise(), and again on
 * receipt, because a signature proves where a document came from and not what
 * is inside it. Everything else goes through h().
 *
 * NO ICONS, AND NO SPRITE
 * The policy body carries no glyph of any kind, so unlike the certifications
 * page there is no second sprite here and unlike the branding page there is no
 * constant to inline. tools/inject_icons.py needs nothing from this file.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';

const PRIVACY_FILE = __DIR__ . '/../content/privacy.json';

/** The document, with every field the renderer reads guaranteed present. */
function privacy_load(): array
{
    return privacy_normalise(store_read(PRIVACY_FILE) ?? []);
}

/**
 * One block, as the markup its kind means.
 *
 * FLAT SIBLINGS, DELIBERATELY. Nothing here wraps a section in a container.
 * assets/css/pages/legal.css zeroes the top margin of the first heading with
 * `.legal__body > .legal__heading:first-of-type`, and a wrapper would make
 * that child combinator match nothing — every heading, including the first,
 * would gain a space it should not have. The blocks are emitted at the same
 * depth the hand-written page emitted them.
 *
 * The default arm is unreachable for a normalised document: an unknown kind
 * has already become a paragraph in privacy_block_defaults(), which keeps the
 * words rather than dropping them. It is here because a renderer that trusts
 * its input to be normalised is a renderer that breaks the day it is not.
 */
function privacy_block(array $block): string
{
    $kind = (string)($block['kind'] ?? '');
    $text = (string)($block['text'] ?? '');

    return match ($kind) {
        'paragraph'  => '<p>' . $text . '</p>',
        'note'       => '<p class="legal__notice">' . $text . '</p>',
        'address'    => '<address class="legal__address">' . $text . '</address>',
        'subheading' => '<h3 class="legal__subheading">' . h($text) . '</h3>',
        'list'       => privacy_list($block),
        'table'      => privacy_table($block),
        default      => '',
    };
}

/** A bulleted list. Its rows are rich text; the <li> is ours. */
function privacy_list(array $block): string
{
    $out = '<ul class="legal__list">';

    foreach (privacy_rows_shown($block['rows'] ?? []) as $row) {
        $out .= '<li>' . (string)($row['text'] ?? '') . '</li>';
    }

    return $out . '</ul>';
}

/**
 * A two-column table, and every cell escaped.
 *
 * Plain on purpose. A retention period is a fact, and a link or an emphasis in
 * one changes what the row appears to promise. The <caption> is read out
 * before the table and shown to nobody: without it a screen reader announces
 * "table" and the listener has to infer what it holds from the first cell.
 *
 * The first cell of each row is a <th scope="row">, not a <td>, so a listener
 * moving across a row hears what it is about before hearing the answer.
 */
function privacy_table(array $block): string
{
    $columns = $block['columns'] ?? ['', ''];
    $caption = trim((string)($block['caption'] ?? ''));

    $out = '<div class="legal__table-wrap"><table class="legal__table">';

    if ($caption !== '') {
        $out .= '<caption class="visually-hidden">' . h($caption) . '</caption>';
    }

    $out .= '<thead><tr>';
    foreach ($columns as $column) {
        $out .= '<th scope="col">' . h((string)$column) . '</th>';
    }
    $out .= '</tr></thead><tbody>';

    foreach (privacy_rows_shown($block['rows'] ?? []) as $row) {
        $out .= '<tr><th scope="row">' . h((string)($row['label'] ?? '')) . '</th>'
              . '<td>' . h((string)($row['value'] ?? '')) . '</td></tr>';
    }

    return $out . '</tbody></table></div>';
}
