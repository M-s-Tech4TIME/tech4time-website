#!/usr/bin/env python3
"""
Redraw tools/templates/hero-circuit.html from the company's own banner artwork.

Build tool. NOT deployed to the web server (see tools/README.md).

    python3 tools/build_hero_circuit.py --resolve   # re-read the SVG (needs Chrome)
    python3 tools/build_hero_circuit.py             # rewrite the template
    python3 tools/build_hero_circuit.py --check     # assert it is current, change nothing

WHY THIS EXISTS
The circuitry around every page title used to be drawn from description -- an
interpretation of the company's printed material, typed as path coordinates by
hand. The company then supplied the artwork itself, and the drawing in the
template is now EXTRACTED from it rather than approximated.

That extraction is arithmetic, not taste: a clip to apply, a bounding box to
measure, a scale to fit. Arithmetic that lives only in somebody's afternoon is
arithmetic nobody can check or redo, so it lives here. Change the fit, the
viewBox or which traces carry a charge, run this, and the template follows.

THE TWO HALVES, AND WHY THEY ARE SEPARATE
Reading the reference needs a browser. Inkscape buries every shape under
composed matrix() transforms and 542 clipPath elements -- 230 of which are a
<use> of a rect defined elsewhere -- and the honest way to resolve that is to
ask an SVG engine rather than to reimplement one. So --resolve drives Chrome.

But a check that needs a browser is a check that gets skipped, and a check that
reports success when it could not run is worse than no check at all. So the
resolved geometry is COMMITTED, as hero-circuit.geometry.json, and everything
after it is plain Python. --check regenerates the template from that file and
compares: no browser, runs anywhere, and fails loudly when it cannot run.

WHAT IS CLASSIFIED, AND HOW
Not by element id. The reference's ids are Inkscape's -- "polyline13-6-2-8-4"
-- and say nothing. By paint and by shape:

    stroked, open                     -> a trace, clipped, carrying a charge
    filled                            -> a pad, or one of the little rotated
                                         rectangles a board carries where a
                                         component lands
    stroked, closed, small            -> a ring: a via, drawn hollow

A trace is clipped against its own clip rectangle with a Liang-Barsky segment
clip, because the cluster's diagonal edge IS that clip and not drawn geometry.
Skip it and the fan becomes a square.
"""

import argparse
import json
import math
import re
import shutil
import subprocess
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TEMPLATES = ROOT / "tools" / "templates"
GEOMETRY = TEMPLATES / "hero-circuit.geometry.json"
TEMPLATE = TEMPLATES / "hero-circuit.html"

# The banner as the company composes it: the artwork fills this page edge to
# edge and bleeds slightly past it. The other file in references/ is the same
# drawing parked on a page it does not fill, which shows the pieces but not the
# arrangement -- so it is not the one read here.
SOURCE = ROOT / "references" / "t4t_circuitry_6000_2031_300.svg"

# The six groups the reference is built from: one cluster under four flip
# matrices, and one band plus its vertical mirror. Only two are read -- the
# other four are those two turned over, which the stylesheet does itself.
CORNER_GROUP = "g300"     # the top-left cluster, unflipped in this file
BAND_GROUP = "g482"       # the top band

# The boxes the drawing is fitted into. The corner takes the cluster's own
# ratio (0.93:1) so that "meet" never leaves a margin; the band's half takes
# the run's (6.31:1), and the full box is two of those mirrored.
CORNER_VIEW = (200, 215)
# ONE TILE OF THE BAND, NOT THE WHOLE VIEWBOX
# The drawing fills the left half and the right half is its mirror, so this is
# the width the mirror is taken about and the box the artwork is fitted into.
# The viewBox emitted below is BAND_TILES of it.
BAND_VIEW = (1440, 114)
# HOW MANY TIMES THE MOTIF REPEATS ACROSS THE VIEWBOX
# The band used to be ONE copy of the drawing stretched to whatever width it
# got -- preserveAspectRatio="none" -- so its horizontal and vertical scales
# agreed at exactly one viewport, 1920px, which is what the artwork was
# composed for, and disagreed everywhere else: 2.6x squashed at 768, 1.4x at
# 1440, 2.2x stretched at 3840 and 10x on a 4K screen zoomed out to 25%. Pads
# came out as ovals and vias as ellipses. It was reported from a zoomed-out
# browser as stretched and old, and it was.
#
# So the band is "xMidYMid slice" over a viewBox this many tiles wide. slice
# scales UNIFORMLY and crops; with a viewBox this wide the HEIGHT decides the
# scale and the width never does, which is one constant scale and one constant
# trace pitch at every viewport and every zoom level.
#
# Fifteen covers a 5K display at Chrome's minimum 25% zoom, which needs 13.5.
# Being wrong about this number is safe in a way the old arrangement never was:
# slice is uniform by definition, so if the width ever does govern, the drawing
# MAGNIFIES. It cannot distort again. That property is what this buys.
#
# Measured: the band fits inside ONE tile at every width up to 1920px, so on an
# ordinary screen nothing repeats at all. It first repeats near 2048px.
#
# ODD ON PURPOSE. Each tile mirrors about its own centre, so an odd count puts
# a mirror axis on the viewBox's centre line and the banner's middle is still
# where the two halves meet.
BAND_TILES = 15

