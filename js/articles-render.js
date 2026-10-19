/* ===================================================================
   ADA LAW Chambers — articles-render.js
   Renders the public Articles listing (downloadable PPT/PDF/Doc
   files) on articles.html. Mirrors links-render.js's pattern.
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

  function formatDate(iso) {
    try {
      return new Date(iso).toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
    } catch (e) {
      return "";
    }
  }

  function fileTypeLabel(type) {
    var t = (type || "").toLowerCase();
    if (t === "ppt" || t === "pptx") return "Presentation";
    if (t === "doc" || t === "docx") return "Document";
    if (t === "pdf") return "PDF";
    return t.toUpperCase();
  }

  // PDFs open directly (every browser can render them). Office files
  // (ppt/pptx/doc/docx) can't be viewed by the browser itself, so they're
  // routed through Microsoft's free Office viewer, which needs the file's
  // FULL absolute URL (not a relative path) to fetch and render it.
  function viewerUrl(article) {
    var absoluteUrl = new URL(article.file_url, window.location.href).href;
    var type = (article.file_type || "").toLowerCase();
    if (type === "pdf") return absoluteUrl;
    if (["ppt", "pptx", "doc", "docx"].indexOf(type) !== -1) {
      return "https://view.officeapps.live.com/op/view.aspx?src=" + encodeURIComponent(absoluteUrl);
    }
    return absoluteUrl;
  }

  function articleCard(article) {
    var fileName = (article.file_url || "").split("/").pop() || "download";
    var openUrl = viewerUrl(article);
    return (
      '<article class="post-card">' +
        '<div class="body">' +
          '<span class="meta">' + escapeHtml(fileTypeLabel(article.file_type)) + " &middot; " + formatDate(article.created_at) + "</span>" +
          '<h3><a class="article-title-link" href="' + escapeHtml(safeUrl(openUrl)) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(article.title) + "</a></h3>" +
          (article.description ? "<p>" + escapeHtml(article.description) + "</p>" : "") +
          '<div class="card-actions" style="justify-content:flex-start;">' +
            '<a class="btn btn--sm btn--gold" href="' + escapeHtml(safeUrl(article.file_url)) + '" download="' + escapeHtml(fileName) + '">Download &darr;</a>' +
            (window.ADA_SHARE ? window.ADA_SHARE.button(article.file_url, article.title) : "") +
          '</div>' +
        "</div>" +
      "</article>"
    );
  }

  async function renderArticles() {
    var mount = document.getElementById("articles-listing");
    if (!mount || mount.hasAttribute("data-ssr")) return; // already rendered on the server (inc/site.php)

    try {
      // Never hang on "Loading…": give up after 15s and show a clear message.
      var articles = await Promise.race([
        window.ADA.data.fetchArticles(),
        new Promise(function (_, reject) {
          setTimeout(function () { reject(new Error("Timed out waiting for /api/articles.php")); }, 15000);
        }),
      ]);
      if (!articles || articles.length === 0) {
        mount.innerHTML = '<div class="empty-state">No articles published yet. Please check back soon.</div>';
        return;
      }
      mount.className = "grid post-grid";
      mount.innerHTML = articles.map(articleCard).join("");
    } catch (err) {
      mount.innerHTML = '<div class="empty-state">Unable to load articles right now.</div>';
      console.error(err);
    }
  }

  // Run now if the page has already finished parsing (some hosts/optimisers
  // delay scripts until after DOMContentLoaded, so a listener alone can
  // miss it and leave the page stuck on "Loading…").
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", renderArticles);
  } else {
    renderArticles();
  }
})();
