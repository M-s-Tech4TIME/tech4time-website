<?php
/**
 * Tech4TIME — the header, footer and dock, emitted once instead of pasted
 * seventeen times.
 *
 * Named as lib/head.php is named, and for the same reason: that file emits the
 * shared part of <head>, this one emits the shared parts of <body>.
 *
 * WHY THIS FILE EXISTS
 * The header, footer and dock were literal markup in seventeen page files --
 * 74 + 136 + 191 template lines each, about 6,800 lines of duplication -- kept
 * in step by tools/propagate_shared.py and policed by
 * tools/check_shared_markup.py. Nothing in them could be changed without a
 * developer and a deploy: not a nav link, not the tagline, not a phone number,
 * not the copyright name.
 *
 * That arrangement had produced three live defects by the time it was
 * replaced. The footer's service list said "Human Resource Provision" where
 * content/services.json said something else. A service added in the editor
 * could never appear in the footer, because the footer could not read the
 * document. The Brussels phone numbers went stale for weeks, because
 * tools/sync_site_contact.py had to be run by hand before a deploy. That
 * script is deleted with this file's arrival, and so is the fingerprint it
 * stamped for the editor to compare against.
 *
 * A fourth was mechanical: propagate_shared.py re-marked every <a> whose href
 * a page already marked, so on index.php the LOGO LINK carried
 * aria-current="page" beside the Home nav link. Nobody designed that; it fell
 * out of a regex. It is gone -- see body_current().
 *
 * WHAT A PAGE PASSES
 *
 *     body_header('/pages/about/');
 *     body_footer();
 *     body_dock('/pages/about/');
 *
 * The same address it hands seo_head(), for the same reason: a service page's
 * address is a row's slug and is in no constant. It decides one thing --
 * which link is aria-current -- and the 404 passes '' and marks nothing.
 *
 * WHERE THE CONTENT COMES FROM. lib/chrome.php, which reads
 * content/chrome.json and resolves a stored row into an address and a name.
 * NOTHING HERE READS A DOCUMENT and nothing here decides what a link says;
 * this file writes tags, escapes every value that goes into one, and holds the
 * markup that is code rather than content -- the landmarks, the class names,
 * the dock's circuit, the CSS hooks the scripts bind to.
 *
 * body_header() ALSO EMITS THE CHROME'S ICON SPRITE, because it is the first
 * of the three to run and a <symbol> has to exist before the <use> that draws
 * it. tools/inject_icons.py reads page SOURCE, and the chrome is not in any
 * page's source any more. lib/sprite.php has the long version.
 */

declare(strict_types=1);

require_once __DIR__ . '/chrome.php';
require_once __DIR__ . '/settings.php';

/**
 * The header: skip link, brand, nav, theme toggle -- and the chrome's sprite.
 *
 * $route is the page's own address. It is passed on to the nav, which marks
 * at most one link with aria-current="page".
 */
