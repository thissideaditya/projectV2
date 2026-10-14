/* ===================================================================
   ADA LAW Chambers — hostinger-client.js
   ---------------------------------------------------------------
   Talks to the PHP + MySQL backend in /api. This provides the same
   window.ADA.data interface that posts-render.js and admin.js
   already expect (fetchPosts, createPost, signIn, etc.) — so those
   files don't need to change at all; this is a drop-in replacement
   for the old js/supabase-client.js.

   Requires js/api-config.js to be loaded first (defines API_BASE_URL).
=================================================================== */
(function (global) {
  "use strict";

  var BASE = (typeof API_BASE_URL !== "undefined" ? API_BASE_URL : "/api");

  async function request(path, options) {
    options = options || {};
    options.credentials = "include"; // send the admin session cookie
    var res = await fetch(BASE + path, options);
    var body = null;
    try {
      body = await res.json();
    } catch (e) {
      // non-JSON response (unexpected server error page, etc.)
    }
    if (!res.ok || !body || body.ok === false) {
      var msg = (body && body.error) ? body.error : "Request failed (" + res.status + ").";
      throw new Error(msg);
    }
    return body;
  }

  function jsonHeaders() {
    return { "Content-Type": "application/json" };
  }

  var ADA = {
    isSupabaseConfigured: false, // legacy flag some old code may check; always false here

    async fetchPosts(category) {
      var body = await request("/posts.php?category=" + encodeURIComponent(category));
      return body.posts || [];
    },

    async fetchPostBySlug(slug) {
      var body = await request("/posts.php?slug=" + encodeURIComponent(slug));
      return body.post || null;
    },

    async fetchAllPosts(category) {
      var qs = "all=1" + (category ? "&category=" + encodeURIComponent(category) : "");
      var body = await request("/posts.php?" + qs);
      return body.posts || [];
    },

    async fetchPostById(id) {
      var body = await request("/posts.php?all=1&id=" + encodeURIComponent(id));
      return body.post || null;
    },

    async createPost(post) {
      var body = await request("/posts.php", {
        method: "POST",
        headers: jsonHeaders(),
        body: JSON.stringify(post),
      });
      return body.post;
    },

    async updatePost(id, updates) {
      var body = await request("/posts.php?id=" + encodeURIComponent(id), {
        method: "PUT",
        headers: jsonHeaders(),
        body: JSON.stringify(updates),
      });
      return body.post;
    },

    async deletePost(id) {
      await request("/posts.php?id=" + encodeURIComponent(id), { method: "DELETE" });
      return true;
    },

    /** Upload a cover-image File (from an <input type="file">) and get back its URL. */
    async uploadImage(file) {
      var formData = new FormData();
      formData.append("image", file);
      var body = await request("/upload-image.php", { method: "POST", body: formData });
      return body.url;
    },

    async signIn(email, password) {
      var body = await request("/auth.php?action=login", {
        method: "POST",
        headers: jsonHeaders(),
        body: JSON.stringify({ email: email, password: password }),
      });
      return body.user;
    },

    async signOut() {
      await request("/auth.php?action=logout", { method: "POST" });
    },

    async getSession() {
      var body = await request("/auth.php?action=check");
      return body.user || null;
    },

    // ---------------------------------------------------------------
    // Contact messages & Careers applications (admin-only reads)
    // ---------------------------------------------------------------
    async fetchMessages() {
      var body = await request("/messages.php");
      return body.messages || [];
    },

    async fetchApplications() {
      var body = await request("/applications.php");
      return body.applications || [];
    },

    /** Direct download URL for a resume (used as an <a href>, not fetched via JS). */
    resumeDownloadUrl(applicationId) {
      return BASE + "/download-resume.php?id=" + encodeURIComponent(applicationId);
    },

    // ---------------------------------------------------------------
    // Important Links
    // ---------------------------------------------------------------
    async fetchLinks() {
      var body = await request("/links.php");
      return body.links || [];
    },

    async createLink(link) {
      var body = await request("/links.php", {
        method: "POST",
        headers: jsonHeaders(),
        body: JSON.stringify(link),
      });
      return body.link;
    },

    async updateLink(id, updates) {
      var body = await request("/links.php?id=" + encodeURIComponent(id), {
        method: "PUT",
        headers: jsonHeaders(),
        body: JSON.stringify(updates),
      });
      return body.link;
    },

    async deleteLink(id) {
      await request("/links.php?id=" + encodeURIComponent(id), { method: "DELETE" });
      return true;
    },

    // ---------------------------------------------------------------
    // Articles (PPT/PDF/Doc downloads)
    // ---------------------------------------------------------------
    async fetchArticles() {
      var body = await request("/articles.php");
      return body.articles || [];
    },

    async fetchArticleBySlug(slug) {
      var body = await request("/articles.php?slug=" + encodeURIComponent(slug));
      return body.article || null;
    },

    async fetchAllArticles() {
      var body = await request("/articles.php?all=1");
      return body.articles || [];
    },

    async fetchArticleById(id) {
      var body = await request("/articles.php?all=1&id=" + encodeURIComponent(id));
      return body.article || null;
    },

    async createArticle(article) {
      var body = await request("/articles.php", {
        method: "POST",
        headers: jsonHeaders(),
        body: JSON.stringify(article),
      });
      return body.article;
    },

    async updateArticle(id, updates) {
      var body = await request("/articles.php?id=" + encodeURIComponent(id), {
        method: "PUT",
        headers: jsonHeaders(),
        body: JSON.stringify(updates),
      });
      return body.article;
    },

    async deleteArticle(id) {
      await request("/articles.php?id=" + encodeURIComponent(id), { method: "DELETE" });
      return true;
    },

    /** Upload an Article/PPT/PDF/Doc File and get back its URL + detected type. */
    async uploadArticleFile(file) {
      var formData = new FormData();
      formData.append("file", file);
      var body = await request("/upload-file.php", { method: "POST", body: formData });
      return { url: body.url, file_type: body.file_type };
    },

    // ---------------------------------------------------------------
    // Resources (category/subcategory grouped links)
    // ---------------------------------------------------------------
    async fetchResources() {
      var body = await request("/resources.php");
      return body.resources || [];
    },

    async createResource(resource) {
      var body = await request("/resources.php", {
        method: "POST",
        headers: jsonHeaders(),
        body: JSON.stringify(resource),
      });
      return body.resource;
    },

    async updateResource(id, updates) {
      var body = await request("/resources.php?id=" + encodeURIComponent(id), {
        method: "PUT",
        headers: jsonHeaders(),
        body: JSON.stringify(updates),
      });
      return body.resource;
    },

    async deleteResource(id) {
      await request("/resources.php?id=" + encodeURIComponent(id), { method: "DELETE" });
      return true;
    },
  };

  global.ADA = global.ADA || {};
  global.ADA.data = ADA;
})(window);
