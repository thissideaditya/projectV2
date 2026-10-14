/* ===================================================================
   ADA LAW Chambers — partials.js
   Loads the shared header and footer from /partials/ so every page
   stays in sync automatically. To change the header or footer on
   every page at once, edit ONLY these files:
     partials/header.html
     partials/footer.html
     partials/disclaimer.html   (the first-visit disclaimer gate)
   To use one on a page, add its slot where it should appear:
     <div id="site-header-slot"></div>
     <div id="site-footer-slot"></div>
     <div id="site-disclaimer-slot"></div>
   Nothing else needs to change — every public page pulls from them.
=================================================================== */
(function () {
  "use strict";

  function inject(placeholderId, url) {
    var el = document.getElementById(placeholderId);
    if (!el) return Promise.resolve(); // page doesn't use this slot — skip quietly

    return fetch(url)
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.text();
      })
      .then(function (html) {
        el.outerHTML = html;
      })
      .catch(function (err) {
        console.error("[partials.js] could not load " + url + ": " + err.message);
        // Leave the empty slot in place rather than breaking the page.
      });
  }

  // If the disclaimer slot is on this page and it hasn't been accepted yet, lock
  // the page right away so nothing is clickable while the partial downloads.
  try {
    if (document.getElementById("site-disclaimer-slot") &&
        sessionStorage.getItem("ada_disclaimer_agreed") !== "true") {
      document.body.classList.add("disclaimer-active");
    }
  } catch (e) {}

  Promise.all([
    inject("site-disclaimer-slot", "partials/disclaimer.html?v=" + Date.now()),
    inject("site-header-slot", "partials/header.html?v=" + Date.now()),
    inject("site-footer-slot", "partials/footer.html?v=" + Date.now()),
  ]).then(function () {
    // main.js listens for this to run nav toggling, active-link
    // highlighting, and the footer year — all of which need the
    // real header/footer markup to exist in the page first.
    document.dispatchEvent(new Event("ada:partials-loaded"));
  });
})();
