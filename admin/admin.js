/* ===================================================================
   ADA LAW Chambers — admin.js
   Handles login, route-guarding, and the create/edit/delete flows
   for Rules & Thoughts posts. Talks only to window.ADA.data, so it
   works identically against the local mock store or real Supabase.
=================================================================== */
(function () {
  "use strict";

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
  }

  /* -------------------------------------------------------------
     LOGIN PAGE (admin/index.html)
  ------------------------------------------------------------- */
  function initLoginPage() {
    var form = document.getElementById("login-form");
    if (!form) return;

    // If already signed in, skip straight to dashboard.
    window.ADA.data.getSession().then(function (session) {
      if (session) window.location.href = "dashboard.html";
    });

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      var email = document.getElementById("email").value.trim();
      var password = document.getElementById("password").value;
      var errorBox = document.getElementById("login-error");
      errorBox.hidden = true;

      try {
        await window.ADA.data.signIn(email, password);
        window.location.href = "dashboard.html";
      } catch (err) {
        errorBox.textContent = err.message || "Unable to sign in.";
        errorBox.hidden = false;
      }
    });
  }

  /* -------------------------------------------------------------
     ROUTE GUARD — used by dashboard.html and editor.html
  ------------------------------------------------------------- */
  async function requireAuth() {
    var session = await window.ADA.data.getSession();
    if (!session) {
      window.location.href = "index.html";
      return null;
    }
    var who = document.getElementById("admin-user-label");
    if (who) who.textContent = session.email || "Admin";
    return session;
  }

  function initLogout() {
    document.querySelectorAll("[data-logout]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        await window.ADA.data.signOut();
        window.location.href = "index.html";
      });
    });
  }

  /* -------------------------------------------------------------
     DASHBOARD (admin/dashboard.html) — list, filter, delete
  ------------------------------------------------------------- */
  function initDashboard() {
    var table = document.getElementById("posts-table-body");
    if (!table) return;

    requireAuth().then(function (session) {
      if (session) loadPosts();
    });

    var filterTabs = document.querySelectorAll("[data-filter]");
    var activeFilter = "";
    filterTabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        filterTabs.forEach(function (t) { t.classList.remove("active"); });
        tab.classList.add("active");
        activeFilter = tab.getAttribute("data-filter");
        loadPosts();
      });
    });

    async function loadPosts() {
      table.innerHTML = '<tr><td colspan="5">Loading…</td></tr>';
      try {
        var posts = await window.ADA.data.fetchAllPosts(activeFilter || undefined);
        if (!posts.length) {
          table.innerHTML = '<tr><td colspan="5">No posts yet. Click "New Post" to add one.</td></tr>';
          return;
        }
        table.innerHTML = posts
          .map(function (p) {
            return (
              "<tr>" +
                "<td>" + escapeHtml(p.title) + "</td>" +
                "<td>" + (p.category === "rule" ? "Rules" : "Thoughts") + "</td>" +
                '<td><span class="status-pill ' + p.status + '">' + p.status + "</span></td>" +
                "<td>" + new Date(p.created_at).toLocaleDateString("en-IN") + "</td>" +
                '<td style="white-space:nowrap;">' +
                  '<a class="btn btn--sm btn--outline-dark" href="editor.html?id=' + encodeURIComponent(p.id) + '">Edit</a> ' +
                  '<button class="btn btn--sm btn--danger" data-delete="' + p.id + '">Delete</button>' +
                "</td>" +
              "</tr>"
            );
          })
          .join("");

        table.querySelectorAll("[data-delete]").forEach(function (btn) {
          btn.addEventListener("click", async function () {
            if (!confirm("Delete this post permanently?")) return;
            await window.ADA.data.deletePost(btn.getAttribute("data-delete"));
            loadPosts();
          });
        });
      } catch (err) {
        table.innerHTML = '<tr><td colspan="5">Could not load posts.</td></tr>';
        console.error(err);
      }
    }
  }

  /* -------------------------------------------------------------
     RICH TEXT EDITOR TOOLBAR — wires the Bold/Italic/H1-H3/font-size
     controls in editor.html to the #content contenteditable div via
     document.execCommand. Old-school but dependency-free and works
     the same in every browser without pulling in a JS library.
  ------------------------------------------------------------- */
  function initRichTextToolbar() {
    var toolbar = document.getElementById("content-toolbar");
    var editor = document.getElementById("content");
    if (!toolbar || !editor) return;

    if (!document.queryCommandSupported || !document.execCommand) {
      console.error("[editor] document.execCommand isn't available in this browser — the formatting toolbar can't work here.");
      return;
    }

    // Continuously track the user's text selection while it's inside
    // the editor. Clicking a toolbar button can otherwise collapse
    // or lose the browser's live selection before execCommand runs
    // (this is inconsistent across browsers) — so instead of hoping
    // the selection survives the click, we explicitly snapshot it
    // here and re-apply it right before every command below.
    var savedRange = null;

    function saveSelectionIfInEditor() {
      var sel = window.getSelection();
      if (sel && sel.rangeCount > 0 && editor.contains(sel.anchorNode)) {
        savedRange = sel.getRangeAt(0).cloneRange();
      }
    }

    function restoreSelection() {
      editor.focus();
      if (!savedRange) return;
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }

    document.addEventListener("selectionchange", saveSelectionIfInEditor);

    // Belt-and-suspenders: also stop these elements from stealing
    // focus/selection on the way down, where the browser supports it.
    toolbar.addEventListener("mousedown", function (e) {
      if (e.target.closest(".rte-btn") || e.target.tagName === "SELECT") e.preventDefault();
    });

    toolbar.addEventListener("click", function (e) {
      var btn = e.target.closest(".rte-btn");
      if (!btn) return;
      restoreSelection();

      if (btn.dataset.cmd) {
        document.execCommand(btn.dataset.cmd, false, null);
      } else if (btn.dataset.block) {
        document.execCommand("formatBlock", false, btn.dataset.block === "P" ? "P" : btn.dataset.block);
      }
      saveSelectionIfInEditor();
      syncToolbarState();
    });

    var sizeSelect = document.getElementById("content-font-size");
    if (sizeSelect) {
      sizeSelect.addEventListener("mousedown", function (e) { e.stopPropagation(); });
      sizeSelect.addEventListener("change", function () {
        var size = sizeSelect.value;
        sizeSelect.value = "";
        if (!size) return;
        restoreSelection();
        // execCommand only supports the legacy 1-7 <font size> scale,
        // so apply a placeholder size (7) then swap the resulting
        // <font> tags for a <span style="font-size:..."> instead.
        document.execCommand("fontSize", false, "7");
        editor.querySelectorAll('font[size="7"]').forEach(function (el) {
          var span = document.createElement("span");
          span.style.fontSize = size;
          span.innerHTML = el.innerHTML;
          el.replaceWith(span);
        });
        saveSelectionIfInEditor();
      });
    }

    var fontPicker = document.getElementById("content-font-picker");
    if (fontPicker) {
      var fontTrigger = document.getElementById("content-font-trigger");
      var fontTriggerLabel = document.getElementById("content-font-trigger-label");
      var fontMenu = document.getElementById("content-font-menu");

      var closeFontMenu = function () {
        fontMenu.hidden = true;
        fontTrigger.setAttribute("aria-expanded", "false");
      };

      fontTrigger.addEventListener("mousedown", function (e) { e.preventDefault(); });
      fontTrigger.addEventListener("click", function () {
        var isOpen = !fontMenu.hidden;
        closeFontMenu();
        if (!isOpen) {
          fontMenu.hidden = false;
          fontTrigger.setAttribute("aria-expanded", "true");
        }
      });

      // mousedown (not click) so the editor's text selection survives
      // the click on the menu — execCommand needs that selection intact.
      fontMenu.addEventListener("mousedown", function (e) { e.preventDefault(); });

      fontMenu.addEventListener("click", function (e) {
        var opt = e.target.closest(".rte-font-option");
        closeFontMenu();
        if (!opt) return;
        var family = opt.dataset.value;
        fontTriggerLabel.textContent = opt.textContent;
        restoreSelection();
        // execCommand("fontName") wraps the selection in <font face="...">
        // — swap that for a <span style="font-family:..."> so the saved
        // HTML is clean and the font stack (with fallbacks) is preserved.
        document.execCommand("fontName", false, family);
        editor.querySelectorAll("font[face]").forEach(function (el) {
          var span = document.createElement("span");
          span.style.fontFamily = family;
          span.innerHTML = el.innerHTML;
          el.replaceWith(span);
        });
        saveSelectionIfInEditor();
      });

      document.addEventListener("click", function (e) {
        if (!fontPicker.contains(e.target)) closeFontMenu();
      });
    }

    function syncToolbarState() {
      toolbar.querySelectorAll("[data-cmd]").forEach(function (btn) {
        var isActive = false;
        try { isActive = document.queryCommandState(btn.dataset.cmd); } catch (e) {}
        btn.classList.toggle("active", isActive);
      });
    }

    editor.addEventListener("keyup", syncToolbarState);
    editor.addEventListener("mouseup", syncToolbarState);
  }

  /* -------------------------------------------------------------
     EDITOR (admin/editor.html) — create or update a post
  ------------------------------------------------------------- */
  function initEditor() {
    var form = document.getElementById("post-form");
    if (!form) return;

    initRichTextToolbar();
    var contentEditor = document.getElementById("content");

    var params = new URLSearchParams(window.location.search);
    var editId = params.get("id");
    var heading = document.getElementById("editor-heading");

    requireAuth().then(async function (session) {
      if (!session) return;
      if (editId) {
        heading.textContent = "Edit Post";
        var post = await window.ADA.data.fetchPostById(editId);
        if (post) {
          document.getElementById("title").value = post.title || "";
          document.getElementById("category").value = post.category || "rule";
          document.getElementById("excerpt").value = post.excerpt || "";
          if (contentEditor) contentEditor.innerHTML = post.content || "";
          document.getElementById("cover_image").value = post.cover_image || "";
          document.getElementById("status").value = post.status || "draft";
        }
      } else {
        heading.textContent = "New Post";
      }
    });

    var fileInput = document.getElementById("cover_image_file");
    var preview = document.getElementById("cover_image_preview");
    if (fileInput && preview) {
      fileInput.addEventListener("change", function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) { preview.hidden = true; return; }
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
      });
    }

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      var saveBtn = document.getElementById("save-btn");

      var contentHtml = contentEditor ? contentEditor.innerHTML.trim() : "";
      if (!contentHtml || contentEditor.textContent.trim() === "") {
        alert("Please write some content for the post before saving.");
        return;
      }

      saveBtn.disabled = true;

      var coverImage = document.getElementById("cover_image").value.trim();
      var selectedFile = fileInput && fileInput.files && fileInput.files[0];

      try {
        if (selectedFile) {
          saveBtn.textContent = "Uploading image…";
          coverImage = await window.ADA.data.uploadImage(selectedFile);
        }

        var payload = {
          title: document.getElementById("title").value.trim(),
          category: document.getElementById("category").value,
          excerpt: document.getElementById("excerpt").value.trim(),
          content: contentHtml,
          cover_image: coverImage || "https://picsum.photos/seed/" + Date.now() + "/800/500",
          status: document.getElementById("status").value,
        };

        saveBtn.textContent = "Saving…";
        if (editId) {
          await window.ADA.data.updatePost(editId, payload);
        } else {
          await window.ADA.data.createPost(payload);
        }
        window.location.href = "dashboard.html";
      } catch (err) {
        alert("Could not save post: " + err.message);
        saveBtn.disabled = false;
        saveBtn.textContent = "Save Post";
      }
    });
  }

  /* -------------------------------------------------------------
     MESSAGES (admin/messages.html) — read-only list
  ------------------------------------------------------------- */
  function initMessages() {
    var table = document.getElementById("messages-table-body");
    if (!table) return;

    requireAuth().then(async function (session) {
      if (!session) return;
      try {
        var messages = await window.ADA.data.fetchMessages();
        if (!messages.length) {
          table.innerHTML = '<tr><td colspan="4">No messages yet.</td></tr>';
          return;
        }
        table.innerHTML = messages.map(function (m) {
          return (
            "<tr>" +
              "<td><strong>" + escapeHtml(m.name) + "</strong><br><span style=\"color:var(--ink-500);font-size:.82rem;\">" + escapeHtml(m.email) + (m.phone ? " &middot; " + escapeHtml(m.phone) : "") + "</span></td>" +
              "<td>" + escapeHtml(m.subject || "&mdash;") + "</td>" +
              "<td style=\"max-width:340px;white-space:pre-wrap;\">" + escapeHtml(m.message) + "</td>" +
              "<td style=\"white-space:nowrap;\">" + new Date(m.created_at).toLocaleString("en-IN") + "</td>" +
            "</tr>"
          );
        }).join("");
      } catch (err) {
        table.innerHTML = '<tr><td colspan="4">Could not load messages.</td></tr>';
        console.error(err);
      }
    });
  }

  /* -------------------------------------------------------------
     APPLICATIONS (admin/applications.html) — read-only list + resume download
  ------------------------------------------------------------- */
  function initApplications() {
    var table = document.getElementById("applications-table-body");
    if (!table) return;

    var allApplications = [];
    var activeFilter = "";

    requireAuth().then(async function (session) {
      if (!session) return;
      try {
        allApplications = await window.ADA.data.fetchApplications();
        render();
      } catch (err) {
        table.innerHTML = '<tr><td colspan="5">Could not load applications.</td></tr>';
        console.error(err);
      }
    });

    document.querySelectorAll("[data-filter]").forEach(function (tab) {
      tab.addEventListener("click", function () {
        document.querySelectorAll("[data-filter]").forEach(function (t) { t.classList.remove("active"); });
        tab.classList.add("active");
        activeFilter = tab.getAttribute("data-filter");
        render();
      });
    });

    function render() {
      var list = activeFilter
        ? allApplications.filter(function (a) { return a.application_type === activeFilter; })
        : allApplications;

      if (!list.length) {
        table.innerHTML = '<tr><td colspan="5">No applications yet.</td></tr>';
        return;
      }

      table.innerHTML = list.map(function (a) {
        var typeLabel = a.application_type === "internship" ? "Internship" : "Associate";
        var details = a.application_type === "internship"
          ? [a.college, a.study_year, a.duration].filter(Boolean).join(" &middot; ")
          : [a.bar_enrolment_no, a.years_experience, a.practice_area].filter(Boolean).join(" &middot; ");

        var resumeCell = a.resume_path
          ? '<a class="btn btn--sm btn--outline-dark" href="' + window.ADA.data.resumeDownloadUrl(a.id) + '" target="_blank" rel="noopener">Download</a>'
          : '<span style="color:var(--ink-500);font-size:.82rem;">Not attached</span>';

        return (
          "<tr>" +
            "<td>" + typeLabel + "</td>" +
            "<td><strong>" + escapeHtml(a.full_name) + "</strong><br><span style=\"color:var(--ink-500);font-size:.82rem;\">" + escapeHtml(a.email) + (a.phone ? " &middot; " + escapeHtml(a.phone) : "") + "</span></td>" +
            "<td style=\"max-width:280px;\">" + (details || "&mdash;") + (a.message ? "<br><span style=\"color:var(--ink-500);font-size:.82rem;\">" + escapeHtml(a.message) + "</span>" : "") + "</td>" +
            "<td>" + resumeCell + "</td>" +
            "<td style=\"white-space:nowrap;\">" + new Date(a.created_at).toLocaleString("en-IN") + "</td>" +
          "</tr>"
        );
      }).join("");
    }
  }

  /* -------------------------------------------------------------
     IMPORTANT LINKS (admin/links.html) — add / edit / delete
  ------------------------------------------------------------- */
  function initLinks() {
    var form = document.getElementById("link-form");
    if (!form) return;

    var table = document.getElementById("links-table-body");
    var saveBtn = document.getElementById("link-save-btn");
    var cancelBtn = document.getElementById("link-cancel-btn");
    var editingId = null;

    requireAuth().then(function (session) {
      if (session) loadLinks();
    });

    async function loadLinks() {
      table.innerHTML = '<tr><td colspan="4">Loading&hellip;</td></tr>';
      try {
        var links = await window.ADA.data.fetchLinks();
        if (!links.length) {
          table.innerHTML = '<tr><td colspan="4">No links yet. Add one above.</td></tr>';
          return;
        }
        table.innerHTML = links.map(function (l) {
          return (
            "<tr>" +
              "<td>" + l.display_order + "</td>" +
              "<td>" + escapeHtml(l.title) + "</td>" +
              "<td style=\"max-width:260px;overflow-wrap:anywhere;\"><a href=\"" + escapeHtml(l.url) + "\" target=\"_blank\" rel=\"noopener\">" + escapeHtml(l.url) + "</a></td>" +
              "<td style=\"white-space:nowrap;\">" +
                "<button class=\"btn btn--sm btn--outline-dark\" data-edit=\"" + l.id + "\">Edit</button> " +
                "<button class=\"btn btn--sm btn--danger\" data-delete=\"" + l.id + "\">Delete</button>" +
              "</td>" +
            "</tr>"
          );
        }).join("");

        table.querySelectorAll("[data-edit]").forEach(function (btn) {
          btn.addEventListener("click", function () {
            var link = links.find(function (l) { return String(l.id) === btn.getAttribute("data-edit"); });
            if (!link) return;
            editingId = link.id;
            document.getElementById("link-title").value = link.title || "";
            document.getElementById("link-url").value = link.url || "";
            document.getElementById("link-description").value = link.description || "";
            document.getElementById("link-order").value = link.display_order || 0;
            saveBtn.textContent = "Update Link";
            cancelBtn.hidden = false;
            form.scrollIntoView({ behavior: "smooth" });
          });
        });

        table.querySelectorAll("[data-delete]").forEach(function (btn) {
          btn.addEventListener("click", async function () {
            if (!confirm("Delete this link?")) return;
            await window.ADA.data.deleteLink(btn.getAttribute("data-delete"));
            loadLinks();
          });
        });
      } catch (err) {
        table.innerHTML = '<tr><td colspan="4">Could not load links.</td></tr>';
        console.error(err);
      }
    }

    function resetForm() {
      editingId = null;
      form.reset();
      document.getElementById("link-order").value = 0;
      saveBtn.textContent = "Add Link";
      cancelBtn.hidden = true;
    }

    cancelBtn.addEventListener("click", resetForm);

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      var payload = {
        title: document.getElementById("link-title").value.trim(),
        url: document.getElementById("link-url").value.trim(),
        description: document.getElementById("link-description").value.trim(),
        display_order: parseInt(document.getElementById("link-order").value, 10) || 0,
      };

      saveBtn.disabled = true;
      try {
        if (editingId) {
          await window.ADA.data.updateLink(editingId, payload);
        } else {
          await window.ADA.data.createLink(payload);
        }
        resetForm();
        loadLinks();
      } catch (err) {
        alert("Could not save link: " + err.message);
      } finally {
        saveBtn.disabled = false;
      }
    });
  }

  function initResources() {
    var form = document.getElementById("resource-form");
    if (!form) return;

    var table = document.getElementById("resources-table-body");
    var saveBtn = document.getElementById("resource-save-btn");
    var cancelBtn = document.getElementById("resource-cancel-btn");
    var editingId = null;

    requireAuth().then(function (session) {
      if (session) loadResources();
    });

    async function loadResources() {
      table.innerHTML = '<tr><td colspan="5">Loading&hellip;</td></tr>';
      try {
        var resources = await window.ADA.data.fetchResources();
        if (!resources.length) {
          table.innerHTML = '<tr><td colspan="5">No resources yet. Add one above.</td></tr>';
          return;
        }
        table.innerHTML = resources.map(function (r) {
          return (
            "<tr>" +
              "<td>" + escapeHtml(r.category) + "</td>" +
              "<td>" + escapeHtml(r.subcategory || "") + "</td>" +
              "<td>" + escapeHtml(r.title) + "</td>" +
              "<td style=\"max-width:220px;overflow-wrap:anywhere;\"><a href=\"" + escapeHtml(r.url) + "\" target=\"_blank\" rel=\"noopener\">" + escapeHtml(r.url) + "</a></td>" +
              "<td style=\"white-space:nowrap;\">" +
                "<button class=\"btn btn--sm btn--outline-dark\" data-edit=\"" + r.id + "\">Edit</button> " +
                "<button class=\"btn btn--sm btn--danger\" data-delete=\"" + r.id + "\">Delete</button>" +
              "</td>" +
            "</tr>"
          );
        }).join("");

        table.querySelectorAll("[data-edit]").forEach(function (btn) {
          btn.addEventListener("click", function () {
            var resource = resources.find(function (r) { return String(r.id) === btn.getAttribute("data-edit"); });
            if (!resource) return;
            editingId = resource.id;
            document.getElementById("resource-category").value = resource.category || "";
            document.getElementById("resource-subcategory").value = resource.subcategory || "";
            document.getElementById("resource-title").value = resource.title || "";
            document.getElementById("resource-url").value = resource.url || "";
            document.getElementById("resource-description").value = resource.description || "";
            document.getElementById("resource-order").value = resource.display_order || 0;
            saveBtn.textContent = "Update Resource";
            cancelBtn.hidden = false;
            form.scrollIntoView({ behavior: "smooth" });
          });
        });

        table.querySelectorAll("[data-delete]").forEach(function (btn) {
          btn.addEventListener("click", async function () {
            if (!confirm("Delete this resource?")) return;
            await window.ADA.data.deleteResource(btn.getAttribute("data-delete"));
            loadResources();
          });
        });
      } catch (err) {
        table.innerHTML = '<tr><td colspan="5">Could not load resources.</td></tr>';
        console.error(err);
      }
    }

    function resetForm() {
      editingId = null;
      form.reset();
      document.getElementById("resource-order").value = 0;
      saveBtn.textContent = "Add Resource";
      cancelBtn.hidden = true;
    }

    cancelBtn.addEventListener("click", resetForm);

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      var payload = {
        category: document.getElementById("resource-category").value.trim(),
        subcategory: document.getElementById("resource-subcategory").value.trim(),
        title: document.getElementById("resource-title").value.trim(),
        url: document.getElementById("resource-url").value.trim(),
        description: document.getElementById("resource-description").value.trim(),
        display_order: parseInt(document.getElementById("resource-order").value, 10) || 0,
      };

      saveBtn.disabled = true;
      try {
        if (editingId) {
          await window.ADA.data.updateResource(editingId, payload);
        } else {
          await window.ADA.data.createResource(payload);
        }
        resetForm();
        loadResources();
      } catch (err) {
        alert("Could not save resource: " + err.message);
      } finally {
        saveBtn.disabled = false;
      }
    });
  }

  /* -------------------------------------------------------------
     ARTICLES (admin/articles.html) — list, mirrors the post dashboard
  ------------------------------------------------------------- */
  function initArticlesList() {
    var table = document.getElementById("articles-table-body");
    if (!table) return;

    requireAuth().then(function (session) {
      if (session) loadArticles();
    });

    async function loadArticles() {
      table.innerHTML = '<tr><td colspan="4">Loading&hellip;</td></tr>';
      try {
        var articles = await window.ADA.data.fetchAllArticles();
        if (!articles.length) {
          table.innerHTML = '<tr><td colspan="4">No articles yet. <a href="article-editor.html">Add one</a>.</td></tr>';
          return;
        }
        table.innerHTML = articles.map(function (a) {
          return (
            "<tr>" +
              "<td>" + escapeHtml(a.title) + "</td>" +
              "<td>" + escapeHtml((a.file_type || "").toUpperCase()) + "</td>" +
              "<td>" + (a.status === "published"
                ? '<span class="badge badge--gold">Published</span>'
                : '<span class="badge">Draft</span>') + "</td>" +
              "<td style=\"white-space:nowrap;\">" +
                "<a class=\"btn btn--sm btn--outline-dark\" href=\"article-editor.html?id=" + a.id + "\">Edit</a> " +
                "<button class=\"btn btn--sm btn--danger\" data-delete=\"" + a.id + "\">Delete</button>" +
              "</td>" +
            "</tr>"
          );
        }).join("");

        table.querySelectorAll("[data-delete]").forEach(function (btn) {
          btn.addEventListener("click", async function () {
            if (!confirm("Delete this article?")) return;
            await window.ADA.data.deleteArticle(btn.getAttribute("data-delete"));
            loadArticles();
          });
        });
      } catch (err) {
        table.innerHTML = '<tr><td colspan="4">Could not load articles.</td></tr>';
        console.error(err);
      }
    }
  }

  /* -------------------------------------------------------------
     ARTICLE EDITOR (admin/article-editor.html) — create or update
  ------------------------------------------------------------- */
  function initArticleEditor() {
    var form = document.getElementById("article-form");
    if (!form) return;

    var params = new URLSearchParams(window.location.search);
    var editId = params.get("id");
    var heading = document.getElementById("article-editor-heading");

    var fileInput = document.getElementById("article-file");
    var fileLabel = document.getElementById("article-file-label");
    var existingFileUrl = null;
    var existingFileType = null;

    requireAuth().then(async function (session) {
      if (!session) return;
      if (editId) {
        heading.textContent = "Edit Article";
        var article = await window.ADA.data.fetchArticleById(editId);
        if (article) {
          document.getElementById("article-title").value = article.title || "";
          document.getElementById("article-description").value = article.description || "";
          document.getElementById("article-status").value = article.status || "draft";
          existingFileUrl = article.file_url || null;
          existingFileType = article.file_type || null;
          if (existingFileUrl) {
            fileLabel.textContent = "Current file: " + existingFileUrl.split("/").pop() + " (choose a new file only to replace it)";
          }
        }
      } else {
        heading.textContent = "New Article";
      }
    });

    if (fileInput && fileLabel) {
      fileInput.addEventListener("change", function () {
        var file = fileInput.files && fileInput.files[0];
        fileLabel.textContent = file ? file.name : "No file chosen";
      });
    }

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      var saveBtn = document.getElementById("article-save-btn");

      var selectedFile = fileInput && fileInput.files && fileInput.files[0];
      if (!selectedFile && !existingFileUrl) {
        alert("Please choose a file (PDF, PPT, PPTX, DOC, or DOCX) to upload.");
        return;
      }

      saveBtn.disabled = true;

      try {
        var fileUrl = existingFileUrl;
        var fileType = existingFileType;

        if (selectedFile) {
          saveBtn.textContent = "Uploading file…";
          var uploaded = await window.ADA.data.uploadArticleFile(selectedFile);
          fileUrl = uploaded.url;
          fileType = uploaded.file_type;
        }

        var payload = {
          title: document.getElementById("article-title").value.trim(),
          description: document.getElementById("article-description").value.trim(),
          file_url: fileUrl,
          file_type: fileType,
          status: document.getElementById("article-status").value,
        };

        saveBtn.textContent = "Saving…";
        if (editId) {
          await window.ADA.data.updateArticle(editId, payload);
        } else {
          await window.ADA.data.createArticle(payload);
        }
        window.location.href = "articles.html";
      } catch (err) {
        alert("Could not save article: " + err.message);
        saveBtn.disabled = false;
        saveBtn.textContent = "Save Article";
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initLoginPage();
    initDashboard();
    initEditor();
    initMessages();
    initApplications();
    initLinks();
    initResources();
    initArticlesList();
    initArticleEditor();
    initLogout();
  });
})();