# Three traces per corner and three per band half, which is 24 charges once the
# mirrors are counted. That number is a budget, not a coincidence: a charge
# costs about 1.1ms of style recalculation per second for as long as the page is
# open, tools/test_motion.py asserts exactly 24, and the table in
# docs/10-development/frontend/motion.md is what to read before raising it.
CHARGES_PER_SET = 3
NODES_PER_LAYER = 4
NODE_RADIUS = 3.6

CORNER_LAYERS = ("corner-tl", "corner-tr", "corner-bl", "corner-br")
NODE_NAMES = "abcdefghijklmnopqrstuvwxyz"


# ---------------------------------------------------------------------------
# --resolve: read the reference through a browser
# ---------------------------------------------------------------------------

# Every shape in the six groups, with the matrix that puts it in root user
# space and the four corners of whatever clips it. getScreenCTM() is the only
# thing here that has to be a browser: it composes the element's own transform
# with every transform above it, and a clipPath's <use> chain has to be walked
# and composed the same way.
RESOLVE_JS = r"""
const svg = document.querySelector('svg');
const rootInv = svg.getScreenCTM().inverse();
const M = el => rootInv.multiply(el.getScreenCTM());
const m6 = m => [m.a, m.b, m.c, m.d, m.e, m.f];

function matrixOf(t) {
  if (!t) { return svg.createSVGMatrix(); }
  const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
  g.setAttribute('transform', t);
  svg.appendChild(g);
  const c = g.transform.baseVal.consolidate();
  const m = c ? c.matrix : svg.createSVGMatrix();
  svg.removeChild(g);
  return m;
}

/* The clip, in the element's own user space: a rect, possibly behind a chain
   of <use>, each link of which may carry a transform of its own. */
function clipQuad(el) {
  const ref = (el.getAttribute('clip-path') || '').match(/url\(#([^)]+)\)/);
  if (!ref) { return null; }
  const node = document.getElementById(ref[1]);
  if (!node) { return {error: 'clip ' + ref[1] + ' not found'}; }
  let shape = node.firstElementChild;
  let acc = svg.createSVGMatrix();
  for (let hop = 0; shape && shape.tagName.toLowerCase() === 'use'; hop += 1) {
    if (hop > 8) { return {error: 'clip <use> chain too deep'}; }
    acc = acc.multiply(matrixOf(shape.getAttribute('transform')));
    const href = (shape.getAttribute('href') ||
                  shape.getAttribute('xlink:href') || '').slice(1);
    shape = document.getElementById(href);
    if (!shape) { return {error: 'clip <use> target ' + href + ' missing'}; }
  }
  if (!shape || shape.tagName.toLowerCase() !== 'rect') {
    return {error: 'clip is a ' + (shape ? shape.tagName : 'nothing') + ', not a rect'};
  }
  const x = parseFloat(shape.getAttribute('x'));
  const y = parseFloat(shape.getAttribute('y'));
  const w = parseFloat(shape.getAttribute('width'));
  const h = parseFloat(shape.getAttribute('height'));
  const full = acc.multiply(matrixOf(shape.getAttribute('transform')));
  return {quad: [[x, y], [x + w, y], [x + w, y + h], [x, y + h]].map(p => {
    const q = svg.createSVGPoint();
    q.x = p[0]; q.y = p[1];
    const r = q.matrixTransform(full);
    return [r.x, r.y];
  })};
}

function pointsOf(el) {
  const tag = el.tagName.toLowerCase();
  if (tag === 'polyline') {
    const raw = (el.getAttribute('points') || '').trim().split(/[\s,]+/).map(Number);
    const out = [];
    for (let i = 0; i + 1 < raw.length; i += 2) { out.push([raw[i], raw[i + 1]]); }
    return out;
  }
  if (tag === 'line') {
    return [[+el.getAttribute('x1'), +el.getAttribute('y1')],
            [+el.getAttribute('x2'), +el.getAttribute('y2')]];
  }
  const total = el.getTotalLength();
  if (!total) { return []; }
  /* Fine enough that a pad's outline comes back as a circle rather than a
     polygon; these are two or three units across in the source. */
  const steps = Math.max(2, Math.ceil(total / 0.15));
  const out = [];
  for (let i = 0; i <= steps; i += 1) {
    const p = el.getPointAtLength(total * i / steps);
    out.push([p.x, p.y]);
  }
  return out;
}

const groups = {};
const errors = [];
__GROUPS__.forEach(id => {
  const g = document.getElementById(id);
  if (!g) { errors.push('group ' + id + ' is not in the file'); return; }
  const items = [];
  g.querySelectorAll('polyline,path,line,rect,circle,ellipse').forEach(el => {
    const tag = el.tagName.toLowerCase();
    const style = el.getAttribute('style') || '';
    const pick = name => (style.match(new RegExp(name + ':\\s*([^;]+)')) || [, ''])[1].trim();
    const rec = {tag: tag, m: m6(M(el)),
                 fill: pick('fill'), stroke: pick('stroke')};
    if (tag === 'rect') {
      rec.rect = [+el.getAttribute('x'), +el.getAttribute('y'),
                  +el.getAttribute('width'), +el.getAttribute('height')];
    } else if (tag === 'circle') {
      rec.round = [+el.getAttribute('cx'), +el.getAttribute('cy'),
                   +el.getAttribute('r'), +el.getAttribute('r')];
    } else if (tag === 'ellipse') {
      rec.round = [+el.getAttribute('cx'), +el.getAttribute('cy'),
                   +el.getAttribute('rx'), +el.getAttribute('ry')];
    } else {
      rec.pts = pointsOf(el);
    }
    const clip = clipQuad(el);
    if (clip && clip.error) { errors.push(tag + ' in ' + id + ': ' + clip.error); }
    rec.clip = clip && clip.quad ? clip.quad : null;
    items.push(rec);
  });
  groups[id] = items;
});

const out = document.createElement('pre');
out.id = 'resolved';
out.textContent = JSON.stringify({groups: groups, errors: errors});
document.body.appendChild(out);
"""


