/* ===================================================================
   ADA LAW Chambers — links-render.js
   Renders the public Important Links list on important-links.html.
=================================================================== */
(function () {
  "use strict";

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

  async function renderLinks() {
    var mount = document.getElementById("links-listing");
    if (!mount || mount.hasAttribute("data-ssr")) return; // already rendered on the server (inc/site.php)

    try {
      var links = await window.ADA.data.fetchLinks();
      if (!links || links.length === 0) {
        mount.innerHTML = '<div class="empty-state">No links published yet. Please check back soon.</div>';
        return;
      }

      mount.innerHTML =
        '<div class="practice-list">' +
        links.map(function (l) {
          return (
            '<div class="practice-row">' +
              '<div>' +
                '<h3><a href="' + escapeHtml(safeUrl(l.url)) + '" target="_blank" rel="noopener">' + escapeHtml(l.title) + '</a></h3>' +
                (l.description ? '<p style="margin:.4rem 0 0;color:var(--ink-700);font-size:.92rem;max-width:60ch;">' + escapeHtml(l.description) + '</p>' : '') +
              '</div>' +
              '<a class="btn btn--sm btn--outline-dark" href="' + escapeHtml(safeUrl(l.url)) + '" target="_blank" rel="noopener">Visit &rarr;</a>' +
            '</div>'
          );
        }).join("") +
        '</div>';
    } catch (err) {
      mount.innerHTML = '<div class="empty-state">Unable to load links right now.</div>';
      console.error(err);
    }
  }

  document.addEventListener("DOMContentLoaded", renderLinks);
})();
