/* ===================================================================
   ADA LAW Chambers — careers-form.js
   ---------------------------------------------------------------
   Submits both the Internship and Associate application forms on
   careers.html — including the resume file, if attached — to the
   Hostinger database via api/careers.php (see api/schema.sql →
   career_applications table). Resumes are saved under
   /uploads/resumes/ and their path is stored with the application.
   View and download submissions any time through phpMyAdmin.

   Requires js/api-config.js to be loaded first.
=================================================================== */
(function () {
  "use strict";

  var BASE = (typeof API_BASE_URL !== "undefined" ? API_BASE_URL : "/api");

  var WHATSAPP_NUMBER = "+918796630295";

  function setStatus(el, text, isError) {
    el.hidden = false;
    el.textContent = text;
    el.className = "alert " + (isError ? "error" : "info");
  }

  /** Opens WhatsApp with the application pre-filled. Visitor still taps
   *  "send" inside WhatsApp — a webpage can't silently deliver a WhatsApp
   *  message without the official (paid, verified) WhatsApp Business API.
   *  Note: WhatsApp click-to-chat links can't attach files, so the resume
   *  itself stays in the database/admin panel — this just flags that one
   *  was attached. */
  function openWhatsAppNotification(type, fields, hasResume) {
    if (!WHATSAPP_NUMBER) return;
    var label = type === "internship" ? "Internship" : "Associate";
    var lines = ["New " + label + " application", "Name: " + fields.full_name, "Email: " + fields.email, "Phone: " + (fields.phone || "-")];

    if (type === "internship") {
      lines.push("College: " + (fields.college || "-"));
      lines.push("Year: " + (fields.study_year || "-"));
      lines.push("Duration: " + (fields.duration || "-"));
    } else {
      lines.push("Bar Enrolment No.: " + (fields.bar_enrolment_no || "-"));
      lines.push("Experience: " + (fields.years_experience || "-"));
      lines.push("Practice Area: " + (fields.practice_area || "-"));
    }
    if (fields.message) lines.push("Note: " + fields.message);
    lines.push(hasResume ? "Resume: attached (see admin panel)" : "Resume: not attached");

    var url = "https://wa.me/" + WHATSAPP_NUMBER + "?text=" + encodeURIComponent(lines.join("\n"));
    window.open(url, "_blank", "noopener");
  }

  async function submitApplication(type, fields, resumeFile, statusBox, submitBtn) {
    var formData = new FormData();
    formData.append("application_type", type);
    Object.keys(fields).forEach(function (key) {
      formData.append(key, fields[key] || "");
    });
    if (resumeFile) {
      formData.append("resume", resumeFile);
    }

    submitBtn.disabled = true;
    var label = submitBtn.querySelector(".cr-submit-label") || submitBtn; // keep the arrow icon
    label.textContent = "Submitting…";

    try {
      var res = await fetch(BASE + "/careers.php", { method: "POST", body: formData });
      var body = await res.json().catch(function () { return null; });

      if (!res.ok || !body || body.ok === false) {
        throw new Error((body && body.error) || "Request failed.");
      }

      setStatus(statusBox, "Application received. We've also opened WhatsApp for you — just tap send there to reach us instantly.", false);
      return true;
    } catch (err) {
      console.error(err);
      setStatus(statusBox, "Something went wrong submitting your application. Please try again, or email us directly.", true);
      return false;
    } finally {
      submitBtn.disabled = false;
      label.textContent = "Submit Application";
    }
  }

  function initInternshipForm() {
    var form = document.getElementById("internship-form");
    if (!form) return;
    var statusBox = document.getElementById("internship-status");
    var submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener("submit", async function (e) {
      e.preventDefault();

      var fullName = document.getElementById("i-name").value.trim();
      var email = document.getElementById("i-email").value.trim();
      if (!fullName || !email) {
        setStatus(statusBox, "Please fill in your name and email.", true);
        return;
      }

      var fields = {
        full_name: fullName,
        email: email,
        phone: document.getElementById("i-phone").value.trim(),
        college: document.getElementById("i-college").value.trim(),
        study_year: document.getElementById("i-year").value.trim(),
        duration: document.getElementById("i-duration").value.trim(),
        message: document.getElementById("i-message").value.trim(),
      };
      var resumeFile = document.getElementById("i-resume").files[0];

      openWhatsAppNotification("internship", fields, !!resumeFile);

      var ok = await submitApplication("internship", fields, resumeFile, statusBox, submitBtn);
      if (ok) form.reset();
    });
  }

  function initAssociateForm() {
    var form = document.getElementById("associate-form");
    if (!form) return;
    var statusBox = document.getElementById("associate-status");
    var submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener("submit", async function (e) {
      e.preventDefault();

      var fullName = document.getElementById("a-name").value.trim();
      var email = document.getElementById("a-email").value.trim();
      if (!fullName || !email) {
        setStatus(statusBox, "Please fill in your name and email.", true);
        return;
      }

      var fields = {
        full_name: fullName,
        email: email,
        phone: document.getElementById("a-phone").value.trim(),
        bar_enrolment_no: document.getElementById("a-enrol").value.trim(),
        years_experience: document.getElementById("a-exp").value.trim(),
        practice_area: document.getElementById("a-area").value.trim(),
        message: document.getElementById("a-message").value.trim(),
      };
      var resumeFile = document.getElementById("a-resume").files[0];

      openWhatsAppNotification("associate", fields, !!resumeFile);

      var ok = await submitApplication("associate", fields, resumeFile, statusBox, submitBtn);
      if (ok) form.reset();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initInternshipForm();
    initAssociateForm();
  });
})();
