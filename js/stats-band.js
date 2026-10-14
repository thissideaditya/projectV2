/* ===================================================================
   ADA LAW Chambers — stats-band.js
   Home page stats band (20+ / 10+ / 2 / 3). The entrance timeline lives
   in css/style.css ("Stats band"); this script:
     - sets .stats-anim (hidden start state) straight away,
     - adds .is-in when the band scrolls into view (CSS runs the gold
       line, divider flashes, light-sweep reveals and portico fade),
     - adds .is-done once it has finished (drifting glow, hover),
     - moves the portico backdrop slightly slower than the page on
       scroll (parallax).
   Visitors who ask for reduced motion (and browsers without
   IntersectionObserver) get the finished, static band.
=================================================================== */
(function () {
  "use strict";

  var DONE_MS = 2100;      // last label finishes at ~1.8s
  var PARALLAX = 0.18;     // backdrop lags the scroll by this fraction

  function init() {
    var band = document.querySelector(".stats-band");
    if (!band) return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) {
      band.classList.add("is-done");
      return;
    }

    // Hide instantly (no fade-out), then re-enable transitions for the entrance
    band.classList.add("stats-anim", "stats-prep");
    void band.offsetWidth;
    band.classList.remove("stats-prep");

    var io = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) return;
      io.disconnect();
      band.classList.add("is-in");
      setTimeout(function () { band.classList.add("is-done"); }, DONE_MS);
    }, { threshold: 0.35 });
    io.observe(band);

    // Parallax: shift the portico against the scroll so it moves slower than the stats
    var arch = band.querySelector(".stats-arch");
    if (!arch) return;
    var queued = false;
    function update() {
      queued = false;
      var r = band.getBoundingClientRect();
      if (r.bottom < 0 || r.top > window.innerHeight) return;
      var fromCentre = r.top + r.height / 2 - window.innerHeight / 2;
      arch.style.transform = "translate3d(0," + (-fromCentre * PARALLAX).toFixed(1) + "px,0)";
    }
    window.addEventListener("scroll", function () {
      if (!queued) { queued = true; requestAnimationFrame(update); }
    }, { passive: true });
    window.addEventListener("resize", update);
    update();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
