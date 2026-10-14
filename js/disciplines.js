/* ===================================================================
   ADA LAW Chambers — disciplines.js
   About page, "One Standard, Many Disciplines": the ADA seal with the
   eight disciplines around it. Styles: css/style.css "DISCIPLINES MAP".

   1. Opening reveal — when the stage comes into view it is dark
      emerald; a gold beam sweeps across, particles gather in the
      centre and the seal emerges with its two rotating rings, then the
      background dawns into the cream design.
   2. Gold pulses — the seal sends a pulse along each connector; each
      card activates in turn (icon draws itself in gold, then its
      title appears). Once all are on, a softer pulse loops every few
      seconds.
   3. Magnetic cards — near the cursor a card tilts in 3D, lifts and
      gets a gold edge glow; its icon does a small motion (scales
      balance, gavel swings, chart redraws…).
   4. Living background — drifting gold particles, slow arcs, and a
      soft spotlight that follows the cursor.
   5. Orbit — the seal stays put while the eight cards keep circling it
      on a loop through their home positions: a slow drift of their own
      (paused while the cursor is near a card), and scrolling turns them
      forward (down) or back (up). The page is never pinned. Each card's
      border gets a gold sweep as it activates.
   Visitors who ask for reduced motion get the finished layout at once.
=================================================================== */
(function () {
  "use strict";

  var root = document.querySelector("[data-disc]");
  if (!root) return;

  var W = 1400, H = 750, HUB = { x: 700, y: 375 }, HUB_R = 152; // design canvas (see CSS)
  var RX = 520, RY = 300;   // the oval the cards travel on (home positions in about.html sit on it)
  var stage = root.querySelector(".disc-stage");
  var canvasEl = root.querySelector(".disc-canvas");
  var linksSvg = root.querySelector(".disc-links");
  var hub = root.querySelector(".disc-hub");
  var slots = Array.prototype.slice.call(root.querySelectorAll(".disc-slot"));
  var cards = slots.map(function (s) { return s.querySelector(".disc-card"); });
  var pos = slots.map(function (s) {
    return { x: parseFloat(s.style.getPropertyValue("--x")), y: parseFloat(s.style.getPropertyValue("--y")) };
  });

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var wideMq = window.matchMedia("(min-width: 900px)");
  var fineMq = window.matchMedia("(hover: hover) and (pointer: fine)");
  var SVGNS = "http://www.w3.org/2000/svg";

  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function easeInOut(t) { return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
  function headerH() { return parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--hh")) || 96; }

  var state = { introDone: false, activated: false, visible: false, scale: 1, loop: null };

  if (!reduced) root.classList.add("disc--armed");

  /* ---------- Layout: scale the design canvas, size card borders ---------- */
  function layout() {
    var map = wideMq.matches;
    root.classList.toggle("is-map", map);
    root.classList.remove("is-pinned"); // no pinned scroll stretch: the page always scrolls normally
    if (map) {
      state.scale = Math.min(1, stage.clientWidth / (W + 80), stage.clientHeight / (H + 40));
      canvasEl.style.setProperty("--s", state.scale.toFixed(4));
    } else {
      state.scale = 1;
      canvasEl.style.removeProperty("--s");
      slots.forEach(function (s) { ["--ox", "--oy", "--os", "--oo"].forEach(function (p) { s.style.removeProperty(p); }); });
    }
    cards.forEach(function (c) {
      var r = c.querySelector(".disc-border rect"), w = c.offsetWidth - 2, h = c.offsetHeight - 2;
      c._hw = c.offsetWidth / 2; c._hh = c.offsetHeight / 2;
      r.setAttribute("width", w); r.setAttribute("height", h); r.setAttribute("rx", h / 2); r.setAttribute("ry", h / 2);
    });
    if (map) { buildLinks(); render(); }
  }

  /* ---------- 5. The orbit ----------
     The cards travel round an oval through their eight home positions,
     45° apart and all at the same pace; with every card the same size
     (css: .disc.is-map .disc-card) they can never run into each other. phase = how far round they are (1 = one
     card position). It drifts slowly on its own, pauses while the cursor
     is near a card, and scrolling turns it: down = forward, up = back.
     On first view the cards also spiral in from the seal (entrance).   */
  var N = pos.length;
  function loopPoint(t) {
    var a = -Math.PI / 2 + t * (2 * Math.PI / N); // t = 0 is the top (Law), then clockwise
    return { x: HUB.x + RX * Math.cos(a), y: HUB.y + RY * Math.sin(a) };
  }

  var DRIFT = 1 / 8;          // card positions per second when left alone
  var SCROLL_STEP = 450;      // px of scrolling that moves the cards one position
  var drift = 0, driftSpeed = DRIFT, scrollTarget = 0, scrollShown = 0, lastScrollY = window.scrollY;
  var entrance = reduced ? 1 : 0;   // 0 = gathered at the seal, 1 = out on the loop

  // Connectors: one path + two dots per card, re-aimed every frame.
  var links = [], paths = [];
  function buildLinks() {
    linksSvg.setAttribute("viewBox", "0 0 " + W + " " + H);
    linksSvg.innerHTML = "";
    links = cards.map(function () {
      var path = document.createElementNS(SVGNS, "path"); path.setAttribute("class", "disc-link");
      var a = document.createElementNS(SVGNS, "circle"); a.setAttribute("class", "disc-dot"); a.setAttribute("r", 5);
      var b = document.createElementNS(SVGNS, "circle"); b.setAttribute("class", "disc-dot"); b.setAttribute("r", 4);
      linksSvg.appendChild(path); linksSvg.appendChild(a); linksSvg.appendChild(b);
      return { path: path, a: a, b: b, pulses: [] };
    });
  }
  function aim(i, P) {
    var c = cards[i], hw = c._hw || 160, hh = c._hh || 50;
    var dx = P.x - HUB.x, dy = P.y - HUB.y, len = Math.hypot(dx, dy) || 1, ux = dx / len, uy = dy / len;
    var sx = HUB.x + ux * HUB_R, sy = HUB.y + uy * HUB_R;
    // where the line from the card's centre towards the seal leaves the card
    var k = Math.min(Math.abs(ux) > 1e-3 ? hw / Math.abs(ux) : 1e9, Math.abs(uy) > 1e-3 ? hh / Math.abs(uy) : 1e9) * .96;
    var ex = P.x - ux * k, ey = P.y - uy * k;
    var gap = Math.hypot(ex - sx, ey - sy), kk = Math.min(60, gap * .4);
    var d = gap < 8 ? "" : "M" + sx.toFixed(1) + " " + sy.toFixed(1) + "C" + (sx + ux * kk).toFixed(1) + " " + (sy + uy * kk).toFixed(1) + " " +
            (ex - ux * kk).toFixed(1) + " " + (ey - uy * kk).toFixed(1) + " " + ex.toFixed(1) + " " + ey.toFixed(1);
    var L = links[i];
    paths[i] = d;
    L.path.setAttribute("d", d);
    L.pulses.forEach(function (pp) { pp.setAttribute("d", d); });
    L.a.setAttribute("cx", sx.toFixed(1)); L.a.setAttribute("cy", sy.toFixed(1));
    L.b.setAttribute("cx", (ex - ux * 10).toFixed(1)); L.b.setAttribute("cy", (ey - uy * 10).toFixed(1));
    var show = d && entrance > .6 ? "1" : "0";
    L.path.style.opacity = show; L.a.style.opacity = show; L.b.style.opacity = show;
  }

  function render() {
    if (!root.classList.contains("is-map") || !links.length) return;
    var phase = drift + scrollShown;
    slots.forEach(function (s, i) {
      var P = loopPoint(i + phase);
      var q = easeInOut(clamp((entrance - i * .03) / .75, 0, 1));
      var dx = P.x - HUB.x, dy = P.y - HUB.y;
      var a = Math.atan2(dy, dx) - (1 - q) * 2.3, rr = Math.hypot(dx, dy) * (.3 + .7 * q);
      var X = HUB.x + Math.cos(a) * rr, Y = HUB.y + Math.sin(a) * rr;
      s.style.setProperty("--ox", (X - pos[i].x).toFixed(1) + "px");
      s.style.setProperty("--oy", (Y - pos[i].y).toFixed(1) + "px");
      s.style.setProperty("--os", (.55 + .45 * q).toFixed(3));
      s.style.setProperty("--oo", Math.min(1, q * 1.6).toFixed(3));
      aim(i, { x: X, y: Y });
    });
  }

  // One animation loop, running only while the section is on screen.
  var running = false, lastT = 0;
  function tick(now) {
    if (!running) return;
    var dt = Math.min(.05, (now - lastT) / 1000 || 0); lastT = now;
    if (!reduced && state.introDone) {
      var hold = cards.some(function (c) { return c._near; });
      driftSpeed += ((hold ? 0 : DRIFT) - driftSpeed) * Math.min(1, dt * 3);
      drift += driftSpeed * dt;
      scrollShown += (scrollTarget - scrollShown) * Math.min(1, dt * 4); // glide after the scroll
    }
    render();
    requestAnimationFrame(tick);
  }
  function setRunning(on) {
    on = on && root.classList.contains("is-map");
    if (on === running) return;
    running = on;
    if (on) { lastT = performance.now(); requestAnimationFrame(tick); }
  }

  function playEntrance() {
    if (reduced || !root.classList.contains("is-map")) { entrance = 1; maybeActivate(); return; }
    var t0 = performance.now(), D = 900;
    (function frame(now) {
      entrance = clamp((now - t0) / D, 0, 1);
      if (!running) render();
      maybeActivate();
      if (entrance < 1) requestAnimationFrame(frame);
    })(t0);
  }

  /* ---------- 2. Pulses + activation ---------- */
  function pulse(i, faint, done) {
    if (!paths[i] || !links[i] || !root.classList.contains("is-map")) { if (done) done(); return; }
    var p = document.createElementNS(SVGNS, "path");
    p.setAttribute("class", "disc-pulse" + (faint ? " is-faint" : ""));
    p.setAttribute("d", paths[i]); p.setAttribute("pathLength", "100");
    p.style.strokeDasharray = "16 200";
    linksSvg.appendChild(p);
    links[i].pulses.push(p); // kept on the moving connector by aim()
    var anim = p.animate([{ strokeDashoffset: 16 }, { strokeDashoffset: -100 }],
                         { duration: faint ? 1100 : 300, easing: "cubic-bezier(.4,0,.2,1)" });
    anim.onfinish = function () {
      p.remove();
      links[i].pulses = links[i].pulses.filter(function (x) { return x !== p; });
      if (done) done();
    };
  }

  function activate(i) {
    var c = cards[i];
    c.classList.add("is-on", "is-swept");
    setTimeout(function () { c.classList.add("is-drawn"); }, 500);
  }

  function ping(i) {
    var c = cards[i];
    c.classList.remove("is-ping"); void c.offsetWidth; c.classList.add("is-ping");
    setTimeout(function () { c.classList.remove("is-ping"); }, 1150);
  }

  function activateAll() {
    if (state.activated) return;
    state.activated = true;
    cards.forEach(function (c, i) {
      setTimeout(function () { pulse(i, false, function () { activate(i); }); }, i * 60);
    });
    // Once all eight are on, a softer pulse loops round now and then
    state.loop = setInterval(function () {
      if (!state.visible || document.hidden) return;
      cards.forEach(function (c, i) { setTimeout(function () { pulse(i, true, function () { ping(i); }); }, i * 160); });
    }, 6500);
  }

  function maybeActivate() {
    if (state.introDone && !state.activated && state.visible && entrance >= .6) activateAll();
  }

  /* ---------- 1. Opening reveal ---------- */
  var gather = root.querySelector(".disc-gather"), gctx = gather.getContext("2d");
  function runGather(ms) {
    var dpr = window.devicePixelRatio || 1, w = stage.clientWidth, h = stage.clientHeight;
    gather.width = w * dpr; gather.height = h * dpr; gctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var hb = hub.getBoundingClientRect(), sb = stage.getBoundingClientRect();
    var cx = hb.left - sb.left + hb.width / 2, cy = hb.top - sb.top + hb.height / 2;
    var parts = [];
    for (var i = 0; i < 150; i++) {
      var a = Math.random() * Math.PI * 2, d = 160 + Math.random() * Math.max(w, h) * .6;
      parts.push({ x: cx + Math.cos(a) * d, y: cy + Math.sin(a) * d, r: .6 + Math.random() * 1.8, delay: Math.random() * .35, swirl: (Math.random() - .5) * 1.2 });
    }
    var t0 = performance.now();
    (function frame(now) {
      var t = (now - t0) / ms;
      gctx.clearRect(0, 0, w, h);
      if (t >= 1) return;
      gctx.shadowColor = "rgba(246,220,138,.9)"; gctx.shadowBlur = 8;
      parts.forEach(function (p) {
        var q = clamp((t - p.delay) / (1 - p.delay), 0, 1), e = easeInOut(q);
        var ang = Math.atan2(p.y - cy, p.x - cx) + p.swirl * (1 - e), dist = Math.hypot(p.x - cx, p.y - cy) * (1 - e);
        gctx.globalAlpha = Math.min(1, q * 3) * (1 - Math.max(0, (q - .85) / .15));
        gctx.fillStyle = "#f3d27a";
        gctx.beginPath(); gctx.arc(cx + Math.cos(ang) * dist, cy + Math.sin(ang) * dist, p.r, 0, Math.PI * 2); gctx.fill();
      });
      requestAnimationFrame(frame);
    })(t0);
  }

  function playIntro() {
    if (reduced) { finishIntro(); return; }
    root.classList.add("is-revealing");
    setTimeout(function () { runGather(500); }, 50);
    setTimeout(function () { root.classList.add("is-seal"); }, 300);
    setTimeout(function () { root.classList.add("is-dawn"); }, 700);
    setTimeout(function () { root.classList.add("is-done"); }, 1300);
    setTimeout(finishIntro, 650); // cards start as the cream dawns
  }
  function finishIntro() { state.introDone = true; lastScrollY = window.scrollY; playEntrance(); maybeActivate(); }

  /* ---------- 4. Living background: drifting gold particles ---------- */
  var bgCanvas = root.querySelector(".disc-particles"), bctx = bgCanvas.getContext("2d"), motes = [], bgRunning = false;
  function sizeBg() {
    var dpr = window.devicePixelRatio || 1;
    bgCanvas.width = stage.clientWidth * dpr; bgCanvas.height = stage.clientHeight * dpr;
    bctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var n = Math.round(clamp(stage.clientWidth / 28, 18, 55));
    motes = [];
    for (var i = 0; i < n; i++) {
      motes.push({ x: Math.random() * stage.clientWidth, y: Math.random() * stage.clientHeight, r: .5 + Math.random() * 1.8,
                   v: .08 + Math.random() * .22, ph: Math.random() * 6.28, a: .18 + Math.random() * .4 });
    }
  }
  function bgFrame(now) {
    if (!bgRunning) return;
    var w = stage.clientWidth, h = stage.clientHeight;
    bctx.clearRect(0, 0, w, h);
    bctx.fillStyle = "#c9a227";
    motes.forEach(function (m) {
      m.y -= m.v; if (m.y < -6) { m.y = h + 6; m.x = Math.random() * w; }
      bctx.globalAlpha = m.a * (.6 + .4 * Math.sin(now / 1400 + m.ph));
      bctx.beginPath(); bctx.arc(m.x + Math.sin(now / 2600 + m.ph) * 14, m.y, m.r, 0, Math.PI * 2); bctx.fill();
    });
    requestAnimationFrame(bgFrame);
  }
  function setBg(on) {
    if (reduced || on === bgRunning) return;
    bgRunning = on;
    if (on) requestAnimationFrame(bgFrame);
  }

  /* ---------- 3. Magnetic cards + spotlight ---------- */
  var pointer = null, magQueued = false;
  function magnet() {
    magQueued = false;
    if (!pointer || !root.classList.contains("is-map")) return;
    var sb = stage.getBoundingClientRect();
    stage.style.setProperty("--sx", (pointer.x - sb.left) + "px");
    stage.style.setProperty("--sy", (pointer.y - sb.top) + "px");
    stage.style.setProperty("--spot", "1");
    var reach = 230 * state.scale;
    cards.forEach(function (c) {
      var b = c.getBoundingClientRect(), cx = b.left + b.width / 2, cy = b.top + b.height / 2;
      var dx = pointer.x - cx, dy = pointer.y - cy;
      var gx = Math.max(0, Math.abs(dx) - b.width / 2), gy = Math.max(0, Math.abs(dy) - b.height / 2);
      var k = clamp(1 - Math.hypot(gx, gy) / reach, 0, 1);
      c.style.setProperty("--ry", (clamp(dx / (b.width / 2), -1, 1) * 9 * k).toFixed(2) + "deg");
      c.style.setProperty("--rx", (-clamp(dy / (b.height / 2), -1, 1) * 9 * k).toFixed(2) + "deg");
      c.style.setProperty("--lift", k.toFixed(3));
      c.style.setProperty("--glow", (k * k).toFixed(3));
      if (k > .7 && !c._near) { c._near = true; micro(c); }
      else if (k < .25) c._near = false;
    });
  }
  function micro(c) {
    if (!c.classList.contains("is-on")) return;
    c.classList.remove("is-moving"); void c.offsetWidth; c.classList.add("is-moving");
    clearTimeout(c._mt); c._mt = setTimeout(function () { c.classList.remove("is-moving"); }, 1500);
  }
  function releaseMagnet() {
    pointer = null;
    stage.style.setProperty("--spot", "0");
    cards.forEach(function (c) { ["--rx", "--ry", "--lift", "--glow"].forEach(function (p) { c.style.removeProperty(p); }); c._near = false; });
  }
  if (!reduced) {
    stage.addEventListener("pointermove", function (e) {
      if (e.pointerType !== "mouse" || !fineMq.matches) return;
      pointer = { x: e.clientX, y: e.clientY };
      if (!magQueued) { magQueued = true; requestAnimationFrame(magnet); }
    }, { passive: true });
    stage.addEventListener("pointerleave", releaseMagnet);
  }

  /* ---------- Wiring ---------- */
  var scrollQueued = false;
  window.addEventListener("scroll", function () {
    if (scrollQueued) return;
    scrollQueued = true;
    requestAnimationFrame(function () {
      scrollQueued = false;
      var y = window.scrollY;
      if (state.introDone && !reduced) scrollTarget += (y - lastScrollY) / SCROLL_STEP; // down = forward, up = back
      lastScrollY = y;
      maybeActivate();
      if (pointer) magnet();
    });
  }, { passive: true });

  var resizeT;
  window.addEventListener("resize", function () {
    clearTimeout(resizeT);
    resizeT = setTimeout(function () { layout(); sizeBg(); }, 120);
  });

  var started = false;
  if ("IntersectionObserver" in window) {
    new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        state.visible = en.isIntersecting;
        setBg(en.isIntersecting);
        setRunning(en.isIntersecting);
        if (en.isIntersecting && !started && (en.intersectionRatio >= .4 || en.intersectionRect.height > window.innerHeight * .5)) {
          started = true;
          root.classList.add("is-entered");
          playIntro();
        }
        maybeActivate();
      });
    }, { threshold: [0, .1, .2, .3, .4, .5, .6] }).observe(stage);
  } else {
    state.visible = true; started = true; finishIntro();
  }

  function init() {
    layout(); sizeBg();
    if (reduced) { cards.forEach(function (c) { c.classList.add("is-on", "is-drawn"); }); state.activated = true; }
  }
  // Header height (--hh) and web fonts change sizes: lay out again once they settle
  if (document.readyState === "complete") init(); else window.addEventListener("load", init);
  document.addEventListener("ada:partials-loaded", function () { setTimeout(layout, 50); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { layout(); });
  init();
})();
