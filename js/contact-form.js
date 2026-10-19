/* ===================================================================
   ADA LAW Chambers — contact-form.js
   ---------------------------------------------------------------
   Submits the Contact Us form to the Hostinger database via
   api/contact.php (see /api/contact.php and api/schema.sql →
   contact_messages table). View submissions any time through
   Hostinger's phpMyAdmin.

   If the API can't be reached at all (e.g. still testing locally
   before the database is wired up), this falls back to opening the
   visitor's email app with the message pre-filled, so the form is
   never a dead end either way.

   Requires js/api-config.js to be loaded first.
=================================================================== */

var CONTACT_RECEIVING_EMAIL = "info@ada.org.in";

(function () {
  "use strict";

  var BASE = (typeof API_BASE_URL !== "undefined" ? API_BASE_URL : "/api");
  var WHATSAPP_NUMBER = (typeof WHATSAPP_NOTIFY_NUMBER !== "undefined" ? WHATSAPP_NOTIFY_NUMBER : "");

  function setStatus(el, text, isError) {
    el.hidden = false;
    el.textContent = text;
    el.className = "alert " + (isError ? "error" : "info");
  }

  function mailtoFallback(payload) {
    var body =
      "Name: " + payload.name + "\n" +
      "Email: " + payload.email + "\n" +
      "Phone: " + (payload.phone || "-") + "\n\n" +
      payload.message;
    var url =
      "mailto:" + CONTACT_RECEIVING_EMAIL +
      "?subject=" + encodeURIComponent("Website enquiry") +
      "&body=" + encodeURIComponent(body);
    window.location.href = url;
  }

  /** Opens WhatsApp with the enquiry pre-filled. Visitor still taps "send"
   *  inside WhatsApp — a webpage can't silently deliver a WhatsApp message
   *  without the official (paid, verified) WhatsApp Business API. */
  function openWhatsAppNotification(payload) {
    if (!WHATSAPP_NUMBER) return;
    var text =
      "New website enquiry\n" +
      "Name: " + payload.name + "\n" +
      "Email: " + payload.email + "\n" +
      "Phone: " + (payload.phone || "-") + "\n" +
      "Message: " + payload.message;
    var url = "https://wa.me/" + WHATSAPP_NUMBER + "?text=" + encodeURIComponent(text);
    window.open(url, "_blank", "noopener");
  }

  function initContactForm() {
    var form = document.getElementById("contact-form");
    if (!form) return;

    var statusBox = document.getElementById("contact-status");
    var openedAt = Date.now(); // spam check: how long the form was open (see looks_like_spam in api/config.php)
    var submitBtn = form.querySelector('button[type="submit"]');
    // the button may hold an icon next to its text; only swap the text
    var submitLabel = submitBtn.querySelector(".cv-submit-label") || submitBtn;

    form.addEventListener("submit", async function (e) {
      e.preventDefault();

      var payload = {
        name: document.getElementById("c-name").value.trim(),
        email: document.getElementById("c-email").value.trim(),
        phone: document.getElementById("c-phone").value.trim(),
        message: document.getElementById("c-message").value.trim(),
        website: document.getElementById("c-website").value, // hidden spam trap, empty for people
        elapsed_ms: Date.now() - openedAt,
      };

      if (!payload.name || !payload.email || !payload.message) {
        setStatus(statusBox, "Please fill in your name, email and message.", true);
        return;
      }

      openWhatsAppNotification(payload);

      submitBtn.disabled = true;
      submitLabel.textContent = "Sending…";

      try {
        var res = await fetch(BASE + "/contact.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        var body = await res.json().catch(function () { return null; });

        if (!res.ok || !body || body.ok === false) {
          throw new Error((body && body.error) || "Request failed.");
        }

        setStatus(statusBox, "Thank you — your message has been received. We've also opened WhatsApp for you — just tap send there to reach us instantly.", false);
        form.reset();
      } catch (err) {
        console.error(err);
        mailtoFallback(payload);
        setStatus(statusBox, "Couldn't reach the server, so we've opened your email app instead to send this directly.", true);
      } finally {
        submitBtn.disabled = false;
        submitLabel.textContent = "Send Message";
      }
    });
  }

  document.addEventListener("DOMContentLoaded", initContactForm);
})();
