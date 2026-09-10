<?php
/**
 * Tech4TIME — contact page.
 *
 * Renders from a document, as every page here does. It was among the first to,
 * for the reason that later applied to all of them: what it says changes
 * without a redeploy. Addresses, phone numbers, opening hours and the copy
 * around them live in content/contact.json and are edited at
 * admin.tech4time.bd; this renders them.
 *
 * Rendered on the SERVER, not fetched in the browser. A contact page whose
 * addresses arrive by JavaScript is one a search engine indexes unreliably,
 * and it is the page a search engine is most often asked for by name.
 *
 * If the data file is missing or unreadable, contact_load() falls back field by
 * field to the details the site was deployed with — stale at worst, never
 * blank.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/head.php';
require_once __DIR__ . '/../../lib/body.php';
/* require_once, not require: lib/head.php pulls lib/contact.php in for the
   Organization graph's addresses and telephone numbers, so by the time this
   line is reached the file is already loaded and a plain require would
   redeclare every function in it. */
require_once __DIR__ . '/../../lib/contact.php';

$data    = contact_load();
$offices = contact_shown_offices($data);
$reach   = contact_shown_reach($data);
?>
<!DOCTYPE html>
<html lang="<?= h(seo_lang()) ?>">
<head>
<?php seo_head('/pages/contact/', $data['meta'], ['pages/contact.css'], $data['updated']); ?>
<?php seo_jsonld('/pages/contact/', $data['meta'], $data['updated']); ?>

<?php /* The ContactPage graph, built from the same records the page below
         renders. Generated rather than written out so it cannot drift from
         what a visitor is being shown — a wrong address in structured data is
         wrong in Google's knowledge panel, where nobody on this end sees it. */ ?>
