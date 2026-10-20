/* ===================================================================
   ADA Law Chambers — motion.js
   Scroll reveals, count-up stats, scroll progress, floating GST
   symbols that react to the cursor, and a GST terms ticker.
   Self-contained: include it (plus css/motion.css) on any page.
=================================================================== */
(function () {
  "use strict";

  var reduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var canObserve = "IntersectionObserver" in window;

  /* ---------------------------------------------------------------
     1. SCROLL REVEAL
     Elements get .reveal (hidden), then .in when they enter view.
     After the animation the classes are removed so the site's own
     hover transforms keep working.
  --------------------------------------------------------------- */
  var REVEAL_SELECTORS = [
    ".section-head", ".section-title", ".eyebrow", ".divider-gold",
    ".grid > *", ".card", ".stat", ".team-card", ".post-card", ".bio-block",
    ".pull-quote", ".cta-band .container > *", ".diagram-node", ".map-embed",
    ".section .container > p", ".section .container > ul", ".section .container > h2",
    ".section .container > h3", ".footer-col", ".footer-brand",
    ".hero .container > div:not(.hero-cta)"
  ].join(",");

  var revealObserver = canObserve ? new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var el = entry.target;
      revealObserver.unobserve(el);
      el.classList.add("in");
      var done = function () { el.classList.remove("reveal", "reveal--fade", "in"); el.style.removeProperty("--reveal-delay"); };
      el.addEventListener("animationend", done, { once: true });
      setTimeout(done, 1800); // safety net
    });
  }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" }) : null;

  function prepareReveals(root) {
    if (reduced || !revealObserver) return;
    var nodes = (root || document).querySelectorAll(REVEAL_SELECTORS);
    nodes.forEach(function (el) {
      if (el.dataset.revealed || el.closest(".disclaimer-overlay, .site-header, .gst-ticker, .floating-contact, .stats-band, .px-why.is-armed")) return; // .stats-band: js/stats-band.js; .px-why: initWhy
      // Skip children of an element already being revealed (avoid double fades)
      var p = el.parentElement;
      while (p) { if (p.classList && p.classList.contains("reveal")) return; p = p.parentElement; }
      el.dataset.revealed = "1";
      // Stagger siblings inside the same grid/row
      var idx = 0;
      if (el.parentElement) idx = Array.prototype.indexOf.call(el.parentElement.children, el);
      el.style.setProperty("--reveal-delay", Math.min(idx, 8) * 90 + "ms");
      el.classList.add("reveal");
      if (el.classList.contains("divider-gold") || el.classList.contains("eyebrow")) el.classList.add("reveal--fade");
      revealObserver.observe(el);
    });
  }

  /* ---------------------------------------------------------------
     2. COUNT-UP STATS  ("20+" counts 0 → 20, keeps the "+")
  --------------------------------------------------------------- */
  function initCountUp() {
    if (reduced || !canObserve) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        io.unobserve(entry.target);
        var el = entry.target, m = el.dataset.count.match(/^(\d+)(.*)$/);
        if (!m) return;
        var end = parseInt(m[1], 10), suffix = m[2], t0 = performance.now(), dur = 1400;
        (function tick(t) {
          var p = Math.min(1, (t - t0) / dur), eased = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(end * eased) + suffix;
          if (p < 1) requestAnimationFrame(tick); else el.textContent = m[1] + suffix;
        })(t0);
      });
    }, { threshold: 0.6 });
    document.querySelectorAll(".stat b").forEach(function (b) {
      if (b.closest(".stats-band")) return; // has its own entrance (js/stats-band.js)
      var txt = b.textContent.trim();
      if (!/^\d+\D{0,2}$/.test(txt)) return; // only plain numbers like 20+, 10+, 2
      b.dataset.count = txt;
      b.textContent = "0" + txt.replace(/^\d+/, "");
      io.observe(b);
    });
  }

  /* ---------------------------------------------------------------
     3. SCROLL PROGRESS BAR
  --------------------------------------------------------------- */
  function initProgress() {
    if (reduced) return;
    var bar = document.createElement("div");
    bar.className = "scroll-progress";
    bar.setAttribute("aria-hidden", "true");
    document.body.appendChild(bar);
    var ticking = false;
    function update() {
      var h = document.documentElement.scrollHeight - window.innerHeight;
      bar.style.transform = "scaleX(" + (h > 0 ? Math.min(1, window.scrollY / h) : 0) + ")";
      ticking = false;
    }
    window.addEventListener("scroll", function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    update();
  }

  /* ---------------------------------------------------------------
     4. FLOATING GST SYMBOLS
     Drift continuously (CSS) and are nudged away from the cursor (JS).
  --------------------------------------------------------------- */
  // Practice words are mixed in between the symbols, and each section starts
  // at a random point in this list, so every word appears across the site.
  var SYMBOLS = [
    { t: "₹", s: 3.2 }, { t: "Appeal", s: 1.7 }, { t: "GST", s: 2.6, mono: 1 }, { t: "Litigation", s: 1.6 },
    { t: "18%", s: 2.4 }, { t: "GSTAT", s: 2.4, mono: 1 }, { t: "ITC", s: 2.4, mono: 1 }, { t: "High Court", s: 1.6 },
    { t: "§", s: 3 }, { t: "GST Consultancy", s: 1.4 }, { t: "IGST", s: 2.6, mono: 1 }, { t: "Supreme Court", s: 1.5 },
    { t: "5%", s: 2.2 }, { t: "HSN", s: 2.4, mono: 1 }, { t: "Appeal", s: 1.5 }, { t: "CGST", s: 2.6, mono: 1 },
    { t: "Litigation", s: 1.4 }, { t: "SGST", s: 2.6, mono: 1 }, { t: "40%", s: 2.2 }, { t: "%", s: 2 }
  ];
  var floaterSets = [];

  function rand(a, b) { return a + Math.random() * (b - a); }

  function addFloaters(host, count, fade) {
    fade = fade || 1;
    if (reduced || !host || host.querySelector(":scope > .gst-floaters")) return;
    host.classList.add("has-floaters");
    var layer = document.createElement("div");
    layer.className = "gst-floaters";
    layer.setAttribute("aria-hidden", "true");
    var items = [];
    var start = Math.floor(Math.random() * SYMBOLS.length);
    for (var i = 0; i < count; i++) {
      var sym = SYMBOLS[(start + i) % SYMBOLS.length];
      var w = document.createElement("div");
      w.className = "gst-float" + (sym.mono ? " mono" : "");
      // Longer words start further left so they aren't cut off at the edge.
      w.style.left = rand(2, sym.t.length > 6 ? 72 : 94).toFixed(1) + "%";
      w.style.top = rand(4, 86).toFixed(1) + "%";
      var s = document.createElement("span");
      s.textContent = sym.t;
      s.style.setProperty("--size", sym.s + "rem");
      s.style.setProperty("--alpha", (rand(0.1, 0.2) * fade).toFixed(2));
      s.style.setProperty("--dur", rand(10, 20).toFixed(1) + "s");
      s.style.setProperty("--delay", (-rand(0, 14)).toFixed(1) + "s");
      w.appendChild(s);
      layer.appendChild(w);
      items.push(w);
    }
    host.insertBefore(layer, host.firstChild);
    floaterSets.push({ host: host, layer: layer, items: items });
    if (canObserve) {
      new IntersectionObserver(function (e) {
        layer.classList.toggle("paused", !e[0].isIntersecting);
      }).observe(host);
    }
  }

  var pointer = null, pointerTick = false;
  function onPointer(e) {
    pointer = { x: e.clientX, y: e.clientY };
    if (pointerTick) return;
    pointerTick = true;
    requestAnimationFrame(function () {
      pointerTick = false;
      if (!pointer) return;
      floaterSets.forEach(function (set) {
        var r = set.host.getBoundingClientRect();
        var inside = pointer.y >= r.top && pointer.y <= r.bottom && pointer.x >= r.left && pointer.x <= r.right;
        set.items.forEach(function (it) {
          if (!inside) { it.style.setProperty("--dx", "0px"); it.style.setProperty("--dy", "0px"); it.classList.remove("near"); return; }
          var b = it.getBoundingClientRect();
          var cx = b.left + b.width / 2, cy = b.top + b.height / 2;
          var dx = cx - pointer.x, dy = cy - pointer.y, d = Math.sqrt(dx * dx + dy * dy) || 1;
          var radius = 170;
          if (d < radius) {
            var push = (1 - d / radius) * 70;
            it.style.setProperty("--dx", (dx / d * push).toFixed(1) + "px");
            it.style.setProperty("--dy", (dy / d * push).toFixed(1) + "px");
            it.classList.add("near");
          } else {
            it.style.setProperty("--dx", "0px"); it.style.setProperty("--dy", "0px"); it.classList.remove("near");
          }
        });
      });
    });
  }

  function initFloaters() {
    if (reduced) return;
    var small = window.innerWidth < 760;
    document.querySelectorAll(".hero").forEach(function (h) { addFloaters(h, small ? 6 : 12, 1); });
    document.querySelectorAll(".cta-band").forEach(function (h) { addFloaters(h, small ? 3 : 5, 0.45); });
    window.addEventListener("pointermove", onPointer, { passive: true });
  }

  /* Subtle 3D tilt on the hero photo */
  function initTilt() {
    if (reduced) return;
    var fig = document.querySelector(".hero-figure");
    if (!fig || !window.matchMedia("(hover: hover)").matches) return;
    fig.addEventListener("pointermove", function (e) {
      var r = fig.getBoundingClientRect();
      var px = (e.clientX - r.left) / r.width - 0.5, py = (e.clientY - r.top) / r.height - 0.5;
      fig.style.setProperty("--tilt-y", (px * 7).toFixed(2) + "deg");
      fig.style.setProperty("--tilt-x", (-py * 7).toFixed(2) + "deg");
    });
    fig.addEventListener("pointerleave", function () {
      fig.style.setProperty("--tilt-x", "0deg"); fig.style.setProperty("--tilt-y", "0deg");
    });
  }

  /* ---------------------------------------------------------------
     5. GST TERMS TICKER (hover / focus a term for a one-line meaning)
  --------------------------------------------------------------- */
  var TERMS = [
    ["CGST", "Central GST — levied by the Centre on intra-state supplies."],
    ["SGST", "State GST — levied by the State on intra-state supplies."],
    ["IGST", "Integrated GST — levied by the Centre on inter-state supplies and imports."],
    ["Input Tax Credit", "Credit for GST paid on purchases, set off against GST payable on sales."],
    ["GSTR-1", "Return of outward supplies (sales) filed by a registered person."],
    ["GSTR-3B", "Summary return in which tax liability is declared and paid."],
    ["HSN Code", "Harmonised System of Nomenclature — a classification code for goods."],
    ["E-Way Bill", "Electronic document required for movement of goods above a prescribed value."],
    ["Reverse Charge", "In notified cases, the recipient (not the supplier) pays the GST."],
    ["Place of Supply", "Decides whether a supply is intra-state or inter-state."],
    ["E-Invoice", "Invoice registered on the IRP, which issues a unique Invoice Reference Number."],
    ["Composition Scheme", "Simplified, lower-compliance scheme for eligible small taxpayers."],
    ["Show Cause Notice", "Notice giving the taxpayer a chance to respond before a demand is confirmed."],
    ["GSTAT", "GST Appellate Tribunal — the further appellate forum for GST disputes."]
  ];

  function buildGroup(hidden) {
    var g = document.createElement("div");
    g.className = "gst-group";
    if (hidden) g.setAttribute("aria-hidden", "true");
    TERMS.forEach(function (t) {
      var b = document.createElement("button");
      b.type = "button"; b.className = "gst-term"; b.textContent = t[0];
      b.dataset.def = t[1];
      if (hidden) b.tabIndex = -1;
      g.appendChild(b);
    });
    return g;
  }

  function initTicker() {
    if (document.querySelector(".gst-ticker")) return;
    var ticker = document.createElement("section");
    ticker.className = "gst-ticker";
    ticker.setAttribute("aria-label", "GST glossary");
    var track = document.createElement("div");
    track.className = "gst-track";
    track.appendChild(buildGroup(false));
    track.appendChild(buildGroup(true)); // duplicate for seamless loop
    ticker.appendChild(track);

    var hero = document.querySelector(".hero");
    var footer = document.getElementById("site-footer-slot") || document.querySelector(".site-footer");
    if (hero && hero.parentNode) hero.parentNode.insertBefore(ticker, hero.nextSibling);
    else if (footer && footer.parentNode) footer.parentNode.insertBefore(ticker, footer);
    else document.body.appendChild(ticker);

    var tip = document.createElement("div");
    tip.className = "gst-tip"; tip.setAttribute("role", "tooltip");
    document.body.appendChild(tip);
    function show(btn) {
      tip.innerHTML = "<b></b><span></span>";
      tip.firstChild.textContent = btn.textContent;
      tip.lastChild.textContent = btn.dataset.def;
      var r = btn.getBoundingClientRect();
      var left = Math.max(8, Math.min(window.innerWidth - 296, r.left + r.width / 2 - 140));
      tip.style.left = left + "px";
      tip.style.top = (r.bottom + 8) + "px";
      tip.classList.add("show");
    }
    function hide() { tip.classList.remove("show"); }
    ticker.addEventListener("mouseover", function (e) { var b = e.target.closest(".gst-term"); if (b) show(b); });
    ticker.addEventListener("mouseout", hide);
    ticker.addEventListener("focusin", function (e) { var b = e.target.closest(".gst-term"); if (b) show(b); });
    ticker.addEventListener("focusout", hide);
    ticker.addEventListener("click", function (e) { var b = e.target.closest(".gst-term"); if (b) show(b); });
  }

  /* ---------------------------------------------------------------
     PROCESS TIMELINE (practice-areas.html, "How We Work")
     The gold line draws from step 01 to 04, each circle lights up with
     a halo and its text rises, when the timeline scrolls into view.
     The sequence itself is CSS (.px-steps.is-armed / .is-drawn).
  --------------------------------------------------------------- */
  function initProcessTimeline() {
    var list = document.querySelector(".px-steps");
    if (!list || reduced || !canObserve) return; // stays in its final, visible state
    list.classList.add("is-armed");
    var io = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) return;
      // next frame, so the "before" state is painted and the transitions run
      requestAnimationFrame(function () { list.classList.add("is-drawn"); });
      io.disconnect();
    }, { threshold: 0.3 });
    io.observe(list);
  }

  /* ---------------------------------------------------------------
     WHY ADA (practice-areas.html, "Our Approach")
     One cinematic entry when ~22% of the section is in view: image
     layers, quote, gold orbit, headline, then the three strengths.
     The choreography is CSS (.px-why.is-armed / .is-in); this only
     arms it and starts the orbit's travelling point (SVG SMIL).
  --------------------------------------------------------------- */
  function initWhy() {
    var sec = document.querySelector(".px-why");
    if (!sec || reduced || !canObserve) return; // stays in its final, visible state
    sec.classList.add("is-armed");
    var io = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) return;
      io.disconnect();
      requestAnimationFrame(function () { sec.classList.add("is-in"); });
      var run = document.getElementById("px-orbit-run");
      if (run && run.beginElement) setTimeout(function () { try { run.beginElement(); } catch (e) {} }, 750);
    }, { threshold: 0.22 });
    io.observe(sec);
  }

  /* ---------------------------------------------------------------
     BOOT
  --------------------------------------------------------------- */
  function boot() {
    initWhy();
    initProcessTimeline();
    initProgress();
    initTicker();
    initFloaters();
    initTilt();
    initCountUp();
    prepareReveals(document);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot); else boot();

  // Header/footer are injected asynchronously by partials.js
  document.addEventListener("ada:partials-loaded", function () {
    var f = document.querySelector(".site-footer");
    if (f) addFloaters(f, window.innerWidth < 760 ? 3 : 6, 0.4);
    prepareReveals(document);
  });

  // Pick up content rendered later (articles, resources, links, experts…)
  if (!reduced && "MutationObserver" in window) {
    var pending = false;
    new MutationObserver(function () {
      if (pending) return; pending = true;
      setTimeout(function () { pending = false; prepareReveals(document); }, 120);
    }).observe(document.body, { childList: true, subtree: true });
  }
})();