function body_header(string $route): void
{
    $header = chrome_header();
    $out = [];

    $out[] = chrome_sprite();
    $out[] = '';
    $out[] = '<a class="skip-link" href="#main">Skip to main content</a>';
    $out[] = '';
    $out[] = '<header class="site-header" id="top">';
    $out[] = '  <div class="container site-header__inner">';
    $out[] = <<<'HTML'
    <!-- Two logo lockups, one per mode, toggled in CSS.
         A <picture media="(prefers-color-scheme: …)"> cannot be used here: that
         media query only ever reflects the OS setting, so it would ignore a
         visitor who picked the opposite mode with the header toggle. The hidden
         variant is lazy-loaded so browsers skip fetching it. -->
HTML;

    /* The brand goes home, and that is not editable. There is one home page,
       a logo that went anywhere else would be a surprise on every page of the
       site, and the label -- what a screen reader announces -- is the field
       worth having. */
    $out[] = '    <a class="site-header__brand" href="' . h(SEO_ROUTES['home'][0]) . '"'
           . ' aria-label="' . h($header['brand_label']) . '">';
    $out[] = body_header_logo($header['logo'], 'light');
    $out[] = body_header_logo($header['logo'], 'dark');
    $out[] = '    </a>';
    $out[] = '';
    $out[] = <<<'HTML'
    <!-- No data-nav-drawer here. This nav is the desktop navigation and
         nothing opens or closes it; below 64em it is hidden and the dock
         panel is the thing that opens. nav.js binds to the first
         [data-nav-drawer] in the document, so leaving the attribute on this
         element pointed the menu button at a display:none nav — it dutifully
         set data-open="true" on something nobody could see. -->
    <nav class="site-nav" id="site-nav" aria-label="Main">
      <ul class="site-nav__list">
HTML;

    foreach (chrome_rows_shown($header['nav']['items']) as $row) {
        $link = chrome_link($row);
        if ($link === null) {
            continue;
        }
        $out[] = '        <li class="site-nav__item"><a class="nav-link" href="'
               . h($link['href']) . '"' . body_current($route, $link['href']) . '>'
               . h($link['label']) . '</a></li>';
    }

    $out[] = <<<'HTML'
      </ul>
    </nav>

    <div class="site-header__actions">
      <button
        class="btn btn--icon"
        type="button"
        data-theme-toggle
        aria-label="Switch to dark mode"
        aria-pressed="false">
        <svg class="icon theme-toggle__icon--moon" aria-hidden="true" focusable="false"><use href="#moon"></use></svg>
        <svg class="icon theme-toggle__icon--sun" aria-hidden="true" focusable="false"><use href="#sun"></use></svg>
      </button>
    </div>
  </div>
</header>
HTML;

    echo implode("\n", $out), "\n";
}

/**
 * One of the header's two logo lockups.
 *
 * One attribute per line, which is how this markup has always been written and
 * is worth keeping: there are eleven of them, the srcset is long, and a diff
 * that changes one width should say so on one line.
 *
 * fetchpriority on the light one and loading="lazy" on the dark one are CODE,
 * not fields. The light lockup is the LCP candidate on every page; the hidden
 * variant should not be fetched until the visitor asks for the other theme.
 */
function body_header_logo(array $logo, string $mode): string
{
    /* THE PICTURE COMES FROM THE SETTINGS AND THE WORDS FROM THE CHROME. One
       mark is drawn in nine places; the sentence a screen reader announces
       THIS link as is the header's own, and is legitimately not the footer's.
       settings_logo() falls back to the light mark when no dark one was
       uploaded, so an empty dark half draws the light one rather than a hole
       in the header of every page. */
    $image = settings_logo(settings_load(), $mode);
    $out   = [];

    $out[] = '      <picture class="site-header__logo-wrap site-header__logo-wrap--' . $mode . '">';
    $out[] = '        <source';
    $out[] = '          srcset="' . h($image['webp_srcset'] !== ''
                                     ? $image['webp_srcset'] : $image['webp']) . '"';
    if (trim((string)$logo['sizes']) !== '') {
        $out[] = '          sizes="' . h($logo['sizes']) . '"';
    }
    $out[] = '          type="image/webp">';
    $out[] = '        <img';
    $out[] = '          class="site-header__logo"';
    $out[] = '          src="' . h($image['src']) . '"';
    if (trim((string)$image['srcset']) !== '') {
        $out[] = '          srcset="' . h($image['srcset']) . '"';
    }
    if (trim((string)$logo['sizes']) !== '') {
        $out[] = '          sizes="' . h($logo['sizes']) . '"';
    }
    $out[] = '          alt="' . h($logo['alt']) . '"';
    $out[] = '          width="' . (int)$image['width'] . '"';
    $out[] = '          height="' . (int)$image['height'] . '"';
    $out[] = $mode === 'light' ? '          fetchpriority="high"' : '          loading="lazy"';
    $out[] = '          decoding="async">';
    $out[] = '      </picture>';

    return implode("\n", $out);
}

