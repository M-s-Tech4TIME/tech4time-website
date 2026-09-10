<?php
/**
 * Tech4TIME — the run-time icon sprite.
 *
 * FRONTEND ONLY. The backend draws its icons from ADMIN_ICONS and has nothing
 * to inline.
 *
 * WHY A SECOND SPRITE EXISTS AT ALL
 * Chromium and WebKit do not resolve <use href="…#name"> into another
 * document, so a symbol has to be inlined in the page that draws it.
 * tools/inject_icons.py does that by reading a page's SOURCE and writing the
 * block after <body> — which works for every icon a page writes literally, and
 * cannot work for one chosen while the page renders. A service's icon is a
 * field of content/services.json; a dock key's icon is a field of
 * content/chrome.json; neither appears in any page's source, and a tool that
 * greps source cannot see either.
 *
 * So the renderer writes its own block as it goes. This is that block, in one
 * place: lib/services.php, lib/certifications.php and lib/chrome.php each work
 * out WHICH symbols they need and hand the list here.
 *
 * THE MARKERS ARE DELIBERATELY NOT icon-sprite:start/end. Those delimit the
 * block inject_icons.py rewrites, and a second pair would make its non-greedy
 * match end in the wrong place — it would swallow everything between the first
 * start and this end, header included.
 *
 * TWO <symbol> ELEMENTS MAY SHARE AN ID and several do: the chrome's #cogs and
 * a service card's #cogs are the same markup from the same file. The first
 * definition wins, the page renders the same, and tools/audit_pages.py exempts
 * symbol ids from its duplicate-id check for exactly this reason. Costing a
 * few hundred bytes is the price of not making each of these callers know what
 * the others draw.
 */

declare(strict_types=1);

/**
 * The inline sprite holding those symbols and no others.
 *
 * A name with no symbol behind it is skipped rather than fatal. The models
 * already refuse to draw an icon their allow-list does not offer, and a sprite
 * that threw would take a whole page down over one bad row.
 */
function sprite_block(array $names): string
{
    static $symbols = null;

    if ($symbols === null) {
        $symbols = [];
        $svg = @file_get_contents(__DIR__ . '/../assets/icons/sprite.svg');
        if ($svg !== false) {
            preg_match_all('/<symbol id="([^"]+)".*?<\/symbol>/s', $svg, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                $symbols[$hit[1]] = $hit[0];
            }
        }
    }

    /* Sprite order, not caller order: the file is the canonical sequence, and
       a block that reordered itself with the content would churn every diff. */
    $body = '';
    foreach ($symbols as $id => $markup) {
        if (in_array($id, $names, true)) {
            $body .= '  ' . $markup . "\n";
        }
    }

    return "<!-- content-sprite:start -->\n"
        . '<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . "\n"
        . $body
        . "</svg>\n"
        . '<!-- content-sprite:end -->';
}
