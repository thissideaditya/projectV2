/* ===================================================================
   ADA LAW Chambers — home-feeds.js
   Home page "Latest Articles" (from gstindia.biz) and "Videos" (the
   latest uploads on the @GSTIndiaBiz YouTube channel), both served by
   /api/external-feeds.php.
   - Articles: index.html ships a static copy that the live list replaces;
     if the API can't be reached the static copy stays.
   - Videos: index.html ships a static copy of the latest uploads too; if
     the API can't be reached it stays (a link to the channel is shown only
     if there is nothing at all).
   Pictures: articles use each article's own share image (og:image, read
   by the API from the article page); videos use their YouTube thumbnail.
=================================================================== */
(function () {
  "use strict";

  var BASE = (typeof API_BASE_URL !== "undefined" ? API_BASE_URL : "/api");
  var SHOW = 3;

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
    var d = new Date(iso + "T00:00:00");
    return isNaN(d) ? "" : d.toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
  }

  var ARROW =
    '<span class="feed-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" ' +
    'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>';

  // Picture = the article's own share image (og:image), read from the article page by the API.
  function articleCard(a) {
    var url = escapeHtml(safeUrl(a.url));
    return (
      '<article class="post-card feed-card">' +
        (a.image ? '<a class="feed-thumb" href="' + url + '" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">' +
                   '<img src="' + escapeHtml(safeUrl(a.image)) + '" alt="" loading="lazy"></a>' : "") +
        '<div class="feed-body">' +
          '<span class="feed-meta">gstindia.biz &middot; ' + formatDate(a.date) + "</span>" +
          '<h3><a href="' + url + '" target="_blank" rel="noopener">' + escapeHtml(a.title) + "</a></h3>" +
          "<p>" + escapeHtml(a.summary) + "</p>" +
          '<a class="feed-more" href="' + url + '" target="_blank" rel="noopener">Read on gstindia.biz' + ARROW + "</a>" +
        "</div>" +
      "</article>"
    );
  }

  // YouTube titles carry channel tags ("Title || By Adv. … || @GSTIndiaBiz"); keep the title itself.
  function videoTitle(t) {
    return String(t || "").split("||")[0].replace(/\*+/g, "").replace(/@\w+/g, "").replace(/\s+/g, " ").replace(/^[\s|-]+|[\s|-]+$/g, "");
  }

  // Picture = the video's YouTube thumbnail (from the API).
  function videoCard(v) {
    return (
      '<a class="video-card feed-card" href="' + escapeHtml(safeUrl(v.url)) + '" target="_blank" rel="noopener">' +
        '<span class="feed-thumb"><img src="' + escapeHtml(safeUrl(v.thumbnail)) + '" alt="" loading="lazy"><span class="video-play" aria-hidden="true"></span></span>' +
        '<span class="feed-body"><span class="feed-meta">YouTube &middot; ' + formatDate(v.date) + "</span>" +
        "<h3>" + escapeHtml(videoTitle(v.title)) + "</h3>" +
        '<span class="feed-more">Watch on YouTube' + ARROW + "</span></span>" +
      "</a>"
    );
  }

  // onFail (optional) runs when the feed can't be loaded; without it the
  // mount's existing content is left as it is.
  function load(feed, mountId, render, onFail) {
    var mount = document.getElementById(mountId);
    if (!mount) return;
    function fail() { if (onFail) onFail(mount); mount.removeAttribute("aria-busy"); }
    if (!window.fetch) return fail();
    var timeout = new Promise(function (_, reject) { setTimeout(function () { reject(new Error("timeout")); }, 12000); });
    Promise.race([fetch(BASE + "/external-feeds.php?feed=" + feed).then(function (r) { return r.ok ? r.json() : null; }), timeout])
      .then(function (data) {
        if (data && data.ok && data.items && data.items.length) {
          mount.innerHTML = data.items.slice(0, SHOW).map(render).join("");
          mount.removeAttribute("aria-busy");
        } else {
          fail();
        }
      })
      .catch(fail);
  }

  // Only when there is nothing to show (index.html normally ships a static copy of the latest videos).
  function videosUnavailable(mount) {
    if (mount.querySelector(".video-card:not(.is-loading)")) return;
    mount.innerHTML =
      '<div class="feed-empty">' +
        '<span class="feed-empty-icon" aria-hidden="true"><svg viewBox="0 0 68 48" width="68" height="48"><rect width="68" height="48" rx="12" fill="currentColor"/><path d="M27 15l18 9-18 9z" fill="#fff"/></svg></span>' +
        '<p>Our latest videos are on YouTube &mdash;<br>watch them at <a href="https://www.youtube.com/@gstindiabiz" target="_blank" rel="noopener">@GSTIndiaBiz &rarr;</a></p>' +
      "</div>";
  }

  function init() {
    load("articles", "home-articles", articleCard);
    load("videos", "home-videos", videoCard, videosUnavailable);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