/**
 * ` aria-current="page"`, or nothing at all.
 *
 * A separate function because it is the one per-page difference in the whole
 * of the chrome, and the thing the old propagation tool got wrong. Three
 * callers ask it: the header's nav, the dock's panel and the dock's bar --
 * the three places a page can be pointed at from. The BRAND does not ask it,
 * which is the fix: propagate_shared.py re-marked every <a> whose href a page
 * already marked, so index.php sent two current links, one of them a logo.
 *
 * The rule itself, prefix and all, is chrome_is_current() in lib/chrome.php.
 */
function body_current(string $route, string $href): string
{
    return chrome_is_current($route, $href) ? ' aria-current="page"' : '';
}

/**
 * The footer: brand, four columns, legal, copyright, back to top.
 *
 * No $route. The footer marks nothing as current and never did -- it is a
 * directory of the site rather than a statement about where the visitor is.
 */
function body_footer(): void
{
    $footer = chrome_footer();
    $out = [];

    $out[] = '<footer class="site-footer">';
    $out[] = '  <div class="container">';
    $out[] = '    <div class="site-footer__main">';
    $out[] = '      <!-- Brand -->';
    $out[] = '      <div class="site-footer__brand">';
    $out[] = '        <a href="' . h(SEO_ROUTES['home'][0]) . '"'
           . ' aria-label="' . h($footer['brand_label']) . '">';
    $out[] = body_footer_logo($footer['logo'], 'light');
    $out[] = body_footer_logo($footer['logo'], 'dark');
    $out[] = '        </a>';
    $out[] = '        <p class="site-footer__tagline">' . h($footer['tagline']) . '</p>';
    $out[] = '        <p class="site-footer__description">';
    $out[] = '          ' . h($footer['description']);
    $out[] = '        </p>';
    $out[] = '        <ul class="site-footer__social">';

    /* Derived from the SEO document's sameAs rows. There is no second copy to
       keep in step, which is the whole reason they are not stored here. */
    foreach (chrome_social() as $link) {
        $out[] = '          <li>';
        $out[] = '            <a class="btn btn--icon" href="' . h($link['url']) . '"';
        $out[] = '               target="_blank" rel="noopener noreferrer"'
               . ' aria-label="' . h(seo_site()['name'] . ' on ' . $link['label']) . '">';
        $out[] = '              <svg class="icon" aria-hidden="true" focusable="false"><use href="#'
               . h($link['icon']) . '"></use></svg>';
        $out[] = '            </a>';
        $out[] = '          </li>';
    }

    $out[] = '        </ul>';
    $out[] = '      </div>';
    $out[] = '';
    $out[] = '      <!-- Quick links -->';
    $out[] = '      <nav class="site-footer__section" aria-labelledby="footer-links-heading">';
    $out[] = '        <h2 class="site-footer__heading" id="footer-links-heading">'
           . h($footer['links']['heading']) . '</h2>';
    $out[] = '        <ul class="site-footer__list">';

    foreach (chrome_rows_shown($footer['links']['items']) as $row) {
        $link = chrome_link($row);
        if ($link !== null) {
            $out[] = body_footer_link($link['href'], $link['label']);
        }
    }

    $out[] = '        </ul>';
    $out[] = '      </nav>';
    $out[] = '';
    $out[] = '      <!-- Services -->';
    $out[] = '      <nav class="site-footer__section" aria-labelledby="footer-services-heading">';
    $out[] = '        <h2 class="site-footer__heading" id="footer-services-heading">'
           . h($footer['services']['heading']) . '</h2>';
    $out[] = '        <ul class="site-footer__list">';

    /* The index first, under a label of its own -- "All Services" introduces
       the list below it rather than naming a page -- then content/services.json
       itself. A seventh service appears here by itself; a hidden one goes. */
    $out[] = body_footer_link(SEO_ROUTES['services'][0], $footer['services']['index_label']);
    foreach (chrome_services() as $service) {
        $out[] = body_footer_link($service['href'], $service['label']);
    }

    $out[] = '        </ul>';
    $out[] = '      </nav>';
    $out[] = '';
    $out[] = '      <!-- Contact -->';
    $out[] = '      <div class="site-footer__section">';
    $out[] = '        <h2 class="site-footer__heading">' . h($footer['contact']['heading']) . '</h2>';
    $out[] = '        <address class="site-footer__contact">';

    foreach (chrome_contact_groups() as $i => $group) {
        if ($i > 0) {
            $out[] = '';
        }
        $out[] = body_contact_item($group);
    }

    $out[] = '        </address>';
    $out[] = '      </div>';
    $out[] = '    </div>';
    $out[] = '';
    $out[] = '    <div class="site-footer__bottom">';
    $out[] = '      <div>';
    $out[] = '        <p class="site-footer__copyright">';
    $out[] = '          &copy; <span data-current-year>' . date('Y') . '</span> '
           . body_copyright($footer['copyright']);
    $out[] = '        </p>';
    $out[] = '        <ul class="site-footer__legal">';

    foreach (chrome_rows_shown($footer['legal']['items']) as $row) {
        $link = chrome_link($row);
        if ($link !== null) {
            $out[] = body_footer_link($link['href'], $link['label']);
        }
    }

    $out[] = <<<'HTML'
        </ul>
      </div>

      <button class="btn btn--icon" type="button" data-back-to-top aria-label="Back to top">
        <svg class="icon" aria-hidden="true" focusable="false"><use href="#arrow-up"></use></svg>
      </button>
    </div>
  </div>
</footer>
HTML;

    echo implode("\n", $out), "\n";
}