def chrome() -> str:
    for name in ("google-chrome", "chromium", "chromium-browser", "google-chrome-stable"):
        found = shutil.which(name)
        if found:
            return found
    raise SystemExit(
        "--resolve needs Chrome to read the reference SVG, and none was found.\n"
        "Install one, or leave the committed geometry alone: everything except\n"
        "--resolve works without a browser."
    )


def resolve() -> dict:
    """Read the reference through a browser and return the raw shapes."""
    if not SOURCE.is_file():
        raise SystemExit(f"the reference artwork is missing: {SOURCE}")

    svg = SOURCE.read_text()
    svg = re.sub(r"<\?xml[^>]*\?>", "", svg)
    svg = re.sub(r"<!--.*?-->", "", svg, flags=re.S)
    js = RESOLVE_JS.replace("__GROUPS__", json.dumps([CORNER_GROUP, BAND_GROUP]))
    page = f"<!doctype html><meta charset=utf-8><body>{svg}<script>{js}</script></body>"

    with tempfile.TemporaryDirectory() as tmp:
        path = Path(tmp) / "resolve.html"
        path.write_text(page)
        result = subprocess.run(
            [chrome(), "--headless=new", "--disable-gpu", "--no-sandbox",
             "--virtual-time-budget=30000", "--dump-dom", f"file://{path}"],
            capture_output=True, text=True,
        )
    found = re.search(r'<pre id="resolved">(.*?)</pre>', result.stdout, re.S)
    if not found:
        raise SystemExit(
            "the browser did not return the geometry. Chrome said:\n"
            + (result.stderr.strip()[-1500:] or "(nothing)")
        )
    import html as html_module
    data = json.loads(html_module.unescape(found.group(1)))
    if data["errors"]:
        raise SystemExit("the reference did not resolve cleanly:\n  "
                         + "\n  ".join(data["errors"][:20]))
    return data["groups"]


# ---------------------------------------------------------------------------
# geometry: clip, classify, reduce
# ---------------------------------------------------------------------------

def transform(m, p):
    a, b, c, d, e, f = m
    return (a * p[0] + c * p[1] + e, b * p[0] + d * p[1] + f)


def scale_of(m):
    a, b, c, d, _, _ = m
    return math.sqrt(abs(a * d - b * c))


def wound(quad):
    """The quad with a known winding, so 'inside' has one meaning below."""
    area = sum(quad[i][0] * quad[(i + 1) % 4][1] - quad[(i + 1) % 4][0] * quad[i][1]
               for i in range(4))
    return quad if area > 0 else quad[::-1]


