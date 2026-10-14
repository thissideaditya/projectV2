/* ===================================================================
   ADA LAW Chambers - posts-render.js
   Renders Supabase/local post data into the Rules, Thoughts and
   single-article pages. Reusable across all three via data-category
   / data-mode attributes set on <body>.
=================================================================== */
(function () {
  "use strict";

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
  }

  function formatDate(iso) {
    try {
      return new Date(iso).toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
    } catch (e) {
      return "";
    }
  }

  function shareButton(url, title) {
    return window.ADA_SHARE ? window.ADA_SHARE.button(url, title) : "";
  }

  function postCard(post, basePath) {
    var label = post.category === "rule" ? "Rules" : "Thoughts";
    return (
      '<article class="post-card">' +
        '<a class="thumb" href="' + basePath + 'post.php?slug=' + encodeURIComponent(post.slug) + '">' +
          '<img src="' + escapeHtml(post.cover_image) + '" alt="' + escapeHtml(post.title) + '" loading="lazy">' +
        "</a>" +
        '<div class="body">' +
          '<span class="meta">' + label + " | " + formatDate(post.created_at) + "</span>" +
          "<h3>" + escapeHtml(post.title) + "</h3>" +
          "<p>" + escapeHtml(post.excerpt) + "</p>" +
          '<div class="card-actions">' +
            '<a class="read-more" href="' + basePath + 'post.php?slug=' + encodeURIComponent(post.slug) + '">Read more &rarr;</a>' +
            shareButton(basePath + 'post.php?slug=' + encodeURIComponent(post.slug), post.title) +
          '</div>' +
        "</div>" +
      "</article>"
    );
  }

  async function renderListing() {
    var mount = document.getElementById("post-listing");
    if (!mount) return;
    var category = mount.getAttribute("data-category");
    var basePath = mount.getAttribute("data-base-path") || "";

    try {
      var posts = await window.ADA.data.fetchPosts(category);
      if (!posts || posts.length === 0) {
        mount.innerHTML = '<div class="empty-state">Nothing published here yet. Please check back soon.</div>';
        return;
      }
      mount.className = "grid post-grid";
      mount.innerHTML = posts.map(function (p) { return postCard(p, basePath); }).join("");
    } catch (err) {
      mount.innerHTML = '<div class="empty-state">Unable to load content right now.</div>';
      console.error(err);
    }
  }

  async function renderArticle() {
    var mount = document.getElementById("article-mount");
    if (!mount) return;

    var params = new URLSearchParams(window.location.search);
    var slug = params.get("slug");
    if (!slug) {
      mount.innerHTML = '<div class="empty-state">No article specified.</div>';
      return;
    }

    try {
      var post = await window.ADA.data.fetchPostBySlug(slug);
      if (!post) {
        mount.innerHTML = '<div class="empty-state">This article could not be found. It may have been unpublished.</div>';
        return;
      }
      document.title = post.title + " - ADA Law Chambers";
      var label = post.category === "rule" ? "Rules" : "Thoughts";
      // Content is authored by the admin via the rich-text editor and
      // saved as HTML (bold/italic/headings/font size), so it's
      // rendered as-is rather than escaped - there's no public
      // submission path into this field.
      var contentHtml = post.content || "";

      mount.innerHTML =
        '<header class="article-header">' +
          '<span class="eyebrow">' + label + " | " + formatDate(post.created_at) + "</span>" +
          "<h1>" + escapeHtml(post.title) + "</h1>" +
          '<div style="margin-top:1rem;">' + shareButton(window.location.href, post.title) + "</div>" +
        "</header>" +
        '<img src="' + escapeHtml(post.cover_image) + '" alt="" style="border-radius:6px;margin-bottom:2.5rem;max-height:420px;object-fit:cover;width:100%;">' +
        '<div class="article-body">' + contentHtml + "</div>";
    } catch (err) {
      mount.innerHTML = '<div class="empty-state">Unable to load this article right now.</div>';
      console.error(err);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    renderListing();
    renderArticle();
  });
})();