/** One row of a footer list. The legal list uses the same link class. */
function body_footer_link(string $href, string $label): string
{
    return '          <li><a class="site-footer__link" href="' . h($href) . '">'
         . h($label) . '</a></li>';
}

/**
 * One of the footer's two logo lockups.
 *
 * Deliberately not body_header_logo(): the footer draws ONE width, lazily,
 * with no srcset and no sizes, and writing that as eleven lines of one
 * attribute each would be eleven lines saying nothing. Two shapes, because
 * there are two jobs.
 */
function body_footer_logo(array $logo, string $mode): string
{
    /* The same mark as the header's, from the same document, drawn small and
       lazily -- and announced in the footer's own words. */
    $image = settings_logo(settings_load(), $mode);
    $out   = [];

    $out[] = '          <picture class="site-footer__logo-wrap site-footer__logo-wrap--' . $mode . '">';
    $out[] = '            <source srcset="' . h($image['webp']) . '" type="image/webp">';
    $out[] = '            <img class="site-footer__logo" src="' . h($image['src']) . '"';
    $out[] = '                 alt="' . h($logo['alt']) . '" width="' . (int)$image['width']
           . '" height="' . (int)$image['height'] . '" loading="lazy" decoding="async">';
    $out[] = '          </picture>';

    return implode("\n", $out);
}

/**
 * One .contact-item: an icon, and the rows that share its kind beneath it.
 *
 * THE SEPARATOR RULE IS ONE RULE, which it was not before. A row's own lines
 * are separated by <br>; rows are separated by nothing, because
 * .contact-item__label is display:block and carries margin-block-start for
 * exactly this -- layout.css says so, beside the rule. The old markup put a
 * <br> between address rows and none between hours rows, an artefact of the
 * old sync script having been written a section at a time; the alternative to
 * picking one was carrying a per-kind separator flag in the contract for ever.
 *
 * A GROUP OF ONE BARE VALUE GETS NO WRAPPER. The <div> exists to hold several
 * lines together as ONE flex child of .contact-item; a single unlabelled value
 * with no note has nothing to hold, and the email row has never had one.
 */
