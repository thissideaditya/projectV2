/* ===================================================================
   ADA Law Chambers — motion-plus.js
   Heavier interactive layer. Needs css/motion-plus.css.
   Sections: 1 GST Lab · 2 Particle network · 3 Hero typing ·
   4 Cursor ring · 5 Spotlight + magnetic · 6 Coin bursts ·
   7 Page transitions · 8 Back-to-top coin · 9 Hero parallax
=================================================================== */
(function () {
  "use strict";

  var reduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var finePointer = window.matchMedia && window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var inr = function (n) { return "₹" + Math.round(n).toLocaleString("en-IN"); };

  /* ---------------------------------------------------------------
     1. GST LAB — interactive supply-chain flow + CGST/SGST/IGST split
        Illustrative model: each stage adds 30% of the base price.
  --------------------------------------------------------------- */
  function initLab() {
    var host = $("[data-gst-lab]");
    if (!host) return;

    host.innerHTML =
      '<section class="section gst-lab"><div class="container">' +
        '<div class="section-head"><span class="eyebrow">Interactive</span>' +
        '<h2 class="section-title">See how GST actually flows</h2><div class="divider-gold"></div>' +
        '<p>Change the rate or the price and watch tax move down the chain. Gold coins are tax charged on each sale; green coins are Input Tax Credit flowing back to offset it.</p></div>' +
        '<div class="lab-controls">' +
          '<div class="lab-group"><span class="lab-label">GST rate</span><div class="seg" data-seg="rate">' +
            '<button type="button" data-v="5" aria-pressed="false">5%</button>' +
            '<button type="button" data-v="18" aria-pressed="true">18%</button>' +
            '<button type="button" data-v="40" aria-pressed="false">40%</button></div></div>' +
          '<div class="lab-group"><label for="lab-price">Manufacturer’s price</label><div class="lab-range">' +
            '<input id="lab-price" type="range" min="500" max="10000" step="100" value="1000"><output id="lab-price-out">₹1,000</output></div></div>' +
          '<div class="lab-group"><span class="lab-label">Supply type</span><div class="seg" data-seg="route">' +
            '<button type="button" data-v="intra" aria-pressed="true">Within one State</button>' +
            '<button type="button" data-v="inter" aria-pressed="false">Across States</button></div></div>' +
          '<div class="lab-group"><span class="lab-label">What if…</span><label class="lab-switch"><input type="checkbox" id="lab-deny"> Wholesaler’s ITC is denied</label></div>' +
        '</div>' +
        '<div class="lab-stage" id="lab-stage"></div>' +
        '<div class="lab-bottom">' +
          '<div class="lab-card"><h4>Net tax reaching the Government</h4><div class="lab-big" id="lab-total">₹0</div><p class="lab-note" id="lab-note"></p></div>' +
          '<div class="lab-card"><h4>Tax on the first sale, split by type</h4><div class="split-bar" id="lab-split">' +
            '<div class="split-seg a" data-k="cgst">CGST</div><div class="split-seg b" data-k="sgst">SGST</div><div class="split-seg i" data-k="igst" hidden>IGST</div></div>' +
            '<div class="lab-legend" id="lab-legend"></div></div>' +
        '</div>' +
        '<p class="lab-foot">Illustrative model only — rates, credit rules and exemptions vary by supply. This is not tax or legal advice; please consult us on your specific facts.</p>' +
      '</div></section>';

    var stages = [
      { name: "Manufacturer", role: "Makes the goods", mult: 1.0 },
      { name: "Wholesaler", role: "Buys in bulk", mult: 1.3 },
      { name: "Retailer", role: "Sells to public", mult: 1.6 },
      { name: "Consumer", role: "Bears the tax", mult: 1.6 }
    ];
    var stage = $("#lab-stage", host);
    var state = { rate: 18, price: 1000, route: "intra", deny: false };
    var shown = {}; // current displayed numbers for tweening

    // build nodes + links once
    var html = "";
    stages.forEach(function (s, i) {
      html += '<div class="lab-node" data-i="' + i + '"><h3><i>' + (i + 1) + '</i>' + s.name + '</h3><span class="role">' + s.role + '</span>' +
        '<div class="lab-row"><span>' + (i === 3 ? "Pays" : "Sells at") + '</span><b data-f="value">—</b></div>' +
        '<div class="lab-row"><span>GST ' + (i === 3 ? "included" : "charged") + '</span><b data-f="tax">—</b></div>' +
        (i < 3 ? '<div class="lab-row itc"><span>ITC claimed</span><b data-f="itc">—</b></div>' +
                 '<div class="lab-row net"><span>Pays Govt</span><b data-f="net">—</b></div>'
               : '<div class="lab-row itc"><span>ITC claimed</span><b>— (no credit)</b></div>') +
        '</div>';
      if (i < 3) html += '<div class="lab-link" data-l="' + i + '" aria-hidden="true"><span class="coin tax">₹</span><span class="coin itc">↩</span></div>';
    });
    // 4 nodes + 3 links: use auto columns
    stage.style.gridTemplateColumns = "repeat(3, minmax(0,1fr) auto) minmax(0,1fr)";
    stage.innerHTML = html;
    function setLinkWidths() {
      $$(".lab-link", stage).forEach(function (l) { l.style.setProperty("--lw", l.getBoundingClientRect().width + "px"); });
    }
    if (window.matchMedia("(max-width: 860px)").matches) stage.style.gridTemplateColumns = "";
    window.addEventListener("resize", function () {
      stage.style.gridTemplateColumns = window.innerWidth <= 860 ? "" : "repeat(3, minmax(0,1fr) auto) minmax(0,1fr)";
      setLinkWidths();
    });

    function tween(el, key, to, fmt) {
      var from = shown[key] == null ? 0 : shown[key];
      shown[key] = to;
      if (reduced || from === to) { el.textContent = fmt(to); return; }
      var t0 = performance.now(), dur = 550;
      (function tick(t) {
        var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
        el.textContent = fmt(from + (to - from) * e);
        if (p < 1) requestAnimationFrame(tick);
      })(t0);
    }

    function compute() {
      var r = state.rate / 100, P = state.price;
      var v = stages.map(function (s) { return P * s.mult; });
      var tax = v.map(function (x) { return x * r; });
      var itc = [0, tax[0], tax[1], 0];
      if (state.deny) itc[1] = 0;           // wholesaler's credit denied
      var net = [tax[0] - itc[0], tax[1] - itc[1], tax[2] - itc[2]];
      var total = net[0] + net[1] + net[2];
      return { v: v, tax: tax, itc: itc, net: net, total: total, r: r, P: P };
    }

    function render(animateFlag) {
      var c = compute();
      $$(".lab-node", stage).forEach(function (node, i) {
        function set(f, val) { var el = $('[data-f="' + f + '"]', node); if (el) tween(el, i + f, val, inr); }
        set("value", c.v[i]);
        set("tax", c.tax[i]);
        if (i < 3) { set("itc", c.itc[i]); set("net", c.net[i]); }
        if (i === 1) {
          var row = $(".lab-row.itc", node);
          row.classList.toggle("lost", state.deny);
          node.classList.toggle("flag", false);
          if (animateFlag && state.deny) { void node.offsetWidth; node.classList.add("flag"); }
        }
      });
      $$(".lab-link", stage).forEach(function (l, i) { l.classList.toggle("broken", state.deny && i === 0); });
      tween($("#lab-total", host), "total", c.total, inr);

      var extra = state.deny ? c.tax[0] : 0;
      $("#lab-note", host).className = "lab-note" + (state.deny ? " warn" : "");
      $("#lab-note", host).textContent = state.deny
        ? "Credit lost at the wholesaler: " + inr(extra) + " of tax gets charged twice in the chain and ends up as a cost to the business — this is why ITC disputes matter."
        : "With full credit, each stage pays tax only on the value it adds. The total equals the tax on the final sale — " + inr(c.tax[2]) + " — and the consumer bears it.";

      // CGST / SGST / IGST split on the first sale
      var first = c.tax[0], inter = state.route === "inter";
      var cg = $('[data-k="cgst"]', host), sg = $('[data-k="sgst"]', host), ig = $('[data-k="igst"]', host);
      cg.hidden = sg.hidden = inter; ig.hidden = !inter;
      if (!inter) {
        cg.style.flexGrow = 1; sg.style.flexGrow = 1;
        cg.textContent = "CGST " + (state.rate / 2) + "%"; sg.textContent = "SGST " + (state.rate / 2) + "%";
        $("#lab-legend", host).textContent = "Same-State sale: " + inr(first) + " splits equally — " + inr(first / 2) + " to the Centre (CGST) and " + inr(first / 2) + " to the State (SGST).";
      } else {
        ig.style.flexGrow = 1; ig.textContent = "IGST " + state.rate + "%";
        $("#lab-legend", host).textContent = "Inter-State sale: the full " + inr(first) + " is charged as IGST, collected by the Centre and shared with the destination State.";
      }
    }

    // wire controls
    $$("[data-seg]", host).forEach(function (seg) {
      seg.addEventListener("click", function (e) {
        var b = e.target.closest("button"); if (!b) return;
        $$("button", seg).forEach(function (x) { x.setAttribute("aria-pressed", x === b ? "true" : "false"); });
        var k = seg.dataset.seg;
        state[k] = k === "rate" ? parseInt(b.dataset.v, 10) : b.dataset.v;
        render(false);
      });
    });
    $("#lab-price", host).addEventListener("input", function (e) {
      state.price = parseInt(e.target.value, 10);
      $("#lab-price-out", host).textContent = inr(state.price);
      render(false);
    });
    $("#lab-deny", host).addEventListener("change", function (e) { state.deny = e.target.checked; render(true); });

    render(false);
    setLinkWidths();
    // recalc link widths once fonts/layout settle
    setTimeout(setLinkWidths, 400);
  }

  /* ---------------------------------------------------------------
     2. PARTICLE NETWORK in heroes — nodes link up, cursor joins in
  --------------------------------------------------------------- */
  function initNetwork(host) {
    if (reduced || host.querySelector(".gst-net")) return;
    var canvas = document.createElement("canvas");
    canvas.className = "gst-net"; canvas.setAttribute("aria-hidden", "true");
    host.insertBefore(canvas, host.firstChild);
    var ctx = canvas.getContext("2d"), dpr = Math.min(window.devicePixelRatio || 1, 2);
    var W = 0, H = 0, nodes = [], mouse = null, running = false, visible = true;

    function resize() {
      var r = host.getBoundingClientRect();
      W = r.width; H = r.height;
      canvas.width = W * dpr; canvas.height = H * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.max(18, Math.min(60, Math.round(W * H / 20000)));
      nodes = [];
      for (var i = 0; i < n; i++) nodes.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35, r: 1 + Math.random() * 1.6 });
    }
    resize();
    window.addEventListener("resize", resize);

    window.addEventListener("pointermove", function (e) {
      var r = host.getBoundingClientRect();
      mouse = (e.clientY >= r.top && e.clientY <= r.bottom && e.clientX >= r.left && e.clientX <= r.right) ? { x: e.clientX - r.left, y: e.clientY - r.top } : null;
    }, { passive: true });

    function frame() {
      if (!visible || document.hidden) { running = false; return; }
      ctx.clearRect(0, 0, W, H);
      var i, j, a, b, dx, dy, d;
      for (i = 0; i < nodes.length; i++) {
        a = nodes[i];
        a.x += a.vx; a.y += a.vy;
        if (a.x < 0 || a.x > W) a.vx *= -1;
        if (a.y < 0 || a.y > H) a.vy *= -1;
        if (mouse) { // gentle attraction
          dx = mouse.x - a.x; dy = mouse.y - a.y; d = Math.sqrt(dx * dx + dy * dy);
          if (d < 160 && d > 1) { a.x += dx / d * .35; a.y += dy / d * .35; }
        }
        for (j = i + 1; j < nodes.length; j++) {
          b = nodes[j]; dx = a.x - b.x; dy = a.y - b.y; d = Math.sqrt(dx * dx + dy * dy);
          if (d < 120) { ctx.strokeStyle = "rgba(220,184,74," + (0.22 * (1 - d / 120)).toFixed(3) + ")"; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); }
        }
        if (mouse) {
          dx = a.x - mouse.x; dy = a.y - mouse.y; d = Math.sqrt(dx * dx + dy * dy);
          if (d < 170) { ctx.strokeStyle = "rgba(244,233,201," + (0.5 * (1 - d / 170)).toFixed(3) + ")"; ctx.lineWidth = 1.2; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(mouse.x, mouse.y); ctx.stroke(); }
        }
        ctx.fillStyle = "rgba(220,184,74,.55)"; ctx.beginPath(); ctx.arc(a.x, a.y, a.r, 0, 6.2832); ctx.fill();
      }
      requestAnimationFrame(frame);
    }
    function start() { if (!running) { running = true; requestAnimationFrame(frame); } }
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function (e) { visible = e[0].isIntersecting; if (visible) start(); }).observe(host);
    }
    document.addEventListener("visibilitychange", function () { if (!document.hidden) start(); });
    start();
  }

  /* ---------------------------------------------------------------
     3. HERO TYPING — eyebrow, headline and intro type out word by word
        (fast). Words keep their space from the start, so nothing
        jumps around while typing. Starts after the disclaimer closes.
  --------------------------------------------------------------- */
  var TYPE_MS = { eyebrow: 70, h1: 110, lede: 38 };   // delay per word — lower = faster

  function wrapWords(el) {
    var words = [];
    (function walk(node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (ch) {
        if (ch.nodeType === 3) {
          var frag = document.createDocumentFragment();
          ch.textContent.split(/(\s+)/).forEach(function (tok) {
            if (!tok) return;
            if (/^\s+$/.test(tok)) { frag.appendChild(document.createTextNode(" ")); return; }
            var w = document.createElement("span"); w.className = "tw"; w.textContent = tok;
            words.push(w); frag.appendChild(w);
          });
          node.replaceChild(frag, ch);
        } else if (ch.nodeType === 1) walk(ch);
      });
    })(el);
    return words;
  }

  function typeHero(hero) {
    if (reduced) return;
    var parts = [
      [$(".eyebrow", hero), TYPE_MS.eyebrow],
      [$("h1", hero), TYPE_MS.h1],
      [$(".lede", hero), TYPE_MS.lede]
    ].filter(function (p) { return p[0]; }).map(function (p) { return { words: wrapWords(p[0]), ms: p[1] }; });
    var cta = $$(".hero-cta", hero);

    var queue = [];
    parts.forEach(function (p) { p.words.forEach(function (w) { queue.push({ w: w, ms: p.ms }); }); });

    function start() {
      var i = 0, prev = null;
      (function step() {
        if (prev) prev.classList.remove("cur");
        if (i >= queue.length) {
          cta.forEach(function (c) { c.classList.add("tw-done"); });
          return;
        }
        var q = queue[i++];
        q.w.classList.add("on", "cur"); prev = q.w;
        setTimeout(step, q.ms);
      })();
    }

    // wait for the disclaimer gate (first visit) before typing. The gate is injected
    // from partials/disclaimer.html, so wait for main.js's "closed" event.
    if (window.__adaDisclaimerDone) {
      setTimeout(start, 350);
    } else {
      document.addEventListener("ada:disclaimer-closed", function () { setTimeout(start, 250); }, { once: true });
    }
  }

  /* ---------------------------------------------------------------
     4. CURSOR RING
  --------------------------------------------------------------- */
  function initCursor() {
    if (reduced || !finePointer) return;
    var ring = document.createElement("div"), dot = document.createElement("div");
    ring.className = "cursor-ring"; dot.className = "cursor-dot";
    ring.setAttribute("aria-hidden", "true"); dot.setAttribute("aria-hidden", "true");
    document.body.appendChild(ring); document.body.appendChild(dot);
    var tx = -100, ty = -100, x = -100, y = -100;
    window.addEventListener("pointermove", function (e) {
      tx = e.clientX; ty = e.clientY; ring.classList.add("on"); dot.classList.add("on");
      dot.style.transform = "translate(" + tx + "px," + ty + "px)";
      var hot = e.target.closest && e.target.closest("a, button, .card, input, label, .gst-term, [role=button]");
      ring.classList.toggle("big", !!hot);
      var onGold = !!(e.target.closest && e.target.closest(".cta-band"));
      ring.classList.toggle("on-gold", onGold); dot.classList.toggle("on-gold", onGold);
    }, { passive: true });
    window.addEventListener("pointerdown", function () { ring.classList.add("down"); });
    window.addEventListener("pointerup", function () { ring.classList.remove("down"); });
    document.addEventListener("mouseleave", function () { ring.classList.remove("on"); dot.classList.remove("on"); });
    (function loop() { x += (tx - x) * .18; y += (ty - y) * .18; ring.style.transform = "translate(" + x.toFixed(1) + "px," + y.toFixed(1) + "px)"; requestAnimationFrame(loop); })();
  }

  /* ---------------------------------------------------------------
     5. SPOTLIGHT ON CARDS + MAGNETIC BUTTONS (event delegation, so
        content rendered later is covered automatically)
  --------------------------------------------------------------- */
  function initHoverFx() {
    if (reduced || !finePointer) return;
    document.addEventListener("pointermove", function (e) {
      var card = e.target.closest && e.target.closest(".card");
      if (card) {
        var r = card.getBoundingClientRect();
        card.style.setProperty("--mx", (e.clientX - r.left) + "px");
        card.style.setProperty("--my", (e.clientY - r.top) + "px");
      }
      var btn = e.target.closest && e.target.closest(".btn");
      if (btn) {
        var b = btn.getBoundingClientRect();
        btn.style.setProperty("--bx", ((e.clientX - (b.left + b.width / 2)) * .18).toFixed(1) + "px");
        btn.style.setProperty("--by", ((e.clientY - (b.top + b.height / 2)) * .28).toFixed(1) + "px");
      }
    }, { passive: true });
    document.addEventListener("pointerout", function (e) {
      var btn = e.target.closest && e.target.closest(".btn");
      if (btn) { btn.style.setProperty("--bx", "0px"); btn.style.setProperty("--by", "0px"); }
    });
  }

  /* ---------------------------------------------------------------
     6. COIN BURST on button / ticker clicks
  --------------------------------------------------------------- */
  function burst(x, y) {
    var glyphs = ["₹", "%", "₹", "§", "₹"];
    for (var i = 0; i < 9; i++) {
      var c = document.createElement("span");
      c.className = "coin-burst"; c.textContent = glyphs[i % glyphs.length];
      var ang = Math.random() * Math.PI * 2, dist = 50 + Math.random() * 70;
      c.style.left = x + "px"; c.style.top = y + "px";
      c.style.setProperty("--tx", Math.cos(ang) * dist + "px");
      c.style.setProperty("--ty", Math.sin(ang) * dist - 30 + "px");
      c.style.setProperty("--rot", (Math.random() * 360 - 180) + "deg");
      document.body.appendChild(c);
      setTimeout((function (el) { return function () { el.remove(); }; })(c), 950);
    }
  }
  function initBursts() {
    if (reduced) return;
    document.addEventListener("click", function (e) {
      if (e.target.closest && e.target.closest(".btn, .gst-term, .coin-top, .seg button")) burst(e.clientX, e.clientY);
    });
  }

  /* ---------------------------------------------------------------
     7. PAGE TRANSITIONS — fade out before following an internal link
  --------------------------------------------------------------- */
  function initTransitions() {
    if (reduced) return;
    document.addEventListener("click", function (e) {
      var a = e.target.closest && e.target.closest("a[href]");
      if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (a.target && a.target !== "_self") return;
      if (a.hasAttribute("download")) return;
      var url; try { url = new URL(a.href, location.href); } catch (_) { return; }
      if (url.origin !== location.origin || !/\.html?$|\/$/.test(url.pathname)) return;
      if (url.pathname === location.pathname && url.search === location.search) return; // same page / hash jump
      e.preventDefault();
      document.body.classList.add("page-out");
      setTimeout(function () { location.href = a.href; }, 240);
    });
    window.addEventListener("pageshow", function (e) { if (e.persisted) document.body.classList.remove("page-out"); });
  }

  /* ---------------------------------------------------------------
     8. BACK-TO-TOP COIN
  --------------------------------------------------------------- */
  function initCoinTop() {
    var b = document.createElement("button");
    b.className = "coin-top"; b.type = "button"; b.setAttribute("aria-label", "Back to top");
    b.innerHTML = '<span><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" ' +
                  'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg></span>'; // up arrow
    document.body.appendChild(b);
    b.addEventListener("click", function () { window.scrollTo({ top: 0, behavior: reduced ? "auto" : "smooth" }); });
    var t = false;
    window.addEventListener("scroll", function () {
      if (t) return; t = true;
      requestAnimationFrame(function () { b.classList.toggle("show", window.scrollY > 600); t = false; });
    }, { passive: true });
  }

  /* ---------------------------------------------------------------
     9. HERO PARALLAX
  --------------------------------------------------------------- */
  function initParallax() {
    if (reduced) return;
    var hero = $(".hero"); if (!hero) return;
    var layer = $(".gst-floaters", hero), fig = $(".hero-figure", hero), t = false;
    window.addEventListener("scroll", function () {
      if (t) return; t = true;
      requestAnimationFrame(function () {
        var y = Math.min(window.scrollY, 800);
        if (layer) layer.style.translate = "0 " + (y * -.12).toFixed(1) + "px";
        if (fig) fig.style.translate = "0 " + (y * .06).toFixed(1) + "px";
        t = false;
      });
    }, { passive: true });
  }

  /* ---------------------------------------------------------------
     BOOT
  --------------------------------------------------------------- */
  function boot() {
    window.__adaMotion = true;                                    // tells the <head> failsafe that we loaded
    if (reduced) document.documentElement.classList.remove("js"); // reduced motion: show everything normally
    initLab();
    $$(".hero").forEach(initNetwork);
    $$(".hero").forEach(typeHero);
    initCursor();
    initHoverFx();
    initBursts();
    initTransitions();
    initCoinTop();
    initParallax();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot); else boot();
})();