def edges_of(quad):
    q = wound(quad)
    return [(q[i], (-(q[(i + 1) % 4][1] - q[i][1]), q[(i + 1) % 4][0] - q[i][0]))
            for i in range(4)]


def inside(quad, point) -> bool:
    return all(n[0] * (point[0] - a[0]) + n[1] * (point[1] - a[1]) >= -1e-9
               for a, n in edges_of(quad))


def clip_polyline(pts, quad):
    """Clip a polyline to a convex quad, returning the pieces that survive.

    Liang-Barsky per segment. A trace that leaves the clip and comes back is two
    pieces, not one -- joining them would draw a line through the gap, and the
    gap is the point.
    """
    if quad is None:
        return [pts]
    edges = edges_of(quad)
    pieces, current = [], []
    for i in range(len(pts) - 1):
        p, q = pts[i], pts[i + 1]
        dx, dy = q[0] - p[0], q[1] - p[1]
        near, far, drop = 0.0, 1.0, False
        for a, n in edges:
            offset = n[0] * (p[0] - a[0]) + n[1] * (p[1] - a[1])
            along = n[0] * dx + n[1] * dy
            if abs(along) < 1e-12:
                if offset < -1e-9:
                    drop = True
                    break
                continue
            t = -offset / along
            if along > 0:
                near = max(near, t)
            else:
                far = min(far, t)
            if near > far:
                drop = True
                break
        if drop or far - near < 1e-9:
            if current:
                pieces.append(current)
                current = []
            continue
        start = (p[0] + dx * near, p[1] + dy * near)
        end = (p[0] + dx * far, p[1] + dy * far)
        if not current:
            current = [start, end]
        elif math.dist(current[-1], start) > 1e-7:
            pieces.append(current)
            current = [start, end]
        else:
            current.append(end)
        if far < 1 - 1e-9:
            pieces.append(current)
            current = []
    if current:
        pieces.append(current)
    return [piece for piece in pieces if len(piece) > 1]


def classify(items):
    """Sort one group's shapes into traces, pads and rings, in root user space."""
    traces, pads, rings = [], [], []
    for item in items:
        m = item["m"]
        quad = [transform(m, p) for p in item["clip"]] if item["clip"] else None
        filled = item["fill"] not in ("none", "")

        if item["tag"] == "rect":
            x, y, w, h = item["rect"]
            corners = [transform(m, p) for p in
                       ((x, y), (x + w, y), (x + w, y + h), (x, y + h))]
            centre = (sum(p[0] for p in corners) / 4, sum(p[1] for p in corners) / 4)
            if quad is None or inside(quad, centre):
                pads.append({"kind": "mark", "points": corners})
            continue

        if item["tag"] in ("circle", "ellipse"):
            cx, cy, rx, ry = item["round"]
            centre = transform(m, (cx, cy))
            radius = (rx + ry) / 2 * scale_of(m)
            if quad is None or inside(quad, centre):
                target = pads if filled else rings
                target.append({"kind": "dot", "at": centre, "r": radius})
            continue

        pts = [transform(m, p) for p in item["pts"]]
        if not pts:
            continue
        xs = [p[0] for p in pts]
        ys = [p[1] for p in pts]
        span = max(max(xs) - min(xs), max(ys) - min(ys))
        closed = math.dist(pts[0], pts[-1]) < 0.05 * max(span, 1e-6)
        centre = (sum(xs) / len(xs), sum(ys) / len(ys))

        # A via is drawn as a closed bezier loop a couple of units across; a
        # trace is an open run tens of units long. Nothing in the reference
        # sits between the two, which is why a span test is enough.
        if item["tag"] == "path" and closed and span < 6.0:
            if quad is None or inside(quad, centre):
                target = pads if filled else rings
                target.append({"kind": "dot", "at": centre,
                               "r": sum(math.dist(centre, p) for p in pts) / len(pts)})
            continue
        if filled:
            if quad is None or inside(quad, centre):
                pads.append({"kind": "mark", "points": pts})
            continue
        traces.extend(clip_polyline(pts, quad))

    return {"traces": traces, "pads": pads, "rings": rings}


def orient(traces, name):
    """Turn every trace so it runs from its own outer edge inward.

    Inkscape stores a polyline in whatever order it was drawn, and the reference
    is about two to one against us: eighteen of the band's twenty-six ran from
    the pad back up to the edge. That is invisible in the still drawing and very
    visible once a charge is on it, because circuit.js and the CSS fallback both
    take a trace's direction as given and decide only whether to reverse it.
    Left alone, a third of the band would carry its charge upstream and the two
    bands would stop reading as one current going round.

    "Outer edge" is the top for a band, whose traces drop from it, and the
    corner itself for a cluster, whose traces fan out of it.
    """
    key = (lambda p: p[1]) if name == "band" else (lambda p: p[0] + p[1])
    return [t if key(t[0]) <= key(t[-1]) else t[::-1] for t in traces]