function body_contact_item(array $group): string
{
    $out = [];
    $out[] = '          <div class="contact-item">';
    $out[] = '            <svg class="icon contact-item__icon" aria-hidden="true" focusable="false"><use href="#'
           . h($group['icon']) . '"></use></svg>';

    $bare = count($group['rows']) === 1
        && trim((string)$group['rows'][0]['label']) === ''
        && trim((string)$group['rows'][0]['note']) === ''
        && count($group['rows'][0]['lines']) === 1;

    $pad = $bare ? '            ' : '              ';
    if (!$bare) {
        $out[] = '            <div>';
    }

    foreach ($group['rows'] as $i => $row) {
        if ($i > 0) {
            $out[] = '';
        }
        if (trim((string)$row['label']) !== '') {
            $out[] = $pad . '<span class="contact-item__label">' . h($row['label']) . '</span>';
        }

        $lines = [];
        foreach ($row['lines'] as $line) {
            $href    = chrome_contact_href($group['kind'], $line);
            $lines[] = $href === ''
                ? h($line)
                : '<a href="' . h($href) . '">' . h($line) . '</a>';
        }
        /* <br> between a row's own lines: three telephone numbers under one
           label are three lines, not one sentence. */
        if ($lines) {
            $out[] = $pad . implode("<br>\n" . $pad, $lines);
        }

        if (trim((string)$row['note']) !== '') {
            $out[] = $pad . '<span class="contact-item__note">' . h($row['note']) . '</span>';
        }
    }

    if (!$bare) {
        $out[] = '            </div>';
    }
    $out[] = '          </div>';

    return implode("\n", $out);
}

/**
 * "Tech4TIME. All rights reserved." -- or just the name, when there is no
 * rights sentence to end.
 *
 * The full stop is markup rather than content: it ends the first sentence, and
 * a name field that had to be typed with a trailing dot would be a trap.
 */
function body_copyright(array $copyright): string
{
    $name   = trim((string)$copyright['name']);
    $rights = trim((string)$copyright['rights']);

    if ($name === '') {
        return h($rights);
    }

    return $rights === '' ? h($name) : h($name) . '. ' . h($rights);
}

/**
 * The dock: the small-screen navigation, panel and bar.
 *
 * $route marks at most one panel item and at most one bar key, by the same
 * rule the nav uses. It is a sibling of <header> and <footer> and NOT inside
 * the header -- see the comment it emits, which explains why in terms of
 * backdrop-filter and containing blocks.
 *
 * The circuit is literal markup here rather than a field of anything. It is
 * decoration with nothing to say: no text, aria-hidden on both halves, and
 * a hundred path coordinates nobody should be offered a form for.
 */
