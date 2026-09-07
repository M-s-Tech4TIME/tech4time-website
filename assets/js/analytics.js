/**
 * Tech4TIME — Google Analytics, configured without an inline script.
 *
 * WHY THIS FILE EXISTS
 * Google's own snippet is two <script> blocks and the second one is inline.
 * The Content Security Policy here is script-src 'self', so a browser refuses
 * an inline block outright — no error a visitor sees, no console line most
 * people look at, and a page that appears to be measuring and is not. This is
 * the same two lines, in a file this site serves, reading the measurement id
 * off its own tag rather than having it written into the source.
 *
 * IT DOES NOTHING UNLESS ASKED TO. lib/head.php emits this tag only while a
 * measurement id is set on the SEO screen, and refuses to emit an id that is
 * not the shape Google issues. With no id there is no tag, no request to
 * another origin, and the strict policy above is the one the page sends.
 *
 * Deferred, so it never competes with rendering: the measurement is worth
 * nothing if it costs the page the speed it is measuring.
 */
(function (global) {
  "use strict";

  var doc = global.document;
  var tag = doc.currentScript || doc.querySelector("script[data-ga]");
  var id = tag && tag.getAttribute("data-ga");

  if (!id) {
    return;
  }

  /* gtag.js reads this array, and it may already have been created by the
     loader script above — which is async, so the order of the two is not
     decided here. Pushing into whichever exists is what makes that safe. */
  global.dataLayer = global.dataLayer || [];

  function gtag() {
    global.dataLayer.push(arguments);
  }

  global.gtag = gtag;
  gtag("js", new Date());
  gtag("config", id);
})(window);
