/* ===================================================================
   ADA LAW Chambers — magazines-render.js
   Renders uploaded magazine issues (PDF + optional cover picture),
   managed from admin/magazines.html:
   - magazines.html:  every published issue      (#magazines-listing)
   - index.html:      the 3 newest issues        (#home-magazines)
   Issues without a cover picture get a styled placeholder cover
   showing the title. Mirrors articles-render.js's pattern.
=================================================================== */
(function () {
  "use strict";

  var HOME_COUNT = 6;

  // Escapes text for HTML, including inside attribute values ("…").
  function escapeHtml(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  // Only web, mail and phone links (or this site's own paths) may become an
  // href/src; anything else (javascript:, data:, ...) is replaced with "#".
  function safeUrl(url) {
    var u = String(url == null ? "" : url).trim();
    if (/^(https?:|mailto:|tel:)/i.test(u) || /^(\/(?!\/)|\.{1,2}\/|[\w\-.~%]+(\/|\?|#|$))/.test(u) && u.indexOf(":") === -1) return u;
    return "#";
  }

  function formatDate(value) {
    // MySQL DATETIME ("2026-10-16 09:30:00") isn't parsed by every browser without the "T".
    var d = new Date(String(value || "").replace(" ", "T"));
    return isNaN(d) ? "" : d.toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
  }

  var ARROW =
    '<span class="feed-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" ' +
    'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>';

  var DOWN =
    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" ' +
    'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v15M6 13l6 6 6-6"/></svg>';

  function cover(m) {
    if (m.cover_image) {
      return '<img src="' + escapeHtml(safeUrl(m.cover_image)) + '" alt="Cover of ' + escapeHtml(m.title) + '" loading="lazy">';
    }
    return (
      '<span class="magazine-cover-ph">' +
        '<span class="magazine-cover-brand">ADA Law Chambers</span>' +
        '<strong>' + escapeHtml(m.title) + "</strong>" +
        (m.issue ? '<span class="magazine-cover-issue">' + escapeHtml(m.issue) + "</span>" : "") +
      "</span>"
    );
  }

  function magazineCard(m) {
    // PDFs open in the browser's own viewer; file_url is served by api/media.php.
    var url = escapeHtml(safeUrl(m.file_url));
    var fileName = escapeHtml(((m.title || "magazine").replace(/[^\w\- ]+/g, "").trim() || "magazine") + ".pdf");
    // The issue label ("October 2026 · Vol. 3") already dates it; fall back to the upload date.
    var meta = escapeHtml(m.issue || formatDate(m.created_at));
    return (
      '<article class="feed-card magazine-card">' +
        '<a class="magazine-cover" href="' + url + '" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">' + cover(m) + "</a>" +
        '<div class="feed-body">' +
          (meta ? '<span class="feed-meta">' + meta + "</span>" : "") +
          '<h3><a href="' + url + '" target="_blank" rel="noopener">' + escapeHtml(m.title) + "</a></h3>" +
          (m.description ? "<p>" + escapeHtml(m.description) + "</p>" : "") +
          '<div class="magazine-actions">' +
            '<a class="btn btn--sm btn--outline-dark magazine-download" href="' + url + '" download="' + fileName + '">Download' + DOWN + "</a>" +
            (window.ADA_SHARE ? window.ADA_SHARE.button(m.file_url, m.title) : "") +
          "</div>" +
          '<a class="feed-more" href="' + url + '" target="_blank" rel="noopener">Read Magazine' + ARROW + "</a>" +
        "</div>" +
      "</article>"
    );
  }

  // Home section: the cards scroll sideways (CSS scroll-snap); the arrows move
  // one card at a time and there is one dot per scroll stop. Controls stay
  // hidden while every card already fits on screen.
  function carousel(track) {
    var wrap = track.closest(".mag-carousel");
    if (!wrap) return;
    var prev = wrap.querySelector(".mag-nav--prev");
    var next = wrap.querySelector(".mag-nav--next");
    var dotsBox = wrap.parentNode.querySelector(".mag-dots");
    var cards = track.querySelectorAll(".magazine-card");
    if (!cards.length) return;

    function step() {
      return cards.length > 1 ? cards[1].offsetLeft - cards[0].offsetLeft : track.clientWidth;
    }
    function stops() {
      return Math.max(1, Math.round((track.scrollWidth - track.clientWidth) / step()) + 1);
    }
    function current() {
      return Math.min(stops() - 1, Math.round(track.scrollLeft / step()));
    }
    function go(i) {
      track.scrollTo({ left: i * step(), behavior: "smooth" });
    }

    function update() {
      var n = stops(), i = current();
      var on = n > 1;
      prev.hidden = next.hidden = dotsBox.hidden = !on;
      prev.disabled = i <= 0;
      next.disabled = i >= n - 1;
      if (dotsBox.children.length !== n) {
        dotsBox.innerHTML = "";
        for (var d = 0; d < n; d++) {
          var b = document.createElement("button");
          b.type = "button";
          b.setAttribute("aria-label", "Show magazine " + (d + 1));
          b.addEventListener("click", go.bind(null, d));
          dotsBox.appendChild(b);
        }
      }
      for (var k = 0; k < dotsBox.children.length; k++) {
        dotsBox.children[k].setAttribute("aria-current", k === i ? "true" : "false");
      }
    }

    prev.addEventListener("click", function () { go(current() - 1); });
    next.addEventListener("click", function () { go(current() + 1); });
    var raf;
    function queue() { cancelAnimationFrame(raf); raf = requestAnimationFrame(update); }
    track.addEventListener("scroll", queue, { passive: true });
    window.addEventListener("resize", queue);
    update();
  }

  async function render(mount, limit, emptyHtml) {
    if (mount.hasAttribute("data-ssr")) return carousel(mount); // already rendered on the server (inc/site.php)
    try {
      // Never hang on "Loading…": give up after 15s and show a clear message.
      var magazines = await Promise.race([
        window.ADA.data.fetchMagazines(limit),
        new Promise(function (_, reject) {
          setTimeout(function () { reject(new Error("Timed out waiting for /api/magazines.php")); }, 15000);
        }),
      ]);
      if (!magazines || magazines.length === 0) {
        mount.innerHTML = emptyHtml;
        return;
      }
      mount.className = "grid post-grid feed-grid magazine-grid";
      mount.innerHTML = magazines.map(magazineCard).join("");
      carousel(mount);
    } catch (err) {
      mount.innerHTML = '<div class="empty-state">Unable to load magazines right now.</div>';
      console.error(err);
    } finally {
      mount.removeAttribute("aria-busy");
    }
  }

  function init() {
    var page = document.getElementById("magazines-listing");
    if (page) {
      render(page, 0, '<div class="empty-state">No magazines published yet. Please check back soon.</div>');
    }
    var home = document.getElementById("home-magazines");
    if (home) {
      render(home, HOME_COUNT, '<div class="empty-state">Our first issue is on its way. Please check back soon.</div>');
    }
  }

  // Run now if the page has already finished parsing (some hosts/optimisers
  // delay scripts until after DOMContentLoaded, so a listener alone can
  // miss it and leave the page stuck on "Loading…").
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