function body_dock(string $route): void
{
    $dock = chrome_dock();
    $out  = [];

    $out[] = <<<'HTML'
<!-- The small-screen navigation: a floating bar within thumb reach, and a card
     of sections that rises above it. It replaces the header hamburger below
     64em, where the header nav is hidden.

     It sits here, a sibling of <header> and <footer>, and NOT inside the
     header. That is deliberate: .site-header paints a backdrop-filter, and an
     element with one becomes the containing block for its position:fixed
     descendants — which is what clamped the old drawer to the header's own
     box. Keeping this outside the header keeps its containing block the
     viewport. See the note in layout.css.

     The four destinations in the bar are real links, so they still work with
     JavaScript disabled. Only the panel needs script, and the footer carries
     the same links again for that case. -->
<div class="dock" data-dock>
  <div class="dock__panel" id="dock-panel" data-nav-drawer data-open="false">

    <!-- Circuit traces down both edges of the card, drawn rather than
         photographed: about a kilobyte instead of a hundred, it tints itself
         from the theme tokens, and it needs no art direction when the palette
         changes.

         aria-hidden on both: they say nothing, they are texture beside the
         list. The animation is described where it is written, in
         components.css.

         The path data is declared once, in the left column's <defs>. SVG ids
         are document-scoped, so the right column references the same paths and
         is flipped in CSS — one set of geometry, two sides. The two run on
         different durations so the mirror image never moves in lockstep with
         its twin. -->
    <div class="dock__circuit dock__circuit--left" aria-hidden="true">
      <svg viewBox="0 0 80 320" preserveAspectRatio="xMidYMid slice" focusable="false">
        <defs>
          <path id="dock-t-a" d="M10 0v72h34v56h28"/>
          <path id="dock-t-b" d="M10 320v-72h28v-52"/>
          <path id="dock-t-c" d="M34 0v44h32v60"/>
          <path id="dock-t-d" d="M10 128h20v48h32v56"/>
          <path id="dock-t-e" d="M72 320v-44H26v-62"/>
          <path id="dock-t-f" d="M66 0v32H10v64"/>
          <path id="dock-t-g" d="M46 320v-28H10"/>
          <path id="dock-t-h" d="M72 152H50V88"/>
        </defs>

        <g class="dock__wires" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <use href="#dock-t-a"/><use href="#dock-t-b"/>
          <use href="#dock-t-c"/><use href="#dock-t-d"/>
          <use href="#dock-t-e"/><use href="#dock-t-f"/>
          <use href="#dock-t-g"/><use href="#dock-t-h"/>
        </g>

        <g class="dock__charges" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <use class="dock__charge dock__charge--a" href="#dock-t-a"/>
          <use class="dock__charge dock__charge--b" href="#dock-t-b"/>
          <use class="dock__charge dock__charge--c" href="#dock-t-c"/>
          <use class="dock__charge dock__charge--d" href="#dock-t-d"/>
          <use class="dock__charge dock__charge--e" href="#dock-t-e"/>
          <use class="dock__charge dock__charge--f" href="#dock-t-f"/>
          <use class="dock__charge dock__charge--g" href="#dock-t-g"/>
          <use class="dock__charge dock__charge--h" href="#dock-t-h"/>
        </g>

        <g class="dock__nodes">
          <circle class="dock__node dock__node--a" cx="44" cy="72" r="3.5"/>
          <circle class="dock__node dock__node--b" cx="44" cy="128" r="3"/>
          <circle class="dock__node dock__node--c" cx="66" cy="104" r="3.5"/>
          <circle class="dock__node dock__node--d" cx="30" cy="176" r="3"/>
          <circle class="dock__node dock__node--e" cx="62" cy="232" r="3.5"/>
          <circle class="dock__node dock__node--f" cx="26" cy="214" r="3"/>
          <circle class="dock__node dock__node--g" cx="10" cy="96" r="3.5"/>
          <circle class="dock__node dock__node--h" cx="50" cy="88" r="3"/>
          <circle class="dock__node dock__node--i" cx="38" cy="248" r="3"/>
          <circle class="dock__node dock__node--j" cx="10" cy="292" r="3.5"/>
        </g>
      </svg>
    </div>

    <div class="dock__circuit dock__circuit--right" aria-hidden="true">
      <svg viewBox="0 0 80 320" preserveAspectRatio="xMidYMid slice" focusable="false">
        <g class="dock__wires" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <use href="#dock-t-a"/><use href="#dock-t-b"/>
          <use href="#dock-t-c"/><use href="#dock-t-d"/>
          <use href="#dock-t-e"/><use href="#dock-t-f"/>
          <use href="#dock-t-g"/><use href="#dock-t-h"/>
        </g>

        <g class="dock__charges" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <use class="dock__charge dock__charge--k" href="#dock-t-a"/>
          <use class="dock__charge dock__charge--l" href="#dock-t-b"/>
          <use class="dock__charge dock__charge--m" href="#dock-t-c"/>
          <use class="dock__charge dock__charge--n" href="#dock-t-d"/>
          <use class="dock__charge dock__charge--o" href="#dock-t-e"/>
          <use class="dock__charge dock__charge--p" href="#dock-t-f"/>
          <use class="dock__charge dock__charge--q" href="#dock-t-g"/>
          <use class="dock__charge dock__charge--r" href="#dock-t-h"/>
        </g>

        <g class="dock__nodes">
          <circle class="dock__node dock__node--k" cx="44" cy="72" r="3.5"/>
          <circle class="dock__node dock__node--l" cx="44" cy="128" r="3"/>
          <circle class="dock__node dock__node--m" cx="66" cy="104" r="3.5"/>
          <circle class="dock__node dock__node--n" cx="30" cy="176" r="3"/>
          <circle class="dock__node dock__node--o" cx="62" cy="232" r="3.5"/>
          <circle class="dock__node dock__node--p" cx="26" cy="214" r="3"/>
          <circle class="dock__node dock__node--q" cx="10" cy="96" r="3.5"/>
          <circle class="dock__node dock__node--r" cx="50" cy="88" r="3"/>
        </g>
      </svg>
    </div>

    <nav class="dock__nav" aria-label="Site sections">
      <ul class="dock__list">
HTML;

    foreach (chrome_rows_shown($dock['panel']['items']) as $row) {
        $link = chrome_link($row);
        if ($link === null) {
            continue;
        }
        $out[] = '        <li>';
        $out[] = '          <a class="dock__item" href="' . h($link['href']) . '"'
               . body_current($route, $link['href']) . '>';
        $out[] = '            <span class="dock__item-title">' . h($link['label']) . '</span>';
        $out[] = '            <span class="dock__item-desc">' . h($row['description']) . '</span>';
        $out[] = '          </a>';
        $out[] = '        </li>';
    }

    $out[] = '      </ul>';
    $out[] = '    </nav>';
    $out[] = '  </div>';
    $out[] = '';
    $out[] = '  <nav class="dock__bar" aria-label="Quick navigation">';

    foreach ($dock['bar']['items'] as $row) {
        $link = chrome_link($row);
        if ($link === null) {
            continue;
        }
        foreach (body_dock_key($route, $row, $link) as $line) {
            $out[] = $line;
        }
    }

    $out[] = '    <button';
    $out[] = '      class="dock__key dock__key--menu"';
    $out[] = '      type="button"';
    $out[] = '      data-nav-toggle';
    $out[] = '      aria-controls="dock-panel"';
    $out[] = '      aria-expanded="false">';
    $out[] = '      <svg class="icon dock__icon--open" aria-hidden="true" focusable="false"><use href="#grid-dots"></use></svg>';
    $out[] = '      <svg class="icon dock__icon--close" aria-hidden="true" focusable="false"><use href="#times"></use></svg>';
    $out[] = '      <span class="dock__key-label">' . h($dock['menu_label']) . '</span>';
    $out[] = '    </button>';
    $out[] = '  </nav>';
    $out[] = '</div>';

    echo implode("\n", $out), "\n";
}

