/* ==========================================================================
   Tech4TIME — circuit.js
   The charges running through the page-title circuitry, drawn on a canvas.

   WHY THIS IS NOT CSS, WHICH IS WHERE IT STARTED
   The traces are SVG and the charges used to be too: a stroke-dasharray on
   each trace with stroke-dashoffset animated in CSS. That is correct and it
   is cheap for a handful of traces. It is not cheap for two hundred, because
   every animated trace is a style recalculation every frame, and putting the
   animation on a group instead is worse — stroke-dashoffset is inherited, so
   the browser then pushes the value down through every <use> shadow tree
   underneath it. That shipped on 2026-09-03 at 895ms of style recalculation
   per second, roughly a core, and the site was reported as struggling.

   A canvas has no style to recalculate. One element, one clear, one pass of
   short strokes — so the charge can go back on ALL 176 traces, which is what
   was wanted before the cost of doing it in CSS got in the way.

   THE GEOMETRY IS READ, NEVER DUPLICATED
   Every path is sampled out of the SVG that is already in the page, so there
   is one source of truth for the drawing. Change tools/templates/
   hero-circuit.html and this follows; there is no second copy to forget.

   WITHOUT JAVASCRIPT
   Nothing here runs and nothing is missing: the SVG circuit is drawn, still.
   Motion decorates it and is never the only way to reach anything, which is
   the rule in docs/10-development/frontend/motion.md. Reduced motion gets the
   same still drawing with the charges parked where they fall.
   ========================================================================== */

