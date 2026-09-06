<?php
/**
 * Tech4TIME — robots.txt.
 *
 * Served at /robots.txt, which is the only address a crawler will ever ask
 * for; .htaccess rewrites that URL here internally, so the address does not
 * change and nothing has to be relearned.
 *
 * WHAT IS EDITABLE AND WHAT IS NOT, AND WHY THE LINE IS DRAWN HERE.
 * "User-agent: *", "Allow: /" and the Sitemap line are written below and
 * cannot be removed from the editor. Everything else -- the extra Disallow
 * paths -- comes from content/seo.json and is edited at
 * admin.tech4time.bd/?s=seo&site=crawl.
 *
 * That is not timidity about giving somebody control. A robots.txt that
 * disallows "/" removes the entire site from every search engine, and it does
 * it silently: nothing breaks, no page 404s, traffic simply stops arriving and
 * the way back is a re-crawl on Google's schedule rather than ours. The three
 * lines that make the site crawlable at all are therefore code, and a rule
 * that would block the whole site is refused by seo_validate() before it can
 * be saved. Any narrower rule is allowed, behind a confirmation that says what
 * it does.
 *
 * IT MUST NOT BE ABLE TO FAIL. seo_defaults() carries the real rule, so a
 * missing content/seo.json still produces the file this site has always
 * served.
 */

declare(strict_types=1);

require __DIR__ . '/lib/seo.php';

$crawl = seo_load()['crawl'];

/* text/plain, and said out loud: a robots.txt served as text/html is ignored
   by some crawlers, and this site sends X-Content-Type-Options: nosniff, so
   nothing downstream will guess a better one. */
header('Content-Type: text/plain; charset=UTF-8');

/* Newlines only, never a stored string with one in it: every line below is
   either a literal or a path that seo_link_defaults()-style normalising has
   already reduced to a single line. A Disallow carrying a newline would let
   an editor write a directive of their own underneath it. */
$rules = [];
foreach ($crawl['robots_extra'] as $path) {
    $path = trim(preg_replace('/\s+/', '', (string)$path) ?? '');
    if ($path !== '') {
        $rules[] = 'Disallow: ' . $path;
    }
}

echo "# Tech4TIME — ", SEO_ORIGIN, "\n";
echo "# All major crawlers are welcome across the whole site.\n";
echo "\n";
echo "User-agent: *\n";
echo "Allow: /\n";

if ($rules !== []) {
    echo "\n";
    /* No mention of where these are edited. This file is the first thing
       anyone scanning the site fetches, and the note below about /admin
       explains at length why it names no path it does not have to. */
    echo "# Paths that have nothing to index.\n";
    echo implode("\n", $rules), "\n";
}
?>

# NOTE: /admin is deliberately NOT listed here.
#
# robots.txt is world-readable and is the first file anyone scanning a site
# fetches, so a Disallow line advertises the path it is meant to protect. It
# also makes matters worse rather than better: a disallowed page is never
# crawled, so the noindex on it is never read, and a URL discovered any other
# way can still surface as a bare result Google refuses to describe.
#
# What actually keeps the editor out of search is that Apache answers it with
# 401 (cPanel Directory Privacy), nothing on the site links to it, it is absent
# from the sitemap, and it carries noindex both as a header and in its markup.

Sitemap: <?= SEO_ORIGIN ?>/sitemap.xml