def reduce_source(groups: dict) -> dict:
    out = {}
    for name, gid in (("corner", CORNER_GROUP), ("band", BAND_GROUP)):
        shape = classify(groups[gid])
        out[name] = {
            "traces": [[[round(v, 4) for v in p] for p in t]
                       for t in orient(shape["traces"], name)],
            "pads": [
                {"kind": "dot", "at": [round(v, 4) for v in p["at"]], "r": round(p["r"], 4)}
                if p["kind"] == "dot" else
                {"kind": "mark", "points": [[round(v, 4) for v in q] for q in p["points"]]}
                for p in shape["pads"]
            ],
            "rings": [{"at": [round(v, 4) for v in r["at"]], "r": round(r["r"], 4)}
                      for r in shape["rings"]],
        }
    return out


HEADER = """<!--hero-circuit:start-->
    <!-- The circuitry around the page title. GENERATED - do not edit this file
         or the copy of it in any page. It is extracted from the company's own
         banner artwork, references/t4t_circuitry_6000_2031_300.svg, by
         tools/build_hero_circuit.py, and tools/propagate_shared.py carries it
         out to every page from here.

         It was drawn by hand once, from a description of that artwork. It is
         no longer a description: every trace, pad and via below is the real
         drawing, clipped and fitted, and the numbers are arithmetic anybody can
         redo rather than an afternoon nobody can.

         aria-hidden, and inside the band but behind it: this is texture around
         the title, and it says nothing.

         SIX LAYERS, ONE SET OF GEOMETRY
         Everything is declared once, in the first layer\'s <defs>. SVG ids are
         document-scoped, so the other five reference the same paths and are
         mirrored in CSS. That is not tidiness - a duplicate id is a hard
         failure in audit_pages.py, so four corners cannot each carry a copy.

         THE BAND TILES; IT IS NOT STRETCHED. IT USED TO BE.
         The bands used preserveAspectRatio="none", on the reasoning that a band
         runs a fixed height across a box whose width is the screen\'s and
         stretching a horizontal run only makes it a longer run. That is true of
         a run and false of the drawing around it: pads turn to ovals, vias to
         ellipses, every vertical trace thins and every horizontal one thickens.
         Measured, the two scales agreed at exactly ONE viewport -- 1920px, the
         width the artwork was composed for -- and disagreed everywhere else.

         So the viewBox is BAND_TILES tiles wide and the fit is xMidYMid slice,
         which scales UNIFORMLY and crops rather than distorting. The height
         decides the scale, the width never does, and the trace pitch is the
         same at 768px as at 4K. The corners have always used xMinYMin meet, for
         the same reason turned the other way: a fan of 45 degree elbows must
         not shear, and it must stay pinned to its own corner.

         THE BAND IS A HALF, MIRRORED -- AND THAT IS WHAT A TILE IS
         The reference\'s band is one run about six times as wide as it is tall.
         So the run fills the left half of a 1440-unit tile and the right half is
         its reflection: every pad stays circular and every mark keeps its drawn
         size. Each tile mirrors about its own centre, and BAND_TILES is ODD so
         the viewBox\'s centre line is a mirror axis too -- the banner\'s middle
         is still where the two halves meet.
         hero-circuit__charge--mirrored puts them back in step, and circuit.js
         places each band trace twice per visible tile for the same reason.

         A CHARGE IS ONE <use>, AND NEVER A GROUP OF THEM
         This layer once carried the charge on a <g> wrapping a <use> of a
         *group* of traces, on the reasoning that forty animated elements must
         beat two hundred. That reasoning was wrong, and measurably so.
         stroke-dashoffset is an inherited property: animating it on a group
         makes the browser push the new value down through every <use> shadow
         tree beneath it, every frame. Lighthouse put the page\'s Style & Layout
         work at 4,683ms against 686ms before it, and the site was reported as
         struggling.

         So: the charge goes directly on the <use> that draws the trace, and it
         is deliberately not on every trace. The density here is the STATIC
         drawing, which costs one rasterisation; movement is the expensive part
         and is spent sparingly - three traces in each cluster and three in each
         band half, 24 against 176 drawn. With scripting, circuit.js paints all
         176 on one canvas and switches these off; these are the fallback, and
         the fallback is what the budget is spent on.

         The cost is close to linear in that number: about 1.1ms of style
         recalculation per second per charge, on top of a floor that is the
         static drawing. If you raise it, measure - tools/check_style_budget.py,
         and read the table in docs/10-development/frontend/motion.md first.

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
         directions, because they are one current going round. -->"""


