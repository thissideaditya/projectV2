/* ===================================================================
   ADA LAW Chambers — main.js
   Shared behaviour for every public page: disclaimer gate, mobile
   nav, dropdown handling, active-link marking.
=================================================================== */
(function () {
  "use strict";

  /* -------------------------------------------------------------
     0. FLOATING CONTACT BUTTONS (WhatsApp + Email)
     Edit these two values to change the number/address site-wide —
     nothing else needs to change on any page.
  ------------------------------------------------------------- */
  var CONTACT = {
    whatsappNumber: "918796630295", // digits only, country code first, no + or spaces
    whatsappMessage: "Hello ADA Law Chambers, I would like to know more about your services.",
    email: "hello@adalawChambers.com",
  };

  function initFloatingContact() {
    if (document.querySelector(".floating-contact")) return; // don't double-inject

    var wrap = document.createElement("div");
    wrap.className = "floating-contact";
    wrap.innerHTML =
      '<a class="floating-btn floating-btn--whatsapp" data-label="Chat on WhatsApp" ' +
        'href="https://wa.me/' + CONTACT.whatsappNumber + '?text=' + encodeURIComponent(CONTACT.whatsappMessage) + '" ' +
        'target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">' +
        '<svg viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16.02 3C9.4 3 4 8.4 4 15.02c0 2.23.6 4.32 1.65 6.12L4 29l8.06-1.6a12.9 12.9 0 0 0 3.96.62h.01c6.62 0 12.02-5.4 12.02-12.02C28.05 8.4 22.65 3 16.02 3zm7.03 17c-.3.83-1.6 1.55-2.5 1.7-.64.1-1.47.14-2.37-.15-.55-.17-1.25-.4-2.15-.8-3.78-1.63-6.25-5.4-6.44-5.65-.19-.25-1.53-2.04-1.53-3.9 0-1.85 1-2.76 1.34-3.13.34-.37.75-.47 1-.47.25 0 .5 0 .72.01.23.01.54-.09.85.65.3.74 1.03 2.56 1.13 2.75.1.19.16.4.03.65-.13.25-.19.4-.38.62-.19.22-.4.5-.57.67-.19.19-.39.4-.17.78.22.37 1 1.63 2.13 2.64 1.47 1.3 2.7 1.71 3.08 1.9.38.19.6.16.83-.1.22-.25.94-1.1 1.19-1.47.25-.37.5-.31.83-.19.34.12 2.15 1.01 2.52 1.2.37.19.62.28.7.44.09.16.09.9-.21 1.73z"/></svg>' +
      '</a>' +
      '<a class="floating-btn floating-btn--email" data-label="Email us" ' +
        'href="mailto:' + CONTACT.email + '" aria-label="Email ADA Law Chambers">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v16H4z" opacity="0"/><path d="M4 6h16v12H4z"/><path d="M4 6l8 7 8-7"/></svg>' +
      '</a>';

    document.body.appendChild(wrap);
  }

  /* -------------------------------------------------------------
     1. DISCLAIMER GATE
     Shown once per browser tab (sessionStorage). Blurs + locks the
     page until "I Agree" is clicked.
  ------------------------------------------------------------- */
  function initDisclaimer() {
    function done() {
      window.__adaDisclaimerDone = true;
      document.dispatchEvent(new Event("ada:disclaimer-closed"));
    }
    var overlay = document.getElementById("disclaimer-overlay");
    if (!overlay) { document.body.classList.remove("disclaimer-active"); done(); return; }

    var alreadyAgreed = false;
    try { alreadyAgreed = sessionStorage.getItem("ada_disclaimer_agreed") === "true"; } catch (e) {}
    if (alreadyAgreed) {
      overlay.hidden = true;
      document.body.classList.remove("disclaimer-active");
      done();
      return;
    }

    document.body.classList.add("disclaimer-active");
    overlay.hidden = false;

    var agreeBtn = document.getElementById("disclaimer-agree");
    if (agreeBtn) {
      agreeBtn.addEventListener("click", function () {
        try { sessionStorage.setItem("ada_disclaimer_agreed", "true"); } catch (e) {}
        overlay.hidden = true;
        document.body.classList.remove("disclaimer-active");
        done();
      });
    }
  }

  /* -------------------------------------------------------------
     2. MOBILE NAV
  ------------------------------------------------------------- */
  function initNav() {
    var toggle = document.querySelector(".nav-toggle");
    var nav = document.querySelector(".main-nav");
    if (!toggle || !nav) return;

    toggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    // Mobile dropdown (About/Insights) — tap the label OR the arrow to
    // expand. Listening on .nav-item-row (the wrapper around both the
    // link text and the arrow icon), rather than just the <a>, means a
    // tap anywhere in that row opens the dropdown.
    document.querySelectorAll(".has-dropdown").forEach(function (li) {
      var row = li.querySelector(".nav-item-row");
      var dropdown = li.querySelector(".dropdown");
      if (!row || !dropdown) return;

      row.addEventListener("click", function (e) {
        if (window.innerWidth <= 760) {
          e.preventDefault(); // stops the <a> inside from navigating away
          li.classList.toggle("open");
        }
      });
    });

    // Close mobile nav when a dropdown's own link is tapped
    nav.querySelectorAll(".dropdown a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("open");
      });
    });
    // Close mobile nav when a top-level link with no dropdown is tapped
    nav.querySelectorAll(".main-nav > ul > li:not(.has-dropdown) > a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("open");
      });
    });
  }

  /* -------------------------------------------------------------
     3. ACTIVE LINK HIGHLIGHTING
     Compares each nav link's pathname against the current page.
  ------------------------------------------------------------- */
  function markActiveLink() {
    var current = window.location.pathname.split("/").pop() || "index.html";
    document.querySelectorAll(".main-nav a").forEach(function (link) {
      var href = link.getAttribute("href");
      if (!href) return;
      var page = href.split("/").pop();
      if (page === current) {
        link.classList.add("active");
      }
    });
  }

  /* -------------------------------------------------------------
     4. FOOTER YEAR
  ------------------------------------------------------------- */
  function setYear() {
    var el = document.getElementById("current-year");
    if (el) el.textContent = new Date().getFullYear();
  }

  document.addEventListener("DOMContentLoaded", function () {
    initFloatingContact();
  });

  /* -------------------------------------------------------------
    5. READ MORE / READ LESS TOGGLES — every page. Markup: the hidden
       extra content, then <button class="read-more-toggle"> right
       after it, so "Read Less" always sits under the expanded text.
  ------------------------------------------------------------- */
  document.querySelectorAll(".read-more-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var list = btn.previousElementSibling;
      var isOpen = btn.getAttribute("aria-expanded") === "true";
      list.hidden = isOpen;
      btn.setAttribute("aria-expanded", String(!isOpen));
      // Labels can be set per button (data-more / data-less), e.g. "Explore service"
      btn.textContent = isOpen ? (btn.dataset.more || "Read More") : (btn.dataset.less || "Read Less");
      // Collapsing a long block can leave the button above the screen; bring it back into view
      if (isOpen && btn.getBoundingClientRect().top < 0) btn.scrollIntoView({ block: "center" });
    });
  });

  // <a href="#id" data-open="id">: jump to that card and open its Read More
  // details (Practice Areas: the GST / Income Tax cards point at their full service).
  document.addEventListener("click", function (e) {
    var link = e.target.closest && e.target.closest("a[data-open]");
    if (!link) return;
    var target = document.getElementById(link.getAttribute("data-open"));
    if (!target) return;
    e.preventDefault();
    // Skip its scroll-reveal (js/motion.js): a not-yet-revealed card sits 26px
    // low, so the jump would land it under the sticky header once it slides up.
    target.classList.remove("reveal", "reveal--fade", "in");
    var toggle = target.querySelector(".read-more-toggle");
    if (toggle && toggle.getAttribute("aria-expanded") !== "true") toggle.click();
    target.scrollIntoView({ block: "start" }); // CSS scroll-margin-top keeps it below the header
    // The page above can shift while the smooth scroll runs (the header compacts
    // once scrolled), so check where the card ended up and nudge it into place.
    var settle = function () {
      var off = target.getBoundingClientRect().top - (parseFloat(getComputedStyle(target).scrollMarginTop) || 0);
      if (Math.abs(off) > 2) window.scrollBy({ top: off, behavior: "smooth" });
    };
    if ("onscrollend" in window) window.addEventListener("scrollend", settle, { once: true });
    else setTimeout(settle, 900);
    if (history.replaceState) history.replaceState(null, "", "#" + target.id);
  });

  // Nav toggling, active-link highlighting, and the footer year all
  // need the real header/footer markup in the DOM first — which
  // partials.js injects asynchronously — so they run off this event
  // instead of DOMContentLoaded. See js/partials.js.
  document.addEventListener("ada:partials-loaded", function () {
    initDisclaimer(); // the gate itself now lives in partials/disclaimer.html
    initNav();
    markActiveLink();
    setYear();
  });
})();