(function (global) {
  "use strict";

  var doc = global.document;

  /* Matches the CSS that used to draw these: 22 units lit in every 100. */
  var LIT = 0.22;
  var BAND_SECONDS = 4;
  /* The clusters are slower than the bands — they are the board, not the
     current — and the three speeds differ so lit neighbours never pair up. */
  var CORNER_SECONDS = [12, 13, 14];
  /* How finely a path is sampled, in viewBox units. These traces are runs of
     straight line joined by right angles and 45 degree elbows, so sampling
     finer than the elbows buys nothing and costs a lineTo per point per frame
     for as long as the page is open. */
  var SAMPLE = 12;
  /* THE CANVAS DRAWS AT THE SCREEN'S RESOLUTION, AND IT DID NOT USED TO
     This was 1, on the reasoning that thin strokes of a single colour behind a
     title cannot show the difference and cost in direct proportion. That was
     decided at a desk, where the choice is between one device pixel and two.
     On a phone it is between one and three, and the layer it applies to is the
     brightest thing in the banner -- the charges, in the accent colour, moving,
     over traces that are SVG and therefore always drawn at the full resolution
     of the screen. The result was a sharp drawing with a soft glow crawling
     over it, and it was reported from a phone as the whole banner looking
     pixelated. It was the only part of it that was.

     So: the device's own ratio, capped at 3 because nothing above that is a
     real screen. Every width set after the transform below -- ctx.lineWidth,
     CHARGE_MIN/MAX, the dot radii -- is in CSS pixels, so this sharpens the
     layer and changes no weight.

     It is not free: fill rate goes with the square of the ratio, so a 2x
     desktop is four times the pixels of this at 1x. Measured at 182ms of
     main-thread task time per second against 139 before, on /pages/about/ at
     2x -- the realistic worst case, since a desktop is 1x or 2x and never 3x.
     At 1x it is 137 against 135, which is to say nothing at all, and on a
     phone 208 against 195, because the bands standing down there pay for most
     of the extra resolution.

     NOT measured with tools/check_style_budget.py, which cannot see it:
     that reads RecalcStyleDuration + LayoutDuration, and rasterising a canvas
     is neither, so it reports the same figure whatever this is set to. The
     table and the method are in docs/10-development/frontend/motion.md. */
  var MAX_DPR = 3;
  /* And it does not need sixty frames a second. A charge crossing a trace over
     four seconds is not made smoother by drawing it twice as often; halving
     the rate halves the cost of the whole layer, which is the difference
     between this being affordable on every trace and not. */
  var FPS = 30;
  /* How many distinct alphas the fade is rounded to. Each one is a separate
     batched stroke, so this is a count of draw calls, not of traces. */
  var BUCKETS = 5;
  /* THE CHARGE IS DRAWN AT THE WEIGHT THE DRAWING IS, NOT AT A FIXED ONE
     This was a flat 2.6 device pixels on every layer at every size. That was
     survivable while the band was full-bleed; it is not now. A band's box runs
     from about 1,220px down to 345px for the same 1,440-unit viewBox, so the
     static drawing under the charge renders from 2.2px down to 0.9px -- and a
     charge held at 2.6 while its own trace fades to a hair stops being the lit
     part of a line and becomes the only part. Measured at 768px it was exactly
     that: a smear of moving white over a drawing nobody could see.

     So the pen is READ, per layer, off .hero-circuit__wires and scaled the way
     the browser scales it, for the same reason the ink is read rather than
     written twice: one source, and a stylesheet change carries here by itself.
     The charge stays half again heavier than its trace, which is what makes it
     read as lit, and the bounds keep it visible on a phone and modest on a
     desktop. */
  var CHARGE_OVER_WIRE = 1.55;
  var CHARGE_MIN = 1.2;
  var CHARGE_MAX = 3.2;

  /* Which way each layer is turned. The SVG mirrors are done in CSS, and the
     canvas has to arrive at the same picture, so they are stated once here
     rather than read back out of a computed transform. */
  var LAYERS = {
    "band-top": {view: [1440, 114], fit: "none", flipX: false, flipY: false},
    "band-bottom": {view: [1440, 114], fit: "none", flipX: false, flipY: true},
    "corner-tl": {view: [200, 215], fit: "meet", flipX: false, flipY: false},
    "corner-tr": {view: [200, 215], fit: "meet", flipX: true, flipY: false},
    "corner-bl": {view: [200, 215], fit: "meet", flipX: false, flipY: true},
    "corner-br": {view: [200, 215], fit: "meet", flipX: true, flipY: true}
  };

  function kindOf(layer) {
    var name;
    for (name in LAYERS) {
      if (LAYERS.hasOwnProperty(name) &&
          layer.classList.contains("hero-circuit__layer--" + name)) {
        return name;
      }
    }
    return null;
  }

  /* ---- sampling -------------------------------------------------------- */

  /* A path becomes a polyline plus the running distance along it, which is
     what lets a charge be "the stretch between 41% and 63% of the way along"
     without measuring anything again. Done once; the geometry never moves. */
  function sample(path) {
    var total = path.getTotalLength();
    if (!total) { return null; }
    var steps = Math.max(2, Math.ceil(total / SAMPLE));
    var pts = [], run = [0], last = null, i, p, d;
    for (i = 0; i <= steps; i += 1) {
      p = path.getPointAtLength(total * (i / steps));
      pts.push(p.x, p.y);
      if (last) {
        d = Math.sqrt((p.x - last.x) * (p.x - last.x) +
                      (p.y - last.y) * (p.y - last.y));
        run.push(run[run.length - 1] + d);
      }
      last = p;
    }
    return {pts: pts, run: run, total: run[run.length - 1] || total};
  }

  function Circuit(root, still) {
    this.root = root;
    this.still = !!still;
    this.watchers = [];
    this.traces = [];
    this.geometry = {};
    this.running = false;
    this.frame = 0;
    this.canvas = doc.createElement("canvas");
    this.canvas.className = "hero-circuit__charge-canvas";
    this.canvas.setAttribute("aria-hidden", "true");
    this.ctx = this.canvas.getContext("2d");
    this.tick = this.tick.bind(this);
    this.onResize = this.onResize.bind(this);
    this.onVisibility = this.onVisibility.bind(this);
  }

  /* Read every trace the drawing declares, once. Both sets live in the first
     layer's <defs>; the corners use one and the bands the other. */
  Circuit.prototype.readGeometry = function () {
    var self = this, ids = ["c", "b"], k;
    for (k = 0; k < ids.length; k += 1) {
      (function (prefix) {
        var out = [], i = 0, path;
        for (;;) {
          path = doc.getElementById("hc-" + prefix + i);
          if (!path) { break; }
          var s = sample(path);
          if (s) { out.push(s); }
          i += 1;
        }
        self.geometry[prefix] = out;
      })(ids[k]);
    }
  };

  /* The same fades the stylesheet puts on the layers, evaluated at a point:
     linear down the bands, radial away from each corner. */
  Circuit.prototype.fadeAt = function (name, spec, lb, box, x, y) {
    var lx = x - (lb.left - box.left);
    var ly = y - (lb.top - box.top);
    if (name.indexOf("band") === 0) {
      var down = spec.flipY ? (lb.height - ly) : ly;
      var t = down / lb.height;
      return t <= 0.72 ? 1 : Math.max(0, 1 - (t - 0.72) / 0.28);
    }
    var cx = spec.flipX ? lb.width : 0;
    var cy = spec.flipY ? lb.height : 0;
    var r = Math.max(lb.width, lb.height) * 1.3;
    var d = Math.sqrt((lx - cx) * (lx - cx) + (ly - cy) * (ly - cy)) / r;
    if (d <= 0.4) { return 1; }
    return Math.max(0, 1 - (d - 0.4) / 0.56);
  };

  Circuit.prototype.measure = function () {
    var box = this.root.getBoundingClientRect();
    if (!box.width || !box.height) { return false; }
    var dpr = Math.min(global.devicePixelRatio || 1, MAX_DPR);
    this.w = box.width;
    this.h = box.height;
    this.canvas.width = Math.round(this.w * dpr);
    this.canvas.height = Math.round(this.h * dpr);
    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    /* One entry per drawn trace: the polyline in canvas pixels, its length,
       how long its charge takes and where in that cycle it starts. Built at
       this size and reused every frame until the box changes. */
    this.traces = [];
    this.dots = [];
    var layers = this.root.querySelectorAll(".hero-circuit__layer");
    var self = this, seed = 0;
    Array.prototype.forEach.call(layers, function (layer) {
      var name = kindOf(layer);
      if (!name) { return; }
      var spec = LAYERS[name];
      var lb = layer.getBoundingClientRect();
      if (!lb.width || !lb.height) { return; }
      var vw = spec.view[0], vh = spec.view[1];
      var sx, sy, ox, oy;
      if (spec.fit === "none") {
        sx = lb.width / vw;
        sy = lb.height / vh;
      } else {
        sx = sy = Math.min(lb.width / vw, lb.height / vh);
      }
      ox = lb.left - box.left;
      oy = lb.top - box.top;

      var band = name.indexOf("band") === 0;
      var set = self.geometry[band ? "b" : "c"] || [];

      /* A stroke is scaled with its drawing, and a band is scaled unevenly --
         so the weight that decides legibility is the smaller of the two. */
      var wires = layer.querySelector(".hero-circuit__wires");
      var pen = wires
        ? parseFloat(global.getComputedStyle(wires).strokeWidth) || 2
        : 2;
      var lit = pen * Math.min(sx, sy) * CHARGE_OVER_WIRE;
      lit = Math.max(CHARGE_MIN, Math.min(CHARGE_MAX, lit));
      lit = Math.round(lit * 4) / 4;

      function place(s, mirrorInView) {
        var pts = new Float32Array(s.pts.length), i, x, y;
        for (i = 0; i < s.pts.length; i += 2) {
          x = mirrorInView ? (vw - s.pts[i]) : s.pts[i];
          y = s.pts[i + 1];
          x = spec.flipX ? (lb.width - x * sx) : x * sx;
          y = spec.flipY ? (lb.height - y * sy) : y * sy;
          pts[i] = ox + x;
          pts[i + 1] = oy + y;
        }
        seed += 1;
        /* How much of the layer's fade reaches this trace, worked out once
           from its midpoint. The SVG layers fade before they reach the title
           and the charges have to fade with them — but doing that as a mask
           means compositing the whole canvas every frame, which measured far
           dearer than the drawing itself. A trace does not move, so its fade
           does not either: quantised into a few buckets, it costs one alpha
           change per bucket per frame instead. */
        var mid = Math.floor(pts.length / 4) * 2;
        var fade = self.fadeAt(name, spec, lb, box, pts[mid], pts[mid + 1]);
        if (fade < 0.06) { return; }
        self.traces.push({
          alpha: Math.round(fade * BUCKETS) / BUCKETS,
          width: lit,
          pts: pts,
          run: s.run,
          total: s.total,
          seconds: band ? BAND_SECONDS
                        : CORNER_SECONDS[seed % CORNER_SECONDS.length],
          /* The bands are one current going round: left to right along the
             top, right to left along the bottom. Everything else alternates
             so neighbouring lit lines run against each other. */
          back: band ? (spec.flipY !== mirrorInView) : (seed % 2 === 1),
          offset: (seed * 0.37) % 1
        });
      }

      set.forEach(function (s) { place(s, false); });
      if (band) { set.forEach(function (s) { place(s, true); }); }

      /* The junction dots come across as well. Left in the SVG they are the
         only thing still animating there, which keeps the whole document
         rendering at sixty frames a second whatever this canvas does — and
         measured, that interaction cost more than the dots themselves. With
         them here, nothing in the band animates except this one element. */
      Array.prototype.forEach.call(
        layer.querySelectorAll(".hero-circuit__node"), function (dot) {
          var x = parseFloat(dot.getAttribute("cx"));
          var y = parseFloat(dot.getAttribute("cy"));
          var px = spec.flipX ? (lb.width - x * sx) : x * sx;
          var py = spec.flipY ? (lb.height - y * sy) : y * sy;
          px += ox;
          py += oy;
          var fade = self.fadeAt(name, spec, lb, box, px, py);
          if (fade < 0.06) { return; }
          seed += 1;
          self.dots.push({
            x: px, y: py,
            r: parseFloat(dot.getAttribute("r") || 3.6) * sy,
            alpha: fade,
            seconds: 7 + (seed % 9) * 4,
            offset: (seed * 0.29) % 1
          });
        });
    });

    this.group();
    return true;
  };

  /* Batched by the alpha its fade rounds to AND by the weight its layer draws
     at, because both are set once per stroke(). Quantising the weight to a
     quarter pixel above is what keeps that a handful of batches rather than
     one per trace. */
  Circuit.prototype.group = function () {
    var by = {}, i, t, key;
    for (i = 0; i < this.traces.length; i += 1) {
      t = this.traces[i];
      key = t.alpha + "@" + t.width;
      if (!by[key]) { by[key] = {alpha: t.alpha, width: t.width, traces: []}; }
      by[key].traces.push(t);
    }
    this.groups = Object.keys(by).map(function (k) { return by[k]; });
  };

  /* The ink follows the theme, so it is read from the stylesheet rather than
     written twice. Re-read whenever the theme changes. */
  Circuit.prototype.readInk = function () {
    var cs = global.getComputedStyle(this.root);
    var ink = (cs.getPropertyValue("--charge-ink") || "").trim();
    this.ink = ink || "#8a8d92";
  };

  /* ONE PATH PER BUCKET, NOT ONE PER TRACE
     Two hundred and sixteen stroke() calls a frame is most of the cost of
     this; the segments themselves are tiny. Traces are grouped by the alpha
     their fade rounds to, every lit stretch in a group is added to a single
     path, and each group is stroked once — so the frame is a handful of draw
     calls however many traces are lit. */
  Circuit.prototype.draw = function (seconds) {
    var ctx = this.ctx, groups = this.groups, g, i, t, phase, from, to, list;
    ctx.clearRect(0, 0, this.w, this.h);
    /* Butt caps and miter joins: a round cap is a curve to rasterise at both
       ends of every lit stretch, and at this weight nobody can tell. */
    ctx.lineCap = "butt";
    ctx.lineJoin = "miter";
    ctx.strokeStyle = this.ink;

    for (g = 0; g < groups.length; g += 1) {
      list = groups[g].traces;
      if (!list.length) { continue; }
      ctx.globalAlpha = groups[g].alpha;
      ctx.lineWidth = groups[g].width;
      ctx.beginPath();
      for (i = 0; i < list.length; i += 1) {
        t = list[i];
        phase = (seconds / t.seconds + t.offset) % 1;
        if (t.back) { phase = 1 - phase; }
        from = phase * t.total;
        to = from + LIT * t.total;
        this.addRun(t, from, to);
        /* A charge that runs off the end comes back on at the start, so the
           loop closes instead of blinking. */
        if (to > t.total) { this.addRun(t, 0, to - t.total); }
      }
      ctx.stroke();
    }

    /* The junctions: one fill for all of them, breathing on their own cycles.
       opacity and radius both move, as the CSS keyframes did. */
    var dots = this.dots, d, pulse;
    ctx.fillStyle = this.ink;
    for (i = 0; i < dots.length; i += 1) {
      d = dots[i];
      pulse = 0.5 + 0.5 * Math.sin(
        ((seconds / d.seconds + d.offset) % 1) * Math.PI * 2);
      ctx.globalAlpha = d.alpha * (0.16 + 0.34 * pulse);
      ctx.beginPath();
      ctx.arc(d.x, d.y, d.r * (0.85 + 0.3 * pulse), 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.globalAlpha = 1;
  };

  /* Stroke only the lit stretch of a polyline. Drawing the short piece that
     is actually bright is much less work than dashing the whole path and
     letting the rasteriser throw most of it away. */
  Circuit.prototype.addRun = function (t, from, to) {
    var run = t.run, pts = t.pts, n = run.length, i, started = false, f;
    var ctx = this.ctx;
    for (i = 0; i < n - 1; i += 1) {
      if (run[i + 1] < from || run[i] > to) { continue; }
      if (!started) {
        f = (from - run[i]) / ((run[i + 1] - run[i]) || 1);
        f = f < 0 ? 0 : (f > 1 ? 1 : f);
        ctx.moveTo(pts[i * 2] + (pts[i * 2 + 2] - pts[i * 2]) * f,
                   pts[i * 2 + 1] + (pts[i * 2 + 3] - pts[i * 2 + 1]) * f);
        started = true;
      }
      if (run[i + 1] <= to) {
        ctx.lineTo(pts[i * 2 + 2], pts[i * 2 + 3]);
      } else {
        f = (to - run[i]) / ((run[i + 1] - run[i]) || 1);
        f = f < 0 ? 0 : (f > 1 ? 1 : f);
        ctx.lineTo(pts[i * 2] + (pts[i * 2 + 2] - pts[i * 2]) * f,
                   pts[i * 2 + 1] + (pts[i * 2 + 3] - pts[i * 2 + 1]) * f);
        break;
      }
    }
  };

  Circuit.prototype.tick = function (now) {
    if (!this.running) { return; }
    /* Still driven by rAF — so it stays in step with the compositor and stops
       when the tab does — but it only draws on every other one. */
    if (now - this.last >= (1000 / FPS) - 1) {
      this.last = now;
      this.draw(now / 1000);
    }
    this.frame = global.requestAnimationFrame(this.tick);
  };

  Circuit.prototype.start = function () {
    if (this.running || this.still) { return; }
    this.running = true;
    this.last = 0;
    this.frame = global.requestAnimationFrame(this.tick);
  };

  Circuit.prototype.stop = function () {
    this.running = false;
    if (this.frame) {
      global.cancelAnimationFrame(this.frame);
      this.frame = 0;
    }
  };

  Circuit.prototype.onResize = function () {
    var self = this;
    if (this.pending) { return; }
    this.pending = global.requestAnimationFrame(function () {
      self.pending = 0;
      if (self.measure() && self.still) { self.draw(0); }
    });
  };

  Circuit.prototype.onVisibility = function () {
    if (doc.hidden) { this.stop(); } else if (this.onScreen !== false) { this.start(); }
  };

  Circuit.prototype.attach = function () {
    this.root.appendChild(this.canvas);
    this.readGeometry();
    this.readInk();
    if (!this.measure()) { return; }

    /* The SVG's own charges are switched off only once this has something to
       put in their place, so a failure here leaves the CSS version running
       rather than a band with no charges at all. */
    this.root.classList.add("hero-circuit--canvas");

    var self = this;
    if (this.still) {
      this.draw(0);
    } else {
      this.start();
    }

    /* THE ELEMENT IS WATCHED, NOT THE WINDOW, AND ROTATION IS WHY
       This was a resize listener, which is enough for a dragged desktop window
       and not enough for a phone being turned over: on mobile the resize can
       arrive before layout has settled, so measure() reads the box the banner
       had in the OLD orientation and every trace position, every scale and the
       canvas backing store are computed from it. One stale read is the whole
       layer wrong, and it stays wrong until something else moves.

       A ResizeObserver fires when .hero-circuit's own box changes, after
       layout, which is exactly the question measure() asks. It also covers two
       things the listener never did: the reflow when a mobile address bar
       collapses, and the reflow when the webfont lands and the title rewraps
       to a different number of lines. Both change the banner's height without
       changing the window's.

       The listener stays as the fallback. Nothing in this file needs a
       polyfill; a browser without ResizeObserver still gets the old
       behaviour rather than none. */
    if (global.ResizeObserver) {
      var ro = new global.ResizeObserver(this.onResize);
      ro.observe(this.root);
      this.watchers.push(function () { ro.disconnect(); });
    } else {
      global.addEventListener("resize", this.onResize);
      this.watchers.push(function () {
        global.removeEventListener("resize", self.onResize);
      });
    }
    doc.addEventListener("visibilitychange", this.onVisibility);
    this.watchers.push(function () {
      doc.removeEventListener("visibilitychange", self.onVisibility);
    });

    /* Off screen, it stops. The band is at the top of a long page and there
       is no reason to keep painting it while somebody reads the bottom. */
    if (global.IntersectionObserver) {
      var io = new global.IntersectionObserver(function (entries) {
        var seen = entries[entries.length - 1].isIntersecting;
        self.onScreen = seen;
        if (seen && !doc.hidden) { self.start(); } else { self.stop(); }
      });
      io.observe(this.root);
      this.watchers.push(function () { io.disconnect(); });
    }

    /* The ink is a theme token, so a change of theme repaints rather than
       leaving last mode's colour on the canvas. */
    if (global.MutationObserver) {
      var mo = new global.MutationObserver(function () {
        self.readInk();
        if (self.still) { self.draw(0); }
      });
      mo.observe(doc.documentElement, {attributes: true,
                                       attributeFilter: ["data-theme"]});
      this.watchers.push(function () { mo.disconnect(); });
    }
    var dark = global.matchMedia("(prefers-color-scheme: dark)");
    function repaint() {
      self.readInk();
      if (self.still) { self.draw(0); }
    }
    if (dark.addEventListener) {
      dark.addEventListener("change", repaint);
      this.watchers.push(function () { dark.removeEventListener("change", repaint); });
    }
  };

  Circuit.prototype.detach = function () {
    this.stop();
    if (this.pending) { global.cancelAnimationFrame(this.pending); this.pending = 0; }
    while (this.watchers.length) { this.watchers.pop()(); }
    this.root.classList.remove("hero-circuit--canvas");
    if (this.canvas.parentNode) { this.canvas.parentNode.removeChild(this.canvas); }
  };

  global.Tech4Time = global.Tech4Time || {};
  global.Tech4Time.circuit = {
    init: function () {
      var root = doc.querySelector(".hero-circuit");
      if (!root || !doc.createElement("canvas").getContext) { return; }

      var calm = global.matchMedia("(prefers-reduced-motion: reduce)");
      var circuit = null;

      function sync() {
        var wantStill = calm.matches;
        if (circuit && circuit.still !== wantStill) {
          circuit.detach();
          circuit = null;
        }
        if (!circuit) {
          circuit = new Circuit(root, wantStill);
          circuit.attach();
        }
      }

      if (calm.addEventListener) {
        calm.addEventListener("change", sync);
      } else if (calm.addListener) {
        calm.addListener(sync);
      }
      sync();
    }
  };
})(window);
