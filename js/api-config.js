/* ===================================================================
   ADA LAW Chambers — api-config.js
   ---------------------------------------------------------------
   One setting: where the PHP API (the /api folder) lives relative
   to this site. On Hostinger, if you upload the whole project
   (including /api) into the same public_html folder as the rest of
   the site, the default below ("/api") is correct and you don't
   need to change anything.

   If your API ever lives on a different domain/subdomain, change
   this to the full URL, e.g. "https://api.adalawChambers.com".
=================================================================== */
var API_BASE_URL = "/api";

/* ===================================================================
   WhatsApp notification number
   ---------------------------------------------------------------
   When someone submits the Contact form or a Careers application,
   their browser opens WhatsApp with the details pre-filled and
   addressed to this number — they just tap send once inside
   WhatsApp. (A fully automatic, zero-tap send would require
   WhatsApp's official Business API, which needs business
   verification and per-message cost — this is the practical
   zero-setup version.)

   Format: digits only, country code first, no + or spaces.
=================================================================== */
var WHATSAPP_NOTIFY_NUMBER = "918796630295";

