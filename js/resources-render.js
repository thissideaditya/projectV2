/* ===================================================================
   ADA LAW Chambers — resources-render.js
   Renders the public Resources page (resources.html), grouping the
   flat list the API returns into Category -> Subcategory sections.
   Mirrors links-render.js's row style for each individual resource.
=================================================================== */
(function () {
  "use strict";

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
  }

  function resourceRow(r) {
    return (
      '<div class="practice-row">' +
        "<div>" +
          '<h3><a href="' + escapeHtml(r.url) + '" target="_blank" rel="noopener">' + escapeHtml(r.title) + "</a></h3>" +
          (r.description ? '<p style="margin:.4rem 0 0;color:var(--ink-700);font-size:.92rem;max-width:60ch;">' + escapeHtml(r.description) + "</p>" : "") +
        "</div>" +
        '<a class="btn btn--sm btn--outline-dark" href="' + escapeHtml(r.url) + '" target="_blank" rel="noopener">Visit &rarr;</a>' +
      "</div>"
    );
  }

  // Groups the flat, already category/subcategory-sorted list from the
  // API into { category: { subcategory-or-"": [resource, ...] } }.
  function groupResources(resources) {
    var groups = {};
    var order = [];
    resources.forEach(function (r) {
      var cat = r.category || "General";
      var sub = r.subcategory || "";
      if (!groups[cat]) { groups[cat] = {}; order.push(cat); }
      if (!groups[cat][sub]) groups[cat][sub] = [];
      groups[cat][sub].push(r);
    });
    return { groups: groups, order: order };
  }

  async function renderResources() {
    var mount = document.getElementById("resources-listing");
    if (!mount) return;

    try {
      var resources = await window.ADA.data.fetchResources();
      if (!resources || resources.length === 0) {
        mount.innerHTML = '<div class="empty-state">No resources published yet. Please check back soon.</div>';
        return;
      }

      var grouped = groupResources(resources);
      var html = "";

      grouped.order.forEach(function (cat) {
        html += '<div style="margin-bottom:3rem;">';
        html += '<h2 class="section-title" style="font-size:1.4rem;margin-bottom:1.25rem;">' + escapeHtml(cat) + "</h2>";

        var subcats = Object.keys(grouped.groups[cat]);
        subcats.forEach(function (sub) {
          if (sub) {
            html += '<span class="eyebrow" style="display:block;margin:1.25rem 0 .5rem;">' + escapeHtml(sub) + "</span>";
          }
          html += '<div class="practice-list">';
          html += grouped.groups[cat][sub].map(resourceRow).join("");
          html += "</div>";
        });

        html += "</div>";
      });

      mount.innerHTML = html;
    } catch (err) {
      mount.innerHTML = '<div class="empty-state">Unable to load resources right now.</div>';
      console.error(err);
    }
  }

  document.addEventListener("DOMContentLoaded", renderResources);
})();