# ---------------------------------------------------------------------------
# fit and emit
# ---------------------------------------------------------------------------

def num(value: float) -> str:
    return f"{value:.1f}".rstrip("0").rstrip(".") or "0"


def fit(shape: dict, view: tuple[int, int]) -> dict:
    """Scale one set into its viewBox, uniformly, against what it actually draws.

    Against the DRAWN extent, not the source page: the reference bleeds past its
    own edge on every side, and fitting to the page would leave a margin the
    artwork was composed not to have.
    """
    xs, ys = [], []
    for trace in shape["traces"]:
        for x, y in trace:
            xs.append(x)
            ys.append(y)
    for pad in shape["pads"]:
        if pad["kind"] == "dot":
            xs += [pad["at"][0] - pad["r"], pad["at"][0] + pad["r"]]
            ys += [pad["at"][1] - pad["r"], pad["at"][1] + pad["r"]]
        else:
            for x, y in pad["points"]:
                xs.append(x)
                ys.append(y)
    for ring in shape["rings"]:
        xs += [ring["at"][0] - ring["r"], ring["at"][0] + ring["r"]]
        ys += [ring["at"][1] - ring["r"], ring["at"][1] + ring["r"]]

    x0, y0, x1, y1 = min(xs), min(ys), max(xs), max(ys)
    scale = min(view[0] / (x1 - x0), view[1] / (y1 - y0))
    place = lambda p: ((p[0] - x0) * scale, (p[1] - y0) * scale)  # noqa: E731

    traces = ["M" + " L".join(f"{num(x)} {num(y)}" for x, y in map(place, t))
              for t in shape["traces"]]
    pads, dots = [], []
    for pad in shape["pads"]:
        if pad["kind"] == "dot":
            at = place(pad["at"])
            pads.append(f'<circle cx="{num(at[0])}" cy="{num(at[1])}" '
                        f'r="{num(pad["r"] * scale)}"/>')
            dots.append(at)
        else:
            corners = " L".join(f"{num(x)} {num(y)}" for x, y in map(place, pad["points"]))
            pads.append(f'<path d="M{corners}Z"/>')
    rings = [f'<circle cx="{num(place(r["at"])[0])}" cy="{num(place(r["at"])[1])}" '
             f'r="{num(r["r"] * scale)}"/>' for r in shape["rings"]]
    return {"traces": traces, "pads": pads, "rings": rings, "dots": dots,
            "drawn": ((x1 - x0) * scale, (y1 - y0) * scale)}


def spread(items, count, key):
    """Pick `count` items spaced evenly through the set, ordered by `key`.

    Evenly rather than at random, and never adjacent: two lit traces side by
    side read as one thick line instead of as two charges.
    """
    order = sorted(range(len(items)), key=lambda i: key(items[i]))
    if count >= len(order):
        return order
    step = len(order) / count
    return sorted(order[int((n + 0.5) * step)] for n in range(count))


def start_of(path: str) -> tuple[float, float]:
    head = path[1:].split(" L")[0].split(" ")
    return (float(head[0]), float(head[1]))


