/* ===================================================================
   ADA LAW Chambers — experts.js
   Placeholder roster for the "Meet Our Experts" section. Swap the
   name/role/img fields below to update the section site-wide —
   nothing else in the codebase needs to change.
=================================================================== */
(function () {
  "use strict";

  var EXPERTS = [
    { name: "Ashu Dalmia", role: "FOUNDER · CHARTERED ACCOUNTANT & ADVOCATE", img: "/assets/images/ashuImage.webp" },
    { name: "Smita Raj", role: "Tax PROFESSIONAL", img: "/assets/images/smitaImage.webp" },
    { name: "Adv. Ankita Jha", role: "LEGAL PRACTITIONER", img: "/assets/images/ankitaImage.webp" },
    { name: "Ishu Bharti Jha", role: "MANAGER · ASSOCIATE", img: "/assets/images/ishuImage.webp" },
    { name: "Neelam ", role: "Advocate", img: "/assets/images/neelamImage.webp" },
    { name: "Anshu Kumar Upadhyay", role: "ADVOCATE", img: "/assets/images/anshuImage.webp" },
    { name: "CA (Dr.) Sunil Goel", role: "CONSULTANT · CONTRACTS, COMPLIANCE & INVESTIGATIONS", img: "/assets/images/sunilImage.webp" },
    { name: "Saurabh Sharma", role: "CONSULTANT · BANKING & FINANCIAL SERVICES", img: "/assets/images/saurabhImage.webp" },
    { name: "Anuj Dalmia", role: "CONSULTANT · START-UP & TRANSACTION ADVISORY", img: "/assets/images/anujImage.webp" },
    { name: "CA Ravish Barahia", role: "CONSULTANT", img: "/assets/images/ravishImage.webp" },
  ];

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

  function render() {
    var mount = document.getElementById("experts-grid");
    if (!mount) return;
    var HOME_LIMIT = 1;
    var onHome = mount.hasAttribute("data-limit-home");
    var list = onHome ? EXPERTS.slice(0, HOME_LIMIT) : EXPERTS;
    mount.innerHTML = list.map(function (e) {
      return (
        '<div class="team-card">' +
          '<div class="team-photo"><img src="' + escapeHtml(safeUrl(e.img)) + '" alt="' + escapeHtml(e.name) + '" loading="lazy"></div>' +
          '<div class="team-info"><h3>' + escapeHtml(e.name) + '</h3><span class="role">' + escapeHtml(e.role) + '</span></div>' +
        '</div>'
      );
    }).join("");
  }

  document.addEventListener("DOMContentLoaded", render);
})();