/* ===================================================================
   HEADER OVER HERO — the header sits over the top of the hero and is
   always cream (it used to be transparent until the page scrolled; the
   .is-top styles in css/style.css are now unused). Pairs with the
   "HEADER OVER HERO" block in css/style.css.
=================================================================== */
document.addEventListener("ada:partials-loaded", function () {
  var header = document.querySelector(".site-header");
  var hero = document.querySelector(".hero");
  if (!header || !hero) return; // pages without a hero keep the normal cream header

  header.classList.add("over-hero");

  function measure() {
    document.documentElement.style.setProperty("--hh", header.offsetHeight + "px");
  }

  measure();
  window.addEventListener("resize", measure);
});

/* ===================================================================
   CARD FOCUS-EXPAND
   Mouse: move onto a card in a grid -> its column grows to 50% of the
          row and the others share the rest; leaving the grid resets.
   Touch: tap a card (tablet / phone) -> same; tap another to switch,
          tap outside the grid to reset. Cards that are links need a
          first tap to expand and a second tap to open.
   Rows with 2 columns can't go to 50% (already there), so the focused
   column takes 60/40. Single-column (phone) just highlights the card.
   Works for every card type (.card, .pa-card, .svc-card, .post-card,
   .video-card) and uses event delegation, so cards rendered later work too.

   Smoothness: widths AND row heights animate together. Before each
   change the final layout is measured on a hidden copy of the grid, so
   text re-wrapping never makes a row jump. Each animation starts from
   where the cards are right now, so moving across cards mid-way just
   retargets. Heights are released once it settles, so "Read More"
   panels can still grow. Rows never get shorter than they were at rest
   (a widened card doesn't collapse to its text); they only grow if a
   narrowed card needs the room. Timing lives in css/style.css (--fx).
=================================================================== */
(function () {
  var CARD = ".card:not(.card--featured), .pa-card, .svc-card, .post-card, .video-card:not(.is-loading)";
  var DUR = 480; // matches --fx in css/style.css
  var touchy = window.matchMedia("(hover: none), (pointer: coarse)");
  var current = null; // { grid, card }

  function slice(list) { return Array.prototype.slice.call(list); }
  function px(n) { return (Math.floor(n * 100) / 100) + "px"; }
  // Width the items actually share: grids that are also a .container carry
  // side padding, which must not be handed out to the cards.
  function innerWidth(g) {
    var cs = getComputedStyle(g);
    return g.getBoundingClientRect().width - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0)
           - (parseFloat(cs.borderLeftWidth) || 0) - (parseFloat(cs.borderRightWidth) || 0);
  }
  function cardOf(item) {
    if (item.matches(CARD)) return item;
    return slice(item.children).filter(function (c) { return c.matches(CARD); })[0] || null;
  }

  // Resolve the grid + grid item + card for any element, or null if this
  // grid isn't a card grid (every item must be, or directly wrap, a card).
  function resolve(el) {
    if (!el || !el.closest) return null;
    var card = el.closest(CARD);
    if (!card) return null;
    var item = card, grid = card.parentElement;
    if (grid && !grid.classList.contains("grid") && grid.tagName === "A" && grid.parentElement) {
      item = grid; grid = grid.parentElement;
    }
    if (!grid || !grid.classList.contains("grid") || grid.classList.contains("team-grid") || grid.classList.contains("fx-measure")) return null;
    var items = slice(grid.children);
    if (items.length < 2) return null;
    var ok = items.every(function (i) { return !!cardOf(i); });
    return ok ? { grid: grid, item: item, card: card, items: items } : null;
  }

  function setFlags(items, flags) {
    items.forEach(function (it, i) {
      var c = cardOf(it);
      if (!c) return;
      c.classList.toggle("is-active", flags[i] === "active");
      c.classList.toggle("is-squeezed", flags[i] === "squeezed");
    });
  }

  // Row heights the grid will have at these widths/states, measured on a
  // hidden copy (same parent, so the same CSS applies) — the live cards are
  // never touched, so their running transitions are left alone.
  function measure(g, widths, flags) {
    var copy = g.cloneNode(true);
    copy.removeAttribute("id");
    copy.classList.remove("has-focus");
    copy.classList.add("fx-measure");
    copy.setAttribute("aria-hidden", "true");
    var s = copy.style;
    s.position = "absolute"; s.left = "0"; s.top = "0"; s.visibility = "hidden"; s.pointerEvents = "none";
    s.width = px(g.getBoundingClientRect().width);
    s.display = "flex"; s.flexWrap = "wrap"; s.alignItems = "flex-start"; s.alignContent = "flex-start";
    var kids = slice(copy.children);
    kids.forEach(function (k, i) { k.style.flex = "0 0 " + px(widths[i]); k.style.minWidth = "0"; k.style.height = "auto"; });
    setFlags(kids, flags);
    g.parentNode.appendChild(copy);
    var rows = [];
    kids.forEach(function (k, i) {
      var r = Math.floor(i / g._cols);
      rows[r] = Math.max(rows[r] || 0, k.getBoundingClientRect().height);
    });
    copy.remove();
    return kids.map(function (k, i) { return rows[Math.floor(i / g._cols)]; });
  }

  // Animate every item from its current size to the target widths + measured heights.
  function go(g, items, widths, flags, after) {
    var heights = measure(g, widths, flags).map(function (h, i) { return Math.max(h, g._rest[i] || 0); });
    var now = items.map(function (it) { return it.getBoundingClientRect(); });
    // Pin the current sizes (mid-animation included) with the size transitions off…
    g.classList.remove("has-focus");
    items.forEach(function (it, i) {
      it.style.minWidth = "0";
      it.style.flex = "0 0 " + px(now[i].width);
      it.style.height = px(now[i].height);
    });
    void g.offsetWidth;
    // …then switch them on and set the targets.
    g.classList.add("has-focus");
    items.forEach(function (it, i) {
      it.style.flex = "0 0 " + px(widths[i]);
      it.style.height = px(heights[i]);
    });
    setFlags(items, flags);
    clearTimeout(g._t);
    g._t = setTimeout(function () {
      // hand over to min-height at the row's final height (some grids don't
      // stretch their items, so a bare release would let a card drop to its
      // own text height) — content (e.g. Read More) can still grow
      items.forEach(function (it, i) { it.style.minHeight = px(heights[i]); it.style.height = ""; });
      if (after) after();
    }, DUR + 60);
  }

  function resetStyles(g) {
    g.style.display = ""; g.style.flexWrap = ""; g.style.alignContent = "";
    slice(g.children).forEach(function (it) { it.style.flex = ""; it.style.minWidth = ""; it.style.height = ""; it.style.minHeight = ""; });
    g.classList.remove("has-focus");
    g._flex = 0;
    clearTimeout(g._t);
  }

  function clear(immediate) {
    if (!current) return;
    var g = current.grid, items = slice(g.children);
    current = null;
    var none = items.map(function () { return ""; });
    if (!g._flex) { setFlags(items, none); g.classList.remove("has-focus"); return; }
    if (immediate) { setFlags(items, none); resetStyles(g); return; }
    go(g, items, items.map(function () { return g._idle; }), none, function () {
      if (!current || current.grid !== g) resetStyles(g);
    });
  }

  function activate(r) {
    if (current && current.card === r.card) return;
    if (current && current.grid !== r.grid) clear(true);

    var g = r.grid, items = r.items;
    clearTimeout(g._t);

    if (!g._flex) {
      var cs = getComputedStyle(g);
      var cols = cs.gridTemplateColumns.split(" ").filter(Boolean).length;
      g._cols = cols; g._gap = parseFloat(cs.columnGap) || 0;
      g._idle = (innerWidth(g) - g._gap * (cols - 1)) / cols;
      if (cols >= 2) {
        // Swap the CSS grid for a wrapping flex layout at the exact same sizes
        // (a CSS grid shares columns between rows; each row must size on its own).
        var start = items.map(function (it) { return it.getBoundingClientRect().height; });
        g.style.display = "flex"; g.style.flexWrap = "wrap"; g.style.alignContent = "flex-start";
        items.forEach(function (it, i) { it.style.minWidth = "0"; it.style.flex = "0 0 " + px(g._idle); it.style.height = px(start[i]); });
        g._flex = cols;
        g._rest = start; // resting row heights: the floor while focused
      }
    }

    var flags;
    if (!g._flex) {
      flags = items.map(function (it) { return it === r.item ? "active" : ""; });
      setFlags(items, flags);
      g.classList.add("has-focus");
    } else {
      var cols2 = g._cols, W = innerWidth(g) - 0.5; // 0.5px slack against rounding
      var row = Math.floor(items.indexOf(r.item) / cols2);
      var rowItems = items.slice(row * cols2, row * cols2 + cols2);
      var n = rowItems.length;
      // Only the focused card's row changes. n>=3: active = 50% of the row;
      // n==2: 60/40 so there is a visible change. Others share the rest.
      var avail = W - g._gap * (n - 1);
      var share = n === 2 ? 0.6 : 0.5;
      var big = avail * share, small = (avail - big) / Math.max(1, n - 1);
      var widths = items.map(function (it) {
        if (n < 2 || rowItems.indexOf(it) === -1) return g._idle;
        return it === r.item ? big : small;
      });
      flags = items.map(function (it) {
        if (it === r.item) return "active";
        return n >= 3 && rowItems.indexOf(it) !== -1 ? "squeezed" : "";
      });
      go(g, items, widths, flags);
    }
    current = { grid: g, card: r.card };
  }

  // Mouse: react to real pointer movement only. Cards sliding under a still
  // cursor (they move while animating) must not steal the focus, or
  // neighbours would flip back and forth.
  var queued = false, lastTarget = null;
  // While a Share menu is open (js/share.js), the card it belongs to stays
  // focused: the pointer is over the menu's click-blocking layer, which would
  // otherwise read as "left the grid" and shrink the card back.
  function sharing() { return document.documentElement.classList.contains("share-open"); }
  document.addEventListener("pointermove", function (e) {
    if (e.pointerType !== "mouse" || touchy.matches || sharing()) return;
    lastTarget = e.target;
    if (queued) return;
    queued = true;
    requestAnimationFrame(function () {
      queued = false;
      if (sharing()) return;
      var r = resolve(lastTarget);
      if (r) activate(r);
      else if (current && !current.grid.contains(lastTarget)) clear();
    });
  }, { passive: true });
  // Pointer left the window
  document.addEventListener("mouseout", function (e) {
    if (!touchy.matches && !e.relatedTarget && !sharing()) clear();
  });

  // Keyboard: tabbing into a card expands it too.
  document.addEventListener("focusin", function (e) {
    if (touchy.matches) return;
    var r = resolve(e.target);
    if (r) activate(r);
  });

  // Touch: tap to expand; link cards open on the second tap; tap outside to reset.
  document.addEventListener("click", function (e) {
    if (!touchy.matches) return;
    var r = resolve(e.target);
    if (!r) { clear(); return; }
    var isActive = current && current.card === r.card;
    if (!isActive) {
      activate(r);
      var link = e.target.closest("a");
      if (link && r.grid.contains(link)) e.preventDefault(); // first tap expands, doesn't navigate
    }
  }, true);

  // Layout changes (rotate, resize across breakpoints) -> start fresh.
  var lastW = window.innerWidth;
  window.addEventListener("resize", function () {
    if (window.innerWidth !== lastW) { lastW = window.innerWidth; clear(true); }
  });
})();