def emit(geometry: dict) -> str:
    corner = fit(geometry["corner"], CORNER_VIEW)
    band = fit(geometry["band"], (BAND_VIEW[0] // 2, BAND_VIEW[1]))
    full_w = BAND_VIEW[0]

    # Which traces are lit. The band's are read left to right and the corner's
    # outward from the corner itself, so "spread" means spread across the thing
    # a visitor is looking at rather than across the order they were drawn in.
    band_lit = spread(band["traces"], CHARGES_PER_SET, lambda p: start_of(p)[0])
    corner_lit = spread(corner["traces"], CHARGES_PER_SET,
                        lambda p: sum(start_of(p)))

    # The junctions. Taken from the round pads, which are where traces actually
    # end, so a pulsing dot sits on a join rather than in the middle of a run.
    corner_nodes = [corner["dots"][i] for i in
                    spread(corner["dots"], NODES_PER_LAYER, lambda p: p[0] + p[1])]
    half_nodes = [band["dots"][i] for i in
                  spread(band["dots"], NODES_PER_LAYER // 2, lambda p: p[0])]
    band_nodes = half_nodes + [(full_w - x, y) for x, y in half_nodes]

    out = [HEADER, '    <div class="hero-circuit" aria-hidden="true">']
    names = iter(NODE_NAMES)

    def nodes(points):
        return "".join(
            f'\n          <circle class="hero-circuit__node '
            f'hero-circuit__node--{next(names)}" cx="{num(x)}" cy="{num(y)}" '
            f'r="{NODE_RADIUS}"/>' for x, y in points)

    defs = []
    for i, path in enumerate(band["traces"]):
        defs.append(f'<path id="hc-b{i}" pathLength="100" d="{path}"/>')
    defs.append('<g id="hc-band-half">'
                + "".join(f'<use href="#hc-b{i}"/>' for i in range(len(band["traces"])))
                + "</g>")
    mirror = f'transform="translate({full_w},0) scale(-1,1)"'
    defs.append(f'<g id="hc-band-wires"><use href="#hc-band-half"/>'
                f'<use href="#hc-band-half" {mirror}/></g>')
    defs.append('<g id="hc-band-pads-half">' + "".join(band["pads"]) + "</g>")
    defs.append(f'<g id="hc-band-pads"><use href="#hc-band-pads-half"/>'
                f'<use href="#hc-band-pads-half" {mirror}/></g>')
    defs.append('<g id="hc-band-rings-half">' + "".join(band["rings"]) + "</g>")
    defs.append(f'<g id="hc-band-rings"><use href="#hc-band-rings-half"/>'
                f'<use href="#hc-band-rings-half" {mirror}/></g>')
    for i, path in enumerate(corner["traces"]):
        defs.append(f'<path id="hc-c{i}" pathLength="100" d="{path}"/>')
    defs.append('<g id="hc-corner-wires">'
                + "".join(f'<use href="#hc-c{i}"/>' for i in range(len(corner["traces"])))
                + "</g>")
    defs.append('<g id="hc-corner-pads">' + "".join(corner["pads"]) + "</g>")
    defs.append('<g id="hc-corner-rings">' + "".join(corner["rings"]) + "</g>")

    band_charges = "".join(
        f'\n          <use class="hero-circuit__charge hero-circuit__charge--band '
        f'hero-circuit__charge--p{n + 1}" href="#hc-b{i}"/>'
        for n, i in enumerate(band_lit))
    band_mirrored = "".join(
        f'\n            <use class="hero-circuit__charge hero-circuit__charge--band '
        f'hero-circuit__charge--mirrored hero-circuit__charge--p{n + 1}" href="#hc-b{i}"/>'
        for n, i in enumerate(band_lit))
    # --back reverses the middle one, so neighbouring lit traces in a cluster
    # run against each other rather than in convoy.
    corner_charges = "".join(
        f'\n          <use class="hero-circuit__charge hero-circuit__charge--c{n + 1}'
        f'{" hero-circuit__charge--back" if n == 1 else ""}" href="#hc-c{i}"/>'
        for n, i in enumerate(corner_lit))

    # The tiled static drawing: one <use> of each group per tile, all of them
    # referencing defs declared once. No id is duplicated, which audit_pages.py
    # treats as a hard failure.
    def tiled(group: str) -> str:
        return "".join(
            f'<use href="#{group}"/>' if k == 0 else
            f'<use href="#{group}" transform="translate({k * BAND_VIEW[0]},0)"/>'
            for k in range(BAND_TILES))

    # THE CHARGES AND THE NODES ARE NOT TILED, AND THAT IS DELIBERATE
    # Fifteen copies would be ninety animated elements per band reached through
    # a <use> of a group -- exactly the shape that put style recalculation at
    # 895ms per second on 2026-09-03 and had the site reported as struggling.
    # stroke-dashoffset is inherited, so animating it under a <use>d group makes
    # the browser push the value down through every shadow tree beneath it,
    # every frame. They are emitted once, on the centre tile.
    #
    # Without scripting on a wide screen that means charges in the middle of the
    # band only. That is the fallback and it reads correctly; with scripting,
    # circuit.js paints a charge on every visible tile.
    centre = (BAND_TILES // 2) * BAND_VIEW[0]

    for which in ("band-top", "band-bottom"):
        first = which == "band-top"
        out.append(
            f'      <svg class="hero-circuit__layer hero-circuit__layer--{which}" '
            f'viewBox="0 0 {BAND_VIEW[0] * BAND_TILES} {BAND_VIEW[1]}" '
            f'preserveAspectRatio="xMidYMid slice" focusable="false">')
        if first:
            out.append("        <defs>")
            out.extend("        " + line for line in defs)
            out.append("        </defs>")
        out.append('        <g class="hero-circuit__wires">' + tiled("hc-band-wires") + "</g>")
        out.append('        <g class="hero-circuit__pads">' + tiled("hc-band-pads") + "</g>")
        out.append('        <g class="hero-circuit__rings">' + tiled("hc-band-rings") + "</g>")
        out.append(f'        <g class="hero-circuit__charges" transform="translate({centre},0)">'
                   + band_charges)
        out.append(f'          <g {mirror}>' + band_mirrored)
        out.append("          </g>")
        out.append("        </g>")
        out.append(f'        <g class="hero-circuit__nodes" transform="translate({centre},0)">'
                   + nodes(band_nodes))
        out.append("        </g>")
        out.append("      </svg>")

    for which in CORNER_LAYERS:
        out.append(
            f'      <svg class="hero-circuit__layer hero-circuit__layer--{which}" '
            f'viewBox="0 0 {CORNER_VIEW[0]} {CORNER_VIEW[1]}" '
            f'preserveAspectRatio="xMinYMin meet" focusable="false">')
        out.append('        <g class="hero-circuit__wires"><use href="#hc-corner-wires"/></g>')
        out.append('        <g class="hero-circuit__pads"><use href="#hc-corner-pads"/></g>')
        out.append('        <g class="hero-circuit__rings"><use href="#hc-corner-rings"/></g>')
        out.append('        <g class="hero-circuit__charges">' + corner_charges)
        out.append("        </g>")
        out.append('        <g class="hero-circuit__nodes">' + nodes(corner_nodes))
        out.append("        </g>")
        out.append("      </svg>")

    out.append("    </div>")
    out.append("<!--hero-circuit:end-->")
    return "\n".join(out) + "\n"


# ---------------------------------------------------------------------------
# the command
# ---------------------------------------------------------------------------

def load_geometry() -> dict:
    if not GEOMETRY.is_file():
        raise SystemExit(
            f"{GEOMETRY.relative_to(ROOT)} is missing, so there is nothing to draw\n"
            "from. Re-read the reference with --resolve (needs Chrome)."
        )
    return json.loads(GEOMETRY.read_text())


def summarise(geometry: dict) -> None:
    for name, view in (("corner", CORNER_VIEW),
                       ("band", (BAND_VIEW[0] // 2, BAND_VIEW[1]))):
        shape = geometry[name]
        placed = fit(shape, view)
        print(f"  {name:<7} {len(shape['traces']):>3} traces  "
              f"{len(shape['pads']):>3} pads  {len(shape['rings']):>2} rings   "
              f"drawn {placed['drawn'][0]:.1f} x {placed['drawn'][1]:.1f} "
              f"in {view[0]} x {view[1]}")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--resolve", action="store_true",
                        help="re-read the reference SVG through Chrome and rewrite "
                             "the committed geometry")
    parser.add_argument("--check", action="store_true",
                        help="assert the template matches the geometry; write nothing")
    args = parser.parse_args()

    if args.resolve:
        print(f"reading {SOURCE.relative_to(ROOT)} through a browser ...")
        geometry = reduce_source(resolve())
        GEOMETRY.write_text(json.dumps(geometry, indent=1, sort_keys=True) + "\n")
        print(f"wrote {GEOMETRY.relative_to(ROOT)}")
        summarise(geometry)

    geometry = load_geometry()
    drawn = emit(geometry)

    if args.check:
        if not TEMPLATE.is_file():
            raise SystemExit(f"{TEMPLATE.relative_to(ROOT)} is missing")
        current = TEMPLATE.read_text()
        if current == drawn:
            print(f"{TEMPLATE.relative_to(ROOT)} is current.")
            summarise(geometry)
            return
        # Say WHERE, not just that. A template edited by hand and a geometry
        # re-resolved against a different reference fail the same way otherwise.
        old, new = current.splitlines(), drawn.splitlines()
        for n, (a, b) in enumerate(zip(old, new), 1):
            if a != b:
                print(f"{TEMPLATE.relative_to(ROOT)} is out of step at line {n}:")
                print(f"  on disk:    {a[:110]}")
                print(f"  generated:  {b[:110]}")
                break
        else:
            print(f"{TEMPLATE.relative_to(ROOT)} is out of step: "
                  f"{len(old)} lines on disk against {len(new)} generated")
        raise SystemExit(
            "\nThe drawing in the template is not the one this geometry makes.\n"
            "Run: python3 tools/build_hero_circuit.py && python3 tools/propagate_shared.py"
        )

    TEMPLATE.write_text(drawn)
    print(f"wrote {TEMPLATE.relative_to(ROOT)} ({len(drawn):,} bytes)")
    summarise(geometry)
    print("\nNow: python3 tools/propagate_shared.py")


if __name__ == "__main__":
    main()