/**
 * One key of the dock bar, as lines.
 *
 * A bar row is a LINK and not a button even when it is the emphasised one: it
 * goes to a page, and a <button> would be a lie to anything reading the markup
 * rather than looking at it. That is what the comment below says on the page,
 * and it is emitted beside the key it is about rather than once at the top,
 * because which key is emphasised is a field now.
 *
 * The emphasis class is .dock__key--contact rather than .dock__key--disc, and
 * that is deliberate rather than an oversight. components.css and
 * tools/test_nav.py both name it; renaming a class costs a cache bust on
 * components.css for every visitor who has been here before, to change nothing
 * anybody can see. The map is one line here instead.
 */
function body_dock_key(string $route, array $row, array $link): array
{
    $emphasis = ($row['emphasis'] ?? 'plain') === 'disc' ? 'disc' : 'plain';
    $out      = [];

    if ($emphasis === 'disc') {
        $out[] = '';
        $out[] = <<<'HTML'
    <!-- The one emphasised destination. It is a link like its neighbours, not
         a button: it goes to a page, and a <button> would be a lie to anything
         reading the markup rather than looking at it. The emphasis is the
         filled disc. -->
HTML;
    }

    $class = 'dock__key' . ($emphasis === 'disc' ? ' dock__key--contact' : '');
    $icon  = '<svg class="icon" aria-hidden="true" focusable="false"><use href="#'
           . h($row['icon']) . '"></use></svg>';

    $out[] = '    <a class="' . $class . '" href="' . h($link['href']) . '"'
           . body_current($route, $link['href']) . '>';

    if ($emphasis === 'disc') {
        $out[] = '      <span class="dock__key-disc">';
        $out[] = '        ' . $icon;
        $out[] = '      </span>';
    } else {
        $out[] = '      ' . $icon;
    }

    $out[] = '      <span class="dock__key-label">' . h($link['label']) . '</span>';
    $out[] = '    </a>';

    if ($emphasis === 'disc') {
        $out[] = '';
    }

    return $out;
}
