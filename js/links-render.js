/* ===================================================================
   ADA LAW Chambers — links-render.js
   Renders the public Important Links list on important-links.html.
=================================================================== */
(function () {
  "use strict";

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
  }

  async function renderLinks() {
    var mount = document.getElementById("links-listing");
    if (!mount) return;

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
                '<h3><a href="' + escapeHtml(l.url) + '" target="_blank" rel="noopener">' + escapeHtml(l.title) + '</a></h3>' +
                (l.description ? '<p style="margin:.4rem 0 0;color:var(--ink-700);font-size:.92rem;max-width:60ch;">' + escapeHtml(l.description) + '</p>' : '') +
              '</div>' +
              '<a class="btn btn--sm btn--outline-dark" href="' + escapeHtml(l.url) + '" target="_blank" rel="noopener">Visit &rarr;</a>' +
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
