<?php
/**
 * Tech4TIME — the sitemap.
 *
 * Served at /sitemap.xml, which is the address robots.txt names and the one
 * submitted to Search Console; .htaccess section 3 rewrites that URL here. The
 * address did not change when this stopped being a static file, and it must
 * not: a sitemap is a URL a crawler remembers.
 *
 * WHAT IS IN IT IS NOT DECIDED HERE ANY MORE. It was a table of ten routes
 * with their changefreq and priority typed in beside them, plus the services
 * read from their document. Every one of those values is a field on the page
 * it describes now -- content/<page>.json, meta band -- and is edited at
 * admin.tech4time.bd/?s=seo along with the page's title. seo_sitemap_entries()
 * assembles them; adding a page is still a line in SEO_ROUTES, and nothing
 * else.
 *
 * MEMBERSHIP IS DERIVED FROM robots AND NOTHING ELSE. A page set to noindex is
 * absent from this file, and a page in this file is indexable, because they
 * are the same switch. Two controls could be set to contradict each other, and
 * a noindex URL in a sitemap is a warning raised against the whole file.
 *
 * LASTMOD IS READ FROM THE DOCUMENT, NOT WRITTEN BY HAND. Every page renders
 * from content/ and changes without a deploy, so a date typed into a file was
 * wrong the moment the editor was next used. api/publish.php sets `updated`
 * when the content arrives, and that is what is reported.
 *
 * IT MUST NOT BE ABLE TO FAIL. A crawler asking for the sitemap gets a
 * sitemap. A document that is missing or unreadable contributes its defaults
 * rather than throwing, so the worst case is a stale date on one line and not
 * a 500 on the file that tells search engines the site exists.
 */

declare(strict_types=1);

require __DIR__ . '/lib/seo.php';

$entries = seo_sitemap_entries();

/* Not text/html: a crawler is entitled to refuse a sitemap that arrives as one,
   and the site sends X-Content-Type-Options: nosniff, so nothing will guess. */
header('Content-Type: application/xml; charset=UTF-8');

/**
 * XML, not HTML. Escapes the five characters that matter in an XML text node.
 */
function x(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/* BOTH of these are echoed, and neither may be written as literal output.
   With short_open_tag=On -- which is off by default in php-cli and ON on this
   host, as it is on most cPanel installs -- PHP reads the "<?" of "<?xml" as
   an open tag and tries to run what follows as code. The file then fails to
   COMPILE, so nothing runs, the response is a 500 with an empty body, and no
   amount of reading the code explains it because the code is fine.

   The declaration was echoed for this reason from the start; the stylesheet
   line was not, and it took the sitemap down on the first deploy.
   build_deploy_set.py --check now parses every shipped .php file with
   short_open_tag=On, so this cannot reach the host again. */
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<?xml-stylesheet type="text/xsl" href="/assets/xsl/sitemap.xsl"?>', "\n";
?>
<!--
  Tech4TIME sitemap.

  GENERATED ON REQUEST by sitemap.php, which is the file to change. Editing
  what you are reading changes nothing: it is written out afresh every time
  this URL is asked for.

  Which pages appear, how often each says it changes and what priority it
  claims are fields on the pages themselves, set in the editor. A page marked
  "not indexed" there is absent from this file. The service pages are read
  from content/services.json, because a service added in the editor has no
  file here to notice.

  This file names no editor address, deliberately, and for the same reason
  robots.txt names no editor path: both are world-readable and both are among
  the first things anything scanning a site fetches. tools/audit_pages.py
  fails the build if the word appears anywhere in what this renders — the
  comments included, which is how this paragraph came to be worded around it.

  The xml-stylesheet line above is what a BROWSER uses to render this as a
  readable table — assets/xsl/sitemap.xsl. Crawlers ignore it and read the
  <urlset> below, so it changes nothing about how the site is indexed.
-->
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($entries as [$path, $lastmod, $changefreq, $priority]): ?>

  <url>
    <loc><?= x(SEO_ORIGIN . $path) ?></loc>
<?php if ($lastmod !== ''): ?>
    <lastmod><?= x($lastmod) ?></lastmod>
<?php endif; ?>
    <changefreq><?= x($changefreq) ?></changefreq>
    <priority><?= x($priority) ?></priority>
  </url>
<?php endforeach; ?>

</urlset>
