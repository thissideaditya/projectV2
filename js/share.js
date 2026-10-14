/* ===================================================================
   ADA LAW Chambers — share.js
   Adds a "Share" button (Copy link / WhatsApp / Email) to Rules,
   Thoughts, the single-post page and Articles.

   How it works
   - ADA_SHARE.button(url, title) returns the HTML for a Share button.
     The renderers (posts-render.js, articles-render.js) drop it in.
   - One click handler on the document opens a small menu next to
     whichever button was pressed. No other setup is needed.
   - Its own styles are injected below, so style.css is untouched.
=================================================================== */
(function () {
  "use strict";

  var ICON_SHARE =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>' +
    '<path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>';
  var ICON_COPY =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>';
  var ICON_WA =
    '<svg viewBox="0 0 32 32" fill="#25d366" aria-hidden="true"><path d="M16.02 3C9.4 3 4 8.4 4 15.02c0 2.23.6 4.32 1.65 6.12L4 29l8.06-1.6a12.9 12.9 0 0 0 3.96.62h.01c6.62 0 12.02-5.4 12.02-12.02C28.05 8.4 22.65 3 16.02 3zm7.03 17c-.3.83-1.6 1.55-2.5 1.7-.64.1-1.47.14-2.37-.15-.55-.17-1.25-.4-2.15-.8-3.78-1.63-6.25-5.4-6.44-5.65-.19-.25-1.53-2.04-1.53-3.9 0-1.85 1-2.76 1.34-3.13.34-.37.75-.47 1-.47.25 0 .5 0 .72.01.23.01.54-.09.85.65.3.74 1.03 2.56 1.13 2.75.1.19.16.4.03.65-.13.25-.19.4-.38.62-.19.22-.4.5-.57.67-.19.19-.39.4-.17.78.22.37 1 1.63 2.13 2.64 1.47 1.3 2.7 1.71 3.08 1.9.38.19.6.16.83-.1.22-.25.94-1.1 1.19-1.47.25-.37.5-.31.83-.19.34.12 2.15 1.01 2.52 1.2.37.19.62.28.7.44.09.16.09.9-.21 1.73z"/></svg>';
  var ICON_MAIL =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M4 6h16v12H4z"/><path d="M4 6l8 7 8-7"/></svg>';

  /* ---------- styles (injected once) ---------- */
  var css =
    ".card-actions{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap}" +
    ".card-actions .read-more{margin-top:0}" +
    ".share-btn{display:inline-flex;align-items:center;gap:.4rem;background:transparent;border:1px solid var(--line);color:var(--green-800);border-radius:999px;padding:.4em .9em;font-size:.8rem;font-weight:600;line-height:1.2}" +
    ".share-btn:hover,.share-btn[aria-expanded=true]{border-color:var(--gold-500);color:var(--gold-700)}" +
    ".share-btn svg{width:1em;height:1em;flex:none}" +
    ".share-menu{position:fixed;z-index:700;min-width:12rem;background:var(--white);border:1px solid var(--line);border-radius:var(--radius-md);box-shadow:0 18px 40px -16px rgba(0,0,0,.35);padding:.35rem}" +
    ".share-menu[hidden]{display:none}" +
    ".share-menu a,.share-menu button{display:flex;align-items:center;gap:.7rem;width:100%;padding:.7rem .8rem;background:none;border:0;border-radius:6px;font-size:.92rem;color:var(--ink-700);text-align:left;text-decoration:none;cursor:pointer}" +
    ".share-menu a:hover,.share-menu button:hover{background:var(--cream-100)}" +
    ".share-menu svg{width:1.15rem;height:1.15rem;flex:none}";
  var style = document.createElement("style");
  style.textContent = css;
  document.head.appendChild(style);

  /* ---------- helpers ---------- */
  function esc(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  function absoluteUrl(url) {
    try { return new URL(url, window.location.href).href; } catch (e) { return url; }
  }

  /** Public: HTML for a Share button. `url` may be relative. */
  function button(url, title) {
    return (
      '<button type="button" class="share-btn" data-share-url="' + esc(url) + '" data-share-title="' + esc(title) + '"' +
      ' aria-haspopup="true" aria-expanded="false">' + ICON_SHARE + " Share</button>"
    );
  }

  /* ---------- the menu (built once, reused) ---------- */
  var menu = null;
  var activeBtn = null;
  var copyLabel = null;

  function buildMenu() {
    menu = document.createElement("div");
    menu.className = "share-menu";
    menu.setAttribute("role", "menu");
    menu.hidden = true;
    menu.innerHTML =
      '<button type="button" role="menuitem" data-act="copy">' + ICON_COPY + '<span>Copy link</span></button>' +
      '<a role="menuitem" data-act="wa" target="_blank" rel="noopener noreferrer">' + ICON_WA + '<span>WhatsApp</span></a>' +
      '<a role="menuitem" data-act="mail">' + ICON_MAIL + '<span>Email</span></a>';
    document.body.appendChild(menu);
    copyLabel = menu.querySelector('[data-act="copy"] span');
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    // Fallback for older browsers / non-HTTPS
    return new Promise(function (resolve, reject) {
      var ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.cssText = "position:fixed;top:0;left:0;opacity:0";
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand("copy") ? resolve() : reject(); }
      catch (e) { reject(e); }
      document.body.removeChild(ta);
    });
  }

  function closeMenu() {
    if (!menu || menu.hidden) return;
    menu.hidden = true;
    if (activeBtn) activeBtn.setAttribute("aria-expanded", "false");
    activeBtn = null;
    copyLabel.textContent = "Copy link";
  }

  function openMenu(btn) {
    if (!menu) buildMenu();
    var url = absoluteUrl(btn.getAttribute("data-share-url"));
    var title = btn.getAttribute("data-share-title") || "ADA Law Chambers";

    menu.setAttribute("data-url", url);
    // wa.me/?text= opens WhatsApp and lets the person pick any contact/group
    menu.querySelector('[data-act="wa"]').href =
      "https://wa.me/?text=" + encodeURIComponent(title + "\n" + url);
    menu.querySelector('[data-act="mail"]').href =
      "mailto:?subject=" + encodeURIComponent(title) +
      "&body=" + encodeURIComponent("I thought you might find this useful:\n\n" + title + "\n" + url + "\n\n— ADA Law Chambers");

    copyLabel.textContent = "Copy link";
    menu.hidden = false;
    btn.setAttribute("aria-expanded", "true");
    activeBtn = btn;

    // Position under the button, flip above if there's no room, keep on-screen
    var r = btn.getBoundingClientRect();
    var w = menu.offsetWidth, h = menu.offsetHeight;
    var left = Math.min(Math.max(8, r.right - w), window.innerWidth - w - 8);
    var top = r.bottom + 6;
    if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 6);
    menu.style.left = left + "px";
    menu.style.top = top + "px";
  }

  /* ---------- events ---------- */
  document.addEventListener("click", function (e) {
    var btn = e.target.closest ? e.target.closest(".share-btn") : null;
    if (btn) {
      e.preventDefault();
      if (activeBtn === btn) { closeMenu(); return; }
      closeMenu();
      openMenu(btn);
      return;
    }

    if (menu && !menu.hidden && menu.contains(e.target)) {
      var item = e.target.closest("[data-act]");
      if (!item) return;
      var act = item.getAttribute("data-act");
      if (act === "copy") {
        copyText(menu.getAttribute("data-url")).then(function () {
          copyLabel.textContent = "Link copied \u2713";
          setTimeout(closeMenu, 1200);
        }, function () {
          copyLabel.textContent = "Press Ctrl+C to copy";
          window.prompt("Copy this link:", menu.getAttribute("data-url"));
          closeMenu();
        });
      } else {
        setTimeout(closeMenu, 100); // WhatsApp / Email links navigate on their own
      }
      return;
    }

    closeMenu(); // click anywhere else
  });

  document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeMenu(); });
  window.addEventListener("resize", closeMenu);
  window.addEventListener("scroll", closeMenu, { passive: true });

  window.ADA_SHARE = { button: button };
})();
