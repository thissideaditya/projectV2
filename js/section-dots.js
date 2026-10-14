/* ===================================================================
   ADA LAW Chambers — section-dots.js
   Home page section navigation, for every element marked
   data-dot="Label" in index.html:
   - A rail of dots on the right; clicking a dot jumps to its section.
   - Auto-advance: after ADVANCE_MS with no scrolling/clicking/typing the
     page moves to the next section; the line under the current dot
     fills up as that time runs out. After the last section it goes
     back to the top. Pauses while the disclaimer is open or the tab
     is hidden.
   Scrolling itself is the browser's normal scrolling. Visitors who ask
   for reduced motion get instant jumps and no auto-advance.
=================================================================== */
(function () {
  "use strict";

  var ADVANCE_MS = 10000;

  function init() {
    var sections = Array.prototype.slice.call(document.querySelectorAll("[data-dot]"));
    if (sections.length < 2) return;

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ---------- Rail ---------- */
    var rail = document.createElement("nav");
    rail.className = "section-dots";
    rail.setAttribute("aria-label", "Page sections");

    var dots = [], lines = [];
    sections.forEach(function (sec, i) {
      if (i > 0) {
        var line = document.createElement("div");
        line.className = "section-dots__line";
        line.innerHTML = "<i></i>";
        rail.appendChild(line);
        lines.push(line);
      }
      var dot = document.createElement("button");
      dot.type = "button";
      dot.className = "section-dots__dot";
      dot.setAttribute("aria-label", sec.dataset.dot);
      dot.innerHTML = "<span>" + sec.dataset.dot + "</span>";
      dot.addEventListener("click", function () { scrollToY(sectionTop(i)); });
      rail.appendChild(dot);
      dots.push(dot);
    });
    document.body.appendChild(rail);

    /* ---------- Geometry ----------
       Section positions are measured once and re-measured only when the
       page's size changes, so scrolling never forces a layout. */
    var tops = null, headerH = 0;
    function measure() {
      var h = document.querySelector(".site-header");
      headerH = h ? h.offsetHeight : 0;
      tops = sections.map(function (sec, i) {
        return i === 0 ? 0 : Math.round(sec.getBoundingClientRect().top + window.scrollY - headerH);
      });
    }
    function invalidate() { tops = null; }
    window.addEventListener("resize", invalidate);
    if ("ResizeObserver" in window) new ResizeObserver(invalidate).observe(document.body);
    else setInterval(invalidate, 1000);

    function headerHeight() { if (!tops) measure(); return headerH; }
    function maxY() { return document.documentElement.scrollHeight - window.innerHeight; }
    // Scroll position at which section i sits just under the sticky header
    function sectionTop(i) { if (!tops) measure(); return tops[i]; }
    // Current section: the last one whose top is at/above the header line
    // (with some slack, since a smooth scroll can settle a few px short of its target)
    function snapIndex() {
      var y = window.scrollY + 40, idx = 0;
      for (var i = 0; i < sections.length; i++) if (sectionTop(i) <= y) idx = i;
      return idx;
    }
    // Section highlighted on the rail (the page bottom counts as the last section)
    function railIndex() {
      return window.scrollY >= maxY() - 4 ? sections.length - 1 : snapIndex();
    }

    /* While the page is moving, html.is-scrolling switches off pointer
       events (css/style.css), so cards sliding under a still mouse don't
       fire hover effects — the card focus-expand in particular re-lays-out
       whole grids — mid-scroll. */
    var root = document.documentElement, scrollIdle = null;
    window.addEventListener("scroll", function () {
      if (!root.classList.contains("is-scrolling")) root.classList.add("is-scrolling");
      clearTimeout(scrollIdle);
      scrollIdle = setTimeout(function () { root.classList.remove("is-scrolling"); }, 150);
    }, { passive: true });

    /* ---------- Scrolling ---------- */
    var animating = false, settleTimer = null;
    function scrollToY(y) {
      y = Math.max(0, Math.min(maxY(), Math.round(y)));
      startedAt = performance.now();
      if (Math.abs(y - window.scrollY) < 2) return;
      animating = true;
      window.scrollTo({ top: y, behavior: reduced ? "auto" : "smooth" });
      clearTimeout(settleTimer);
      settleTimer = setTimeout(function () { animating = false; }, reduced ? 50 : 1100);
    }
    if ("onscrollend" in window) {
      window.addEventListener("scrollend", function () { clearTimeout(settleTimer); animating = false; });
    }

    /* ---------- Rail state + auto-advance ---------- */
    var current = -1, startedAt = performance.now();

    function setCurrent(i) {
      if (i === current) return;
      current = i;
      startedAt = performance.now();
      dots.forEach(function (d, j) {
        d.classList.toggle("is-active", j === i);
        d.classList.toggle("is-done", j < i);
        if (j === i) d.setAttribute("aria-current", "true"); else d.removeAttribute("aria-current");
      });
      lines.forEach(function (l, j) {
        l.classList.toggle("is-done", j < i);
        l.style.setProperty("--p", j < i ? 1 : 0);
      });
    }

    // The visitor taking over restarts the countdown
    ["wheel", "touchstart", "keydown", "pointerdown"].forEach(function (ev) {
      window.addEventListener(ev, function () { startedAt = performance.now(); }, { passive: true });
    });
    var railQueued = false;
    window.addEventListener("scroll", function () {
      if (railQueued) return;
      railQueued = true;
      requestAnimationFrame(function () { railQueued = false; setCurrent(railIndex()); });
    }, { passive: true });
    setCurrent(railIndex());

    (function tick(now) {
      if (reduced || animating || document.hidden || document.body.classList.contains("disclaimer-active")) startedAt = now;
      var line = lines[current]; // the line below the current dot
      if (line) {
        var p = Math.min(1, (now - startedAt) / ADVANCE_MS);
        line.style.setProperty("--p", p.toFixed(4));
        if (p >= 1) scrollToY(sectionTop(current + 1));
      } else if (current === sections.length - 1 && now - startedAt >= ADVANCE_MS) {
        scrollToY(0); // end of the page: start over from the top
      }
      requestAnimationFrame(tick);
    })(performance.now());
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