<script type="application/ld+json">
<?= json_encode(contact_page_schema($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>
</head>

<body class="page">
<!-- icon-sprite:start -->
<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <symbol id="building" viewBox="0 0 384 512"><path d="M48 0C21.5 0 0 21.5 0 48V464c0 26.5 21.5 48 48 48h96V432c0-26.5 21.5-48 48-48s48 21.5 48 48v80h96c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48H48zM64 240c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V240zm112-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V240c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V240zM80 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V112zM272 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16z"/></symbol>
  <symbol id="calendar-alt" viewBox="0 0 448 512"><path d="M128 0c17.7 0 32 14.3 32 32V64H288V32c0-17.7 14.3-32 32-32s32 14.3 32 32V64h48c26.5 0 48 21.5 48 48v48H0V112C0 85.5 21.5 64 48 64H96V32c0-17.7 14.3-32 32-32zM0 192H448V464c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V192zm64 80v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H80c-8.8 0-16 7.2-16 16zm128 0v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H208c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H336zM64 400v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H80c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H208zm112 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H336c-8.8 0-16 7.2-16 16z"/></symbol>
  <symbol id="clock" viewBox="0 0 512 512"><path d="M256 0a256 256 0 1 1 0 512A256 256 0 1 1 256 0zM232 120V256c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2V120c0-13.3-10.7-24-24-24s-24 10.7-24 24z"/></symbol>
  <symbol id="comment-alt" viewBox="0 0 512 512"><path d="M64 0C28.7 0 0 28.7 0 64V352c0 35.3 28.7 64 64 64h96v80c0 6.1 3.4 11.6 8.8 14.3s11.9 2.1 16.8-1.5L309.3 416H448c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H64z"/></symbol>
  <symbol id="envelope" viewBox="0 0 512 512"><path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/></symbol>
  <symbol id="github" viewBox="0 0 496 512"><path d="M165.9 397.4c0 2-2.3 3.6-5.2 3.6-3.3.3-5.6-1.3-5.6-3.6 0-2 2.3-3.6 5.2-3.6 3-.3 5.6 1.3 5.6 3.6zm-31.1-4.5c-.7 2 1.3 4.3 4.3 4.9 2.6 1 5.6 0 6.2-2s-1.3-4.3-4.3-5.2c-2.6-.7-5.5.3-6.2 2.3zm44.2-1.7c-2.9.7-4.9 2.6-4.6 4.9.3 2 2.9 3.3 5.9 2.6 2.9-.7 4.9-2.6 4.6-4.6-.3-1.9-3-3.2-5.9-2.9zM244.8 8C106.1 8 0 113.3 0 252c0 110.9 69.8 205.8 169.5 239.2 12.8 2.3 17.3-5.6 17.3-12.1 0-6.2-.3-40.4-.3-61.4 0 0-70 15-84.7-29.8 0 0-11.4-29.1-27.8-36.6 0 0-22.9-15.7 1.6-15.4 0 0 24.9 2 38.6 25.8 21.9 38.6 58.6 27.5 72.9 20.9 2.3-16 8.8-27.1 16-33.7-55.9-6.2-112.3-14.3-112.3-110.5 0-27.5 7.6-41.3 23.6-58.9-2.6-6.5-11.1-33.3 2.6-67.9 20.9-6.5 69 27 69 27 20-5.6 41.5-8.5 62.8-8.5s42.8 2.9 62.8 8.5c0 0 48.1-33.6 69-27 13.7 34.7 5.2 61.4 2.6 67.9 16 17.7 25.8 31.5 25.8 58.9 0 96.5-58.9 104.2-114.8 110.5 9.2 7.9 17 22.9 17 46.4 0 33.7-.3 75.4-.3 83.6 0 6.5 4.6 14.4 17.3 12.1C428.2 457.8 496 362.9 496 252 496 113.3 383.5 8 244.8 8zM97.2 352.9c-1.3 1-1 3.3.7 5.2 1.6 1.6 3.9 2.3 5.2 1 1.3-1 1-3.3-.7-5.2-1.6-1.6-3.9-2.3-5.2-1zm-10.8-8.1c-.7 1.3.3 2.9 2.3 3.9 1.6 1 3.6.7 4.3-.7.7-1.3-.3-2.9-2.3-3.9-2-.6-3.6-.3-4.3.7zm32.4 35.6c-1.6 1.3-1 4.3 1.3 6.2 2.3 2.3 5.2 2.6 6.5 1 1.3-1.3.7-4.3-1.3-6.2-2.2-2.3-5.2-2.6-6.5-1zm-11.4-14.7c-1.6 1-1.6 3.6 0 5.9 1.6 2.3 4.3 3.3 5.6 2.3 1.6-1.3 1.6-3.9 0-6.2-1.4-2.3-4-3.3-5.6-2z"/></symbol>
  <symbol id="globe" viewBox="0 0 512 512"><path d="M352 256c0 22.2-1.2 43.6-3.3 64H163.3c-2.2-20.4-3.3-41.8-3.3-64s1.2-43.6 3.3-64H348.7c2.2 20.4 3.3 41.8 3.3 64zm28.8-64H503.9c5.3 20.5 8.1 41.9 8.1 64s-2.8 43.5-8.1 64H380.8c2.1-20.6 3.2-42 3.2-64s-1.1-43.4-3.2-64zm112.6-32H376.7c-10-63.9-29.8-117.4-55.3-151.6c78.3 20.7 142 77.5 171.9 151.6zm-149.1 0H167.7c6.1-36.4 15.5-68.6 27-94.7c10.5-23.6 22.2-40.7 33.5-51.5C239.4 3.2 248.7 0 256 0s16.6 3.2 27.8 13.8c11.3 10.8 23 27.9 33.5 51.5c11.6 26 20.9 58.2 27 94.7zm-209 0H18.6C48.6 85.9 112.2 29.1 190.6 8.4C165.1 42.6 145.3 96.1 135.3 160zM8.1 192H131.2c-2.1 20.6-3.2 42-3.2 64s1.1 43.4 3.2 64H8.1C2.8 299.5 0 278.1 0 256s2.8-43.5 8.1-64zM194.7 446.6c-11.6-26-20.9-58.2-27-94.6H344.3c-6.1 36.4-15.5 68.6-27 94.6c-10.5 23.6-22.2 40.7-33.5 51.5C272.6 508.8 263.3 512 256 512s-16.6-3.2-27.8-13.8c-11.3-10.8-23-27.9-33.5-51.5zM135.3 352c10 63.9 29.8 117.4 55.3 151.6C112.2 482.9 48.6 426.1 18.6 352H135.3zm358.1 0c-30 74.1-93.6 130.9-171.9 151.6c25.5-34.2 45.2-87.7 55.3-151.6H493.4z"/></symbol>
  <symbol id="headset" viewBox="0 0 512 512"><path d="M256 48C141.1 48 48 141.1 48 256v40c0 13.3-10.7 24-24 24s-24-10.7-24-24V256C0 114.6 114.6 0 256 0S512 114.6 512 256V400.1c0 48.6-39.4 88-88.1 88L313.6 488c-8.3 14.3-23.8 24-41.6 24H240c-26.5 0-48-21.5-48-48s21.5-48 48-48h32c17.8 0 33.3 9.7 41.6 24l110.4 .1c22.1 0 40-17.9 40-40V256c0-114.9-93.1-208-208-208zM144 208h16c17.7 0 32 14.3 32 32V352c0 17.7-14.3 32-32 32H144c-35.3 0-64-28.7-64-64V272c0-35.3 28.7-64 64-64zm224 0c35.3 0 64 28.7 64 64v48c0 35.3-28.7 64-64 64H352c-17.7 0-32-14.3-32-32V240c0-17.7 14.3-32 32-32h16z"/></symbol>
  <symbol id="info-circle" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"/></symbol>
  <symbol id="linkedin" viewBox="0 0 448 512"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"/></symbol>
  <symbol id="lock" viewBox="0 0 448 512"><path d="M144 144v48H304V144c0-44.2-35.8-80-80-80s-80 35.8-80 80zM80 192V144C80 64.5 144.5 0 224 0s144 64.5 144 144v48h16c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V256c0-35.3 28.7-64 64-64H80z"/></symbol>
  <symbol id="map-marker-alt" viewBox="0 0 384 512"><path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></symbol>
  <symbol id="mobile-alt" viewBox="0 0 384 512"><path d="M16 64C16 28.7 44.7 0 80 0H304c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H80c-35.3 0-64-28.7-64-64V64zM224 448a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zM304 64H80V384H304V64z"/></symbol>
  <symbol id="paper-plane" viewBox="0 0 512 512"><path d="M498.1 5.6c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.8 4.2-10.7L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z"/></symbol>
  <symbol id="phone" viewBox="0 0 512 512"><path d="M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 311.4 200.6 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L193.3 167c13.7-11.2 18.4-30 11.6-46.3l-40-96z"/></symbol>
</svg>
<!-- icon-sprite:end -->

<?php body_header('/pages/contact/'); ?>

<main class="page__main" id="main">

  <!-- ========================== Hero banner ========================== -->
  <section class="page-hero">
    <!--hero-circuit:start-->
    <!-- The circuitry around the page title, in the same language as the
         company's own printed material: clusters emerging from all four
         corners, and a chevron band running the full width of the top and the
         bottom edge. Drawn rather than photographed, so it tints itself from
         the theme tokens and costs the Largest Contentful Paint nothing.

         aria-hidden, and inside the band but behind it: this is texture around
         the title, and it says nothing.

         SIX LAYERS, ONE SET OF GEOMETRY
         Everything is declared once, in the first layer's <defs>. SVG ids are
         document-scoped, so the other five reference the same paths and are
         mirrored in CSS. That is not tidiness - a duplicate id is a hard
         failure in audit_pages.py, so four corners cannot each carry a copy.

         The bands use preserveAspectRatio="none" because they run the width of
         the viewport at a fixed height, and stretching a horizontal run just
         makes it a longer run, which is what more circuit board looks like.
         The corners use xMinYMin meet instead: a cluster of 45 degree elbows
         must not shear, and it has to stay pinned to its own corner.

         A CHARGE IS ONE <use>, AND NEVER A GROUP OF THEM
         This layer once carried the charge on a <g> wrapping a <use> of a
         *group* of traces, on the reasoning that forty animated elements must
         beat two hundred. That reasoning was wrong, and measurably so.
         stroke-dashoffset is an inherited property: animating it on a group
         makes the browser push the new value down through every <use> shadow
         tree beneath it, every frame. Lighthouse put the page's Style & Layout
         work at 4,683ms against 686ms before it, and the site was reported as
         struggling. Flattening it to one <use> per charged trace cut that by
         about 1,500ms on its own; charging one trace in five rather than all
         of them cut another 1,000ms.

         So: the charge goes directly on the <use> that draws the trace, and it
         is deliberately not on every trace. The density here is the STATIC
         drawing, which costs one rasterisation; movement is the expensive part
         and is spent sparingly - three traces in each cluster and three in each
         band half, 24 against 216 drawn.

         The cost is close to linear in that number: about 1.1ms of style
         recalculation per second per charge, on top of a 25ms floor that is the
         static drawing. 24 charges measure 39ms/s, which is BELOW the 52ms/s
         this page cost before the circuitry was ever replaced. 48 would be
         83ms/s and 216 about 300ms/s, which is what was shipped and reported.
         If you raise it, measure - tools/check_style_budget.py, and read the
         table in docs/10-development/frontend/motion.md first.

         THE FOUR CORNERS SHARE THREE DURATIONS, AND THAT IS ALSO MEASURED
         Everywhere else on this site a shared duration is the fault being
         avoided. Here it is deliberate: twelve distinct durations give twelve
         distinct computed styles, and Chrome can then share none of them
         between elements. That measured 55ms of style recalculation per second
         against 35ms for the same twenty-four charges on three shared ones -
         and the four clusters are mirror images of each other, so sharing a
         phase reads as the board lighting symmetrically rather than as four
         copies of one loop. Within a cluster the three still differ.

         The two bands are the other deliberate exception: one speed, opposite
         directions, because they are one current going round. -->
    <div class="hero-circuit" aria-hidden="true">
      <svg class="hero-circuit__layer hero-circuit__layer--band-top" viewBox="0 0 1440 120" preserveAspectRatio="none" focusable="false">
        <defs>
        <path id="hc-c0" pathLength="100" d="M12 0L12 34L32 54L32 84L58 84L58 102"/>
        <path id="hc-c1" pathLength="100" d="M30 0L30 18L56 44L56 88"/>
        <path id="hc-c2" pathLength="100" d="M48 0L48 46L70 46L86 62L114 62"/>
        <path id="hc-c3" pathLength="100" d="M66 0L66 26L84 44L84 68L122 68"/>
        <path id="hc-c4" pathLength="100" d="M84 0L84 36L104 36L104 66L118 80"/>
        <path id="hc-c5" pathLength="100" d="M104 0L104 16L126 38L160 38L160 58"/>
        <path id="hc-c6" pathLength="100" d="M124 0L124 28L140 28L140 44L158 62"/>
        <path id="hc-c7" pathLength="100" d="M146 0L146 20L162 36L162 70"/>
        <path id="hc-c8" pathLength="100" d="M170 0L170 40L196 40L196 54"/>
        <path id="hc-c9" pathLength="100" d="M196 0L196 24L216 44L238 44"/>
        <path id="hc-c10" pathLength="100" d="M224 0L224 32L242 32"/>
        <path id="hc-c11" pathLength="100" d="M0 12L34 12L54 32L84 32L84 58L102 58"/>
        <path id="hc-c12" pathLength="100" d="M0 30L18 30L44 56L88 56"/>
        <path id="hc-c13" pathLength="100" d="M0 48L46 48L46 70L62 86L62 114"/>
        <path id="hc-c14" pathLength="100" d="M0 66L26 66L44 84L68 84L68 122"/>
        <path id="hc-c15" pathLength="100" d="M0 84L36 84L36 104L66 104L80 118"/>
        <path id="hc-c16" pathLength="100" d="M0 104L16 104L38 126L38 160L58 160"/>
        <path id="hc-c17" pathLength="100" d="M0 124L28 124L28 140L44 140L62 158"/>
        <path id="hc-c18" pathLength="100" d="M0 146L20 146L36 162L70 162"/>
        <path id="hc-c19" pathLength="100" d="M0 170L40 170L40 196L54 196"/>
        <path id="hc-c20" pathLength="100" d="M32 54L52 54L64 66"/>
        <path id="hc-c21" pathLength="100" d="M54 32L54 52L66 64"/>
        <path id="hc-c22" pathLength="100" d="M84 68L84 86L98 86"/>
        <path id="hc-c23" pathLength="100" d="M68 84L86 84L86 98"/>
        <path id="hc-c24" pathLength="100" d="M126 38L126 60L114 72"/>
        <path id="hc-c25" pathLength="100" d="M38 126L60 126L72 114"/>
        <path id="hc-c26" pathLength="100" d="M54 84L68 98L84 98"/>
        <path id="hc-c27" pathLength="100" d="M84 54L98 68L98 84"/>
        <path id="hc-c28" pathLength="100" d="M162 36L182 36L182 48"/>
        <path id="hc-c29" pathLength="100" d="M36 162L36 182L48 182"/>
        <g id="hc-corner-wires"><use href="#hc-c0"/><use href="#hc-c1"/><use href="#hc-c2"/><use href="#hc-c3"/><use href="#hc-c4"/><use href="#hc-c5"/><use href="#hc-c6"/><use href="#hc-c7"/><use href="#hc-c8"/><use href="#hc-c9"/><use href="#hc-c10"/><use href="#hc-c11"/><use href="#hc-c12"/><use href="#hc-c13"/><use href="#hc-c14"/><use href="#hc-c15"/><use href="#hc-c16"/><use href="#hc-c17"/><use href="#hc-c18"/><use href="#hc-c19"/><use href="#hc-c20"/><use href="#hc-c21"/><use href="#hc-c22"/><use href="#hc-c23"/><use href="#hc-c24"/><use href="#hc-c25"/><use href="#hc-c26"/><use href="#hc-c27"/><use href="#hc-c28"/><use href="#hc-c29"/></g>
        <g id="hc-corner-pads"><circle cx="58" cy="102" r="3.4"/><circle cx="56" cy="88" r="3.4"/><circle cx="114" cy="62" r="3.4"/><circle cx="122" cy="68" r="3.4"/><circle cx="118" cy="80" r="3.4"/><circle cx="160" cy="58" r="3.4"/><circle cx="158" cy="62" r="3.4"/><circle cx="162" cy="70" r="3.4"/><circle cx="196" cy="54" r="3.4"/><circle cx="238" cy="44" r="3.4"/><circle cx="242" cy="32" r="3.4"/><circle cx="102" cy="58" r="3.4"/><circle cx="88" cy="56" r="3.4"/><circle cx="62" cy="114" r="3.4"/><circle cx="68" cy="122" r="3.4"/><circle cx="80" cy="118" r="3.4"/><circle cx="58" cy="160" r="3.4"/><circle cx="62" cy="158" r="3.4"/><circle cx="70" cy="162" r="3.4"/><circle cx="54" cy="196" r="3.4"/><circle cx="64" cy="66" r="3.4"/><circle cx="66" cy="64" r="3.4"/><circle cx="98" cy="86" r="3.4"/><circle cx="86" cy="98" r="3.4"/><circle cx="114" cy="72" r="3.4"/><circle cx="72" cy="114" r="3.4"/><circle cx="84" cy="98" r="3.4"/><circle cx="98" cy="84" r="3.4"/><circle cx="182" cy="48" r="3.4"/><circle cx="48" cy="182" r="3.4"/><rect x="88" y="92" width="10" height="6" rx="1"/><rect x="146" y="74" width="6" height="10" rx="1"/><rect x="54" y="126" width="10" height="6" rx="1"/><rect x="118" y="110" width="6" height="10" rx="1"/><rect x="200" y="60" width="10" height="6" rx="1"/><rect x="36" y="100" width="10" height="6" rx="1"/><rect x="74" y="148" width="6" height="10" rx="1"/><rect x="172" y="92" width="10" height="6" rx="1"/><rect x="108" y="154" width="10" height="6" rx="1"/><rect x="30" y="168" width="6" height="10" rx="1"/></g>
        <g id="hc-corner-rings"><circle cx="140" cy="46" r="5"/><circle cx="46" cy="140" r="5"/><circle cx="206" cy="96" r="4.2"/><circle cx="96" cy="206" r="4.2"/><circle cx="176" cy="128" r="4"/><circle cx="128" cy="176" r="4"/><path d="M180 14v15"/><path d="M189 14v15"/><path d="M198 14v15"/><path d="M207 14v15"/><path d="M216 14v15"/><path d="M225 14v15"/><path d="M14 180h15"/><path d="M14 189h15"/><path d="M14 198h15"/><path d="M14 207h15"/><path d="M14 216h15"/><path d="M14 225h15"/><path d="M138 100v12"/><path d="M146 100v12"/><path d="M154 100v12"/><path d="M162 100v12"/><path d="M170 100v12"/><path d="M100 138h12"/><path d="M100 146h12"/><path d="M100 154h12"/><path d="M100 162h12"/><path d="M100 170h12"/><path d="M222 62v11"/><path d="M230 62v11"/><path d="M238 62v11"/><path d="M246 62v11"/></g>
        <path id="hc-b0" pathLength="100" d="M-100 132L-18 -12"/>
        <path id="hc-b1" pathLength="100" d="M-52 132L-14 66L44 66L82 -12"/>
        <path id="hc-b2" pathLength="100" d="M-4 132L78 -12"/>
        <path id="hc-b3" pathLength="100" d="M44 132L78 72L78 40L104 -12"/>
        <path id="hc-b4" pathLength="100" d="M92 132L174 -12"/>
        <path id="hc-b5" pathLength="100" d="M130 66l22 13"/>
        <path id="hc-b6" pathLength="100" d="M140 132L222 -12"/>
        <path id="hc-b7" pathLength="100" d="M188 132L239 43"/>
        <path id="hc-b8" pathLength="100" d="M236 132L274 66L332 66L370 -12"/>
        <path id="hc-b9" pathLength="100" d="M284 132L366 -12"/>
        <path id="hc-b10" pathLength="100" d="M332 132L366 72L366 40L392 -12"/>
        <path id="hc-b11" pathLength="100" d="M380 132L462 -12"/>
        <path id="hc-b12" pathLength="100" d="M428 132L466 66L524 66L562 -12"/>
        <path id="hc-b13" pathLength="100" d="M476 132L558 -12"/>
        <path id="hc-b14" pathLength="100" d="M524 132L558 72L558 40L584 -12"/>
        <path id="hc-b15" pathLength="100" d="M572 132L654 -12"/>
        <path id="hc-b16" pathLength="100" d="M610 66l22 13"/>
        <path id="hc-b17" pathLength="100" d="M620 132L702 -12"/>
        <path id="hc-b18" pathLength="100" d="M668 132L719 43"/>
        <path id="hc-b19" pathLength="100" d="M646 132L720 2"/>
        <path id="hc-b20" pathLength="100" d="M598 132L672 2L720 2"/>
        <path id="hc-b21" pathLength="100" d="M0 26h150l22 20h206"/>
        <path id="hc-b22" pathLength="100" d="M0 62h84l26-18h150"/>
        <path id="hc-b23" pathLength="100" d="M0 100h96l24-20h180"/>
        <g id="hc-band-half"><use href="#hc-b0"/><use href="#hc-b1"/><use href="#hc-b2"/><use href="#hc-b3"/><use href="#hc-b4"/><use href="#hc-b5"/><use href="#hc-b6"/><use href="#hc-b7"/><use href="#hc-b8"/><use href="#hc-b9"/><use href="#hc-b10"/><use href="#hc-b11"/><use href="#hc-b12"/><use href="#hc-b13"/><use href="#hc-b14"/><use href="#hc-b15"/><use href="#hc-b16"/><use href="#hc-b17"/><use href="#hc-b18"/><use href="#hc-b19"/><use href="#hc-b20"/><use href="#hc-b21"/><use href="#hc-b22"/><use href="#hc-b23"/></g>
        <g id="hc-band-wires"><use href="#hc-band-half"/><use href="#hc-band-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <g id="hc-band-pads-half"><rect x="-79" y="85" width="8" height="8" rx="1"/><rect x="65" y="85" width="8" height="8" rx="1"/><rect x="209" y="85" width="8" height="8" rx="1"/><rect x="353" y="85" width="8" height="8" rx="1"/><rect x="497" y="85" width="8" height="8" rx="1"/><rect x="641" y="85" width="8" height="8" rx="1"/><rect x="14" y="16" width="9" height="9" rx="1"/><rect x="14" y="38" width="9" height="9" rx="1"/><rect x="14" y="60" width="9" height="9" rx="1"/><rect x="14" y="82" width="9" height="9" rx="1"/><path d="M-4 40L4 48L-4 56L-12 48Z"/><path d="M140 40L148 48L140 56L132 48Z"/><path d="M284 40L292 48L284 56L276 48Z"/><path d="M428 40L436 48L428 56L420 48Z"/><path d="M572 40L580 48L572 56L564 48Z"/><path d="M716 40L724 48L716 56L708 48Z"/><circle cx="62" cy="17" r="3.2"/><circle cx="152" cy="79" r="3.4"/><circle cx="239" cy="43" r="4"/><circle cx="254" cy="17" r="3.2"/><circle cx="446" cy="17" r="3.2"/><circle cx="632" cy="79" r="3.4"/><circle cx="638" cy="17" r="3.2"/><circle cx="719" cy="43" r="4"/></g>
        <g id="hc-band-pads"><use href="#hc-band-pads-half"/><use href="#hc-band-pads-half" transform="translate(1440,0) scale(-1,1)"/></g>
        <g id="hc-band-rings-half"><circle cx="206" cy="46" r="4.6"/><circle cx="474" cy="66" r="4.6"/><circle cx="120" cy="80" r="4"/><circle cx="352" cy="30" r="4.4"/></g>
        <g id="hc-band-rings"><use href="#hc-band-rings-half"/><use href="#hc-band-rings-half" transform="translate(1440,0) scale(-1,1)"/></g>
        </defs>
        <g class="hero-circuit__wires"><use href="#hc-band-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-band-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-band-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p1" href="#hc-b0"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p2" href="#hc-b8"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p3" href="#hc-b16"/>
          <g transform="translate(1440,0) scale(-1,1)">
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p1" href="#hc-b0"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p2" href="#hc-b8"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p3" href="#hc-b16"/>
          </g>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--a" cx="206" cy="46" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--b" cx="474" cy="66" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--c" cx="966" cy="66" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--d" cx="1234" cy="46" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--band-bottom" viewBox="0 0 1440 120" preserveAspectRatio="none" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-band-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-band-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-band-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p1" href="#hc-b0"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p2" href="#hc-b8"/>
          <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--p3" href="#hc-b16"/>
          <g transform="translate(1440,0) scale(-1,1)">
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p1" href="#hc-b0"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p2" href="#hc-b8"/>
            <use class="hero-circuit__charge hero-circuit__charge--band hero-circuit__charge--mirrored hero-circuit__charge--p3" href="#hc-b16"/>
          </g>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--e" cx="206" cy="46" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--f" cx="474" cy="66" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--g" cx="966" cy="66" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--h" cx="1234" cy="46" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-tl" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c20"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--i" cx="56" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--j" cx="104" cy="36" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--k" cx="44" cy="56" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--l" cx="36" cy="104" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-tr" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c20"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--m" cx="56" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--n" cx="104" cy="36" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--o" cx="44" cy="56" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--p" cx="36" cy="104" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-bl" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c20"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--q" cx="56" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--r" cx="104" cy="36" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--s" cx="44" cy="56" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--t" cx="36" cy="104" r="3.6"/>
        </g>
      </svg>
      <svg class="hero-circuit__layer hero-circuit__layer--corner-br" viewBox="0 0 260 200" preserveAspectRatio="xMinYMin meet" focusable="false">
        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>
        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>
        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>
        <g class="hero-circuit__charges">
          <use class="hero-circuit__charge hero-circuit__charge--c1" href="#hc-c0"/>
          <use class="hero-circuit__charge hero-circuit__charge--c2 hero-circuit__charge--back" href="#hc-c10"/>
          <use class="hero-circuit__charge hero-circuit__charge--c3" href="#hc-c20"/>
        </g>
        <g class="hero-circuit__nodes">
          <circle class="hero-circuit__node hero-circuit__node--u" cx="56" cy="44" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--v" cx="104" cy="36" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--w" cx="44" cy="56" r="3.6"/>
          <circle class="hero-circuit__node hero-circuit__node--x" cx="36" cy="104" r="3.6"/>
        </g>
      </svg>
    </div>
<!--hero-circuit:end-->

<div class="container page-hero__inner">
      <h1 class="page-hero__title"><?= h($data['hero']['title']) ?></h1>
<?php if (trim((string)$data['hero']['subtitle']) !== ''): ?>
      <p class="page-hero__subtitle"><?= h($data['hero']['subtitle']) ?></p>
<?php endif; ?>
    </div>
  </section>

  <!-- ======================== Form + contact info ========================
       The NextJS route pairs the form with an information card; the live page
       leads with the form and lists the three offices below it. Both are kept:
       the pairing for the layout, the live page's content for what goes in it.
       ==================================================================== -->
  <section class="section contact" aria-labelledby="contact-heading">
    <div class="container contact__grid">

      <!-- ------------------------------- Form ------------------------------- -->
      <div data-reveal data-reveal-delay class="contact__form-panel">
        <h2 class="contact__title" id="contact-heading"><?= h($data['form']['title']) ?></h2>
<?php /* Printed unescaped, which is safe for exactly one reason: it went
         through rt_sanitise_html() before it was stored, and that function
         writes its output from an allow-list rather than passing anything
         through. See lib/html.php. */ ?>
<?php if (trim((string)$data['form']['lead']) !== ''): ?>
        <div class="contact__lead"><?= $data['form']['lead'] ?></div>
<?php endif; ?>

        <form
          class="form"
          action="/contact-handler.php"
          method="post"
          novalidate
          data-enhanced-form
          aria-describedby="contact-form-status">

          <div class="field">
            <label class="field__label" for="contact-name">
              Your Name <span class="field__required" aria-hidden="true">*</span>
            </label>
            <input
              class="field__control"
              id="contact-name"
              name="name"
              type="text"
              autocomplete="name"
              required
              aria-describedby="contact-name-error">
            <p class="field__error" id="contact-name-error" role="alert"></p>
          </div>

          <div class="field">
            <label class="field__label" for="contact-phone">
              Your Phone <span class="field__required" aria-hidden="true">*</span>
            </label>
            <input
              class="field__control"
              id="contact-phone"
              name="phone"
              type="tel"
              autocomplete="tel"
              required
              aria-describedby="contact-phone-error contact-phone-hint">
            <p class="field__hint" id="contact-phone-hint">
              Local or international, for example 01712345678 or +8801712345678.
            </p>
            <p class="field__error" id="contact-phone-error" role="alert"></p>
          </div>

          <div class="field">
            <label class="field__label" for="contact-email">
              Your E-Mail <span class="field__required" aria-hidden="true">*</span>
            </label>
            <input
              class="field__control"
              id="contact-email"
              name="email"
              type="email"
              autocomplete="email"
              required
              aria-describedby="contact-email-error">
            <p class="field__error" id="contact-email-error" role="alert"></p>
          </div>

          <div class="field">
            <label class="field__label" for="contact-subject">
              Type of Service <span class="field__required" aria-hidden="true">*</span>
            </label>
            <input
              class="field__control"
              id="contact-subject"
              name="subject"
              type="text"
              list="service-types"
              required
              aria-describedby="contact-subject-error contact-subject-hint">
            <!-- A datalist, not a select: the live field is free text, and a
                 fixed list would turn away work we do but have not listed. -->
            <datalist id="service-types">
<?php foreach ($data['form']['service_types'] as $service): ?>
              <option value="<?= h($service) ?>"></option>
<?php endforeach; ?>
            </datalist>
<?php if (trim((string)$data['form']['subject_hint']) !== ''): ?>
            <p class="field__hint" id="contact-subject-hint">
              <?= h($data['form']['subject_hint']) ?>
            </p>
<?php endif; ?>
            <p class="field__error" id="contact-subject-error" role="alert"></p>
          </div>

          <div class="field">
            <label class="field__label" for="contact-message">
              Message <span class="field__required" aria-hidden="true">*</span>
            </label>
            <textarea
              class="field__control"
              id="contact-message"
              name="message"
              rows="6"
              required
              aria-describedby="contact-message-error"></textarea>
            <p class="field__error" id="contact-message-error" role="alert"></p>
          </div>

          <div class="field field--check">
            <input
              class="field__checkbox"
              id="contact-privacy"
              name="privacy"
              type="checkbox"
              value="yes"
              required
              data-validate="consent"
              aria-describedby="contact-privacy-error">
            <label class="field__label field__label--check" for="contact-privacy">
              I have read and understand the
              <a href="/pages/privacy-policy/">privacy policy</a>.
              <span class="field__required" aria-hidden="true">*</span>
            </label>
            <p class="field__error" id="contact-privacy-error" role="alert"></p>
          </div>

          <!-- The live form uses an image CAPTCHA, which needs a session and a
               server round trip to generate. This is a honeypot instead: real
               visitors never see or tab to it, and the handler drops anything
               that fills it in. -->
          <div class="field field--honeypot" aria-hidden="true">
            <label class="field__label" for="contact-company">Company</label>
            <input
              class="field__control"
              id="contact-company"
              name="company"
              type="text"
              tabindex="-1"
              autocomplete="off">
          </div>

          <p class="form__status" id="contact-form-status" data-form-status role="status" aria-live="polite"></p>

          <button class="btn btn--primary btn--lg btn--block" type="submit">
            <svg class="icon icon--sm" aria-hidden="true" focusable="false"><use href="#paper-plane"></use></svg>
            Send
          </button>

<?php if (trim((string)$data['form']['note']) !== ''): ?>
          <p class="form__note">
            <svg class="icon icon--sm" aria-hidden="true" focusable="false"><use href="#lock"></use></svg>
            <?= h($data['form']['note']) ?>
          </p>
<?php endif; ?>
        </form>
      </div>

      <!-- ---------------------------- Quick contact ----------------------------
           The band and every row in it can be switched off in the admin.
           contact_shown_reach() answers for both, so an empty list here means
           "nothing to show" whichever switch produced it, and the heading goes
           with the list rather than standing over nothing. -->
<?php if ($reach): ?>
      <aside class="contact__aside" aria-labelledby="reach-heading">
        <h2 data-reveal data-reveal-delay class="contact__title" id="reach-heading"><?= h($data['reach']['title']) ?></h2>

<?php /* Every icon a reach row may carry is named in the comment below. Read
         that comment before deleting it: tools/inject_icons.py finds the
         symbols a page needs by scanning it for literal href="#name", and the
         name used here is chosen at run time, where the scan cannot see it.
         This is what keeps the sprite at the top of the page complete. The
         same list is CONTACT_ICONS in lib/contact.php.

         <use href="#envelope"> <use href="#phone"> <use href="#mobile-alt">
         <use href="#clock"> <use href="#map-marker-alt"> <use href="#building">
         <use href="#globe"> <use href="#headset"> <use href="#comment-alt">
         <use href="#paper-plane"> <use href="#calendar-alt">
         <use href="#info-circle"> <use href="#linkedin"> <use href="#github">
      */ ?>
        <ul class="reach" role="list">
<?php foreach ($reach as $item): ?>
          <li data-reveal data-reveal-delay class="reach__item">
<?php if (isset(CONTACT_ICONS[$item['icon'] ?? ''])): ?>
            <span class="reach__icon">
              <svg class="icon" aria-hidden="true" focusable="false"><use href="#<?= h($item['icon']) ?>"></use></svg>
            </span>
<?php endif; ?>
            <div>
              <h3 class="reach__label"><?= h((string)$item['label']) ?></h3>
<?php /* A row may hold several values — three numbers under one "Phone"
         heading, the way the office cards list theirs. One value stays a
         plain paragraph; more than one becomes a stacked list, so the
         heading is not repeated once per number. */ ?>
<?php if (count($item['values']) > 1): ?>
              <p class="reach__value reach__value--many">
<?php foreach ($item['values'] as $value): ?>
<?php $href = contact_reach_href($item, $value); $external = $href !== null && str_starts_with($href, 'http'); ?>
<?php if ($href !== null): ?>
                <a href="<?= h($href) ?>"<?= $external ? ' rel="noopener noreferrer" target="_blank"' : '' ?>><?= h(contact_reach_text($item, $value)) ?></a>
<?php else: ?>
                <span><?= h(contact_reach_text($item, $value)) ?></span>
<?php endif; ?>
<?php endforeach; ?>
              </p>
<?php else: ?>
<?php $value = (string)($item['values'][0] ?? ''); ?>
<?php $href = contact_reach_href($item, $value); $external = $href !== null && str_starts_with($href, 'http'); ?>
<?php if ($href !== null): ?>
              <p class="reach__value"><a href="<?= h($href) ?>"<?= $external ? ' rel="noopener noreferrer" target="_blank"' : '' ?>><?= h(contact_reach_text($item, $value)) ?></a></p>
<?php else: ?>
              <p class="reach__value"><?= h(contact_reach_text($item, $value)) ?></p>
<?php endif; ?>
<?php endif; ?>
            </div>
          </li>
<?php endforeach; ?>
        </ul>
      </aside>
<?php endif; ?>
    </div>
  </section>

  <!-- ============================= Our offices ============================= -->
<?php if ($offices): ?>
  <section class="section section--surface offices" aria-labelledby="offices-heading">
    <div class="container">
<?php if (trim((string)$data['offices']['title']) !== '' || trim((string)$data['offices']['lead']) !== ''): ?>
      <div data-reveal data-reveal-delay class="section__header">
<?php if (trim((string)$data['offices']['eyebrow']) !== ''): ?>
        <span class="section__eyebrow"><?= h($data['offices']['eyebrow']) ?></span>
<?php endif; ?>
        <h2 class="section__title" id="offices-heading"><?= h($data['offices']['title']) ?></h2>
<?php if (trim((string)$data['offices']['lead']) !== ''): ?>
        <div class="section__lead"><?= $data['offices']['lead'] ?></div>
<?php endif; ?>
      </div>
<?php endif; ?>

      <ul class="offices__grid" role="list">
<?php foreach ($offices as $office): ?>
        <li data-reveal data-reveal-delay class="office">
          <?= contact_flag_picture($office) ?>
          <h3 class="office__name"><?= h((string)$office['name']) ?></h3>
          <address class="office__body">
<?php if (trim((string)$office['address']) !== ''): ?>
            <p class="office__line">
              <svg class="icon icon--sm office__icon" aria-hidden="true" focusable="false"><use href="#map-marker-alt"></use></svg>
              <?= h((string)$office['address']) ?>
            </p>
<?php endif; ?>
<?php if ($office['phones']): ?>
            <p class="office__line">
              <svg class="icon icon--sm office__icon" aria-hidden="true" focusable="false"><use href="#phone"></use></svg>
              <span class="office__phones">
<?php foreach ($office['phones'] as $phone): ?>
                <a href="tel:<?= h(contact_tel($phone)) ?>"><?= h($phone) ?></a>
<?php endforeach; ?>
              </span>
            </p>
<?php endif; ?>
<?php if (trim((string)$office['hours']) !== ''): ?>
            <p class="office__line">
              <svg class="icon icon--sm office__icon" aria-hidden="true" focusable="false"><use href="#clock"></use></svg>
              <?= h((string)$office['hours']) ?>
            </p>
<?php endif; ?>
          </address>
        </li>
<?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endif; ?>

</main>

<?php body_footer(); ?>

<?php body_dock('/pages/contact/'); ?>

<!-- Deferred so nothing blocks rendering. Order matters only in that main.js
     runs last: each module registers itself on window.Tech4Time, and main.js
     calls their init(). Pages that need no forms can omit forms.js. -->
<script src="/assets/js/theme-toggle.js" defer></script>
<script src="/assets/js/nav.js" defer></script>
<script src="/assets/js/animations.js" defer></script>
<script src="/assets/js/forms.js?v=2" defer></script>
<script src="/assets/js/dashboard.js" defer></script>
<script src="/assets/js/tech-sphere.js" defer></script>
<!-- Versioned for the same reason the stylesheets are, and with a sharper
     edge: MODULES in this file is a hardcoded allow list, so a stale copy
     silently skips every module added since — no error, no console line,
     just a feature that is not there. -->
<script src="/assets/js/circuit.js?v=2" defer></script>
<script src="/assets/js/main.js?v=3" defer></script>
</body>
</html>
