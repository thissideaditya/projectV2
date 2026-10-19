# ADA Law Chambers — Website

A fully responsive HTML/CSS/JS website for ADA Law Chambers, with an admin
panel for managing the **Rules** and **Thoughts** sections, backed by a
PHP + MySQL API built for **Hostinger** hosting.

## Structure

```
ada-law-Chambers/
├── index.html, about.html, practice-areas.html, team.html, pro-bono.html,
│   insights.html, rules.html, thoughts.html, post.html, careers.html,
│   contact.html                Public pages
├── css/style.css                Single shared stylesheet (design tokens at top)
├── js/main.js                   Disclaimer gate, mobile nav, floating WhatsApp/Email buttons
├── js/api-config.js             ONE setting: where the PHP API lives (API_BASE_URL)
├── js/hostinger-client.js       Talks to /api — same interface every page/admin script expects
├── js/contact-form.js           Contact form → api/contact.php (mailto fallback if unreachable)
├── js/careers-form.js           Both Careers forms (+ resume upload) → api/careers.php
├── js/experts.js                "Meet Our Experts" data + renderer (Home page)
├── js/posts-render.js           Renders Rules/Thoughts listings & single articles
├── assets/images/                Logo + team photos
├── admin/
│   ├── index.html                Login
│   ├── dashboard.html            List / filter / delete posts
│   ├── editor.html                Create / edit a post, incl. picture upload
│   └── admin.js                   Login, route guard, CRUD + image upload wiring
├── api/                           PHP backend — see "Deploying to Hostinger" below
│   ├── config.php                  Database credentials (EDIT THIS)
│   ├── schema.sql                  MySQL tables — run once in phpMyAdmin
│   ├── setup-admin.php             One-time script to create your admin login
│   ├── auth.php                    Login / logout / session check
│   ├── posts.php                   Rules & Thoughts CRUD
│   ├── upload-image.php            Admin picture upload for post covers
│   ├── careers.php                 Careers form submissions + resume upload
│   └── contact.php                 Contact form submissions
└── uploads/                      Older uploads only (see "Where uploads are stored")
```

## Running locally (frontend only, no database)

No build step is required for the static pages:

```
cd ada-law-Chambers
python3 -m http.server 8080
```

Then open `http://localhost:8080`. Rules/Thoughts, the admin panel, and the
Contact/Careers forms won't work until the database is connected (below) —
everything else (Home, About, Team, etc.) works immediately.

To also see the Home page's **latest YouTube videos and gstindia.biz articles**
(served by `api/external-feeds.php`), preview with PHP instead — a plain static
server such as `python3 -m http.server` or VS Code Live Server can't run PHP, so
those two sections fall back to a "watch on YouTube" link / their built-in copy:

```
cd ada-law-Chambers
php -S localhost:8000      # PHP via Homebrew: brew install php
```

Then open `http://localhost:8000`.

---

## Deploying to Hostinger, step by step

This assumes Hostinger shared/business hosting (PHP + MySQL). If you're on
a VPS you can follow the same steps through hPanel or via SSH.

### 1. Create the database

1. Log in to **hPanel** → **Databases** → **MySQL Databases**.
2. Create a new database (or use the one Hostinger auto-provisions). Note
   down the **database name**, **username**, and **password** it shows you.
   The **host** is almost always `localhost` on shared hosting.

### 2. Create the tables

1. Still in hPanel → **Databases**, click **phpMyAdmin** next to your new
   database.
2. Click the **SQL** tab.
3. Open `api/schema.sql` from this project, copy its entire contents,
   paste into phpMyAdmin's SQL box, and click **Go**.
4. You should now see four tables: `admins`, `posts`, `contact_messages`,
   `career_applications`, and two sample rows in `posts`.

### 3. Upload the project files

1. In hPanel → **Files** → **File Manager** (or via FTP/SFTP with a client
   like FileZilla — Hostinger gives you credentials under
   **Files → FTP Accounts**).
2. Upload the **entire contents** of this project folder into
   `public_html` (or a subfolder, if this site lives at a sub-path).
   Everything — the HTML pages, `css/`, `js/`, `admin/`, `api/`,
   `uploads/`, all of it — needs to be on the server together, since the
   frontend calls the PHP API on the same domain.

### 4. Configure the database connection

1. Edit `api/config.php` (directly in hPanel's File Manager code editor,
   or edit locally and re-upload) and fill in the four values from Step 1:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_actual_db_name');
   define('DB_USER', 'your_actual_db_user');
   define('DB_PASS', 'your_actual_db_password');
   ```
2. While you're in there, also change `SETUP_SECRET` to your own random
   string (anything long and hard to guess) — you'll use it once in the
   next step.

### 5. Set folder permissions for uploads

The `uploads/resumes/` and `uploads/posts/` folders need to be writable by
PHP so resumes and post images can be saved there.

1. In File Manager, right-click `uploads` → **Permissions** (or
   **Change Permissions**).
2. Set it to **755** (and apply to subfolders/files if offered). If PHP
   still can't write, try **775** — Hostinger's exact PHP user setup can
   vary slightly by plan.

### 6. Create your admin login

Passwords must be hashed by PHP — you can't just type one into
phpMyAdmin — so there's a one-time setup script for this:

1. In your browser, visit:
   ```
   https://yourdomain.com/api/setup-admin.php?secret=YOUR_SETUP_SECRET&email=admin@adalawChambers.com&password=ChooseAStrongPassword123
   ```
   using the `SETUP_SECRET` you set in Step 4, and your own email/password.
2. You should see `{"ok":true,...}` in the browser. That's your admin
   login — it works immediately at `/admin/`.
3. **Delete `api/setup-admin.php` from the server now** (File Manager →
   right-click → Delete). Leaving it live means anyone who guesses your
   setup secret could create or reset an admin login — it's only meant to
   be used once.

### 7. Test everything

- Visit your domain — the site should load exactly as before.
- Visit `/admin/`, log in with the email/password from Step 6.
- Add a test Rule or Thought, including uploading a picture from your
  device — it should appear on `/rules.html` or `/thoughts.html` once
  published.
- Submit the Contact form and both Careers forms (try attaching a PDF or
  DOCX resume) — open phpMyAdmin → your database → `contact_messages` /
  `career_applications` tables to confirm the submissions (and, for
  careers applications, the resume file path) landed there.

### Viewing submitted messages & applications

Both now have dedicated admin pages:

- **`/admin/messages.html`** — every Contact Us submission, newest first.
- **`/admin/applications.html`** — every Internship/Associate application,
  filterable by type, with a **Download** button per resume. Downloads go
  through `api/download-resume.php`, which checks you're logged in before
  serving the file (a plain link into `/uploads/resumes/` would let anyone
  who guessed the filename download it — this doesn't).

If your database was created before this update, run this one block from
`api/schema.sql` in phpMyAdmin (everything else in that file already
exists on your database, so you don't need to re-run the whole thing):

```sql
CREATE TABLE IF NOT EXISTS important_links (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(255) NOT NULL,
  url           VARCHAR(500) NOT NULL,
  description   TEXT,
  display_order INT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Important Links

A new public page, **`important-links.html`**, listed under the Insights
dropdown (Rules / Thoughts / **Important Links**) and on the `insights.html`
landing page. Manage it entirely from **`/admin/links.html`** — add a
title, URL, optional description, and a display order (lower numbers show
first). No code edits required.

### How public pages are built (SEO)

Every public `.html` page is served through **`render.php`** (see the
render rule in `.htaccess`); the address in the browser doesn't change.
Before the page is sent, the server fills in the shared header and
footer (`partials/`), the breadcrumbs, and the database lists (Rules,
Thoughts, Articles, Magazines, Resources, Important Links), so search
engines see the complete page without running JavaScript. `post.php`
does the same for single posts. The building code is in
**`inc/site.php`**.

- To edit the header/footer: still just `partials/header.html` and
  `partials/footer.html`.
- If you change how a card looks in a `js/*-render.js` file, make the
  same change in the matching function in `inc/site.php`.
- If the database is unreachable, pages still load and the browser
  scripts fill in the lists, exactly as before.

### Spam protection on forms

The Contact and Careers forms carry a hidden field people never see
and record how long the form was open. Submissions that fill the
hidden field, arrive in under 3 seconds, or skip the website's own
form entirely get a normal "thank you" reply but are **not saved**
(see `looks_like_spam()` in `api/config.php`).

### Magazines

A public page, **`magazines.html`**, listed under the Insights dropdown, on
the `insights.html` landing page and in the footer. The **Home page** also
has a **Magazines** section (below the YouTube videos) showing the 3 newest
published issues.

Manage it from **`/admin/magazines.html`** → **Upload Magazine**: a title,
optional issue/edition label (e.g. "October 2026"), optional description,
the magazine **PDF** (max 50MB) and an optional **cover picture**
(JPG/PNG/WEBP, max 4MB — without one the site shows a styled cover with
the title). Save as Draft to hide it, Published to show it.

Files are stored in `persistent-uploads/magazines/` (outside
`public_html`, so redeploys never delete them) and served through
`api/media.php`.

If your database was created before this update, run this one block in
phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS magazines (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  issue         VARCHAR(120),
  description   TEXT,
  file_url      VARCHAR(500) NOT NULL,
  cover_image   VARCHAR(500),
  status        ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

PHP's own upload limit must allow 50MB PDFs: in hPanel → **Advanced → PHP
Configuration**, set `upload_max_filesize` and `post_max_size` to at least
`64M`.

### Where uploads are stored

Every upload — post pictures, articles, magazines, résumés — is saved in
`persistent-uploads/`, the folder **next to** `public_html` (not inside
it), so deploying never deletes them. Pictures and documents are served
through `api/media.php`; résumés are private and only downloadable from
the admin panel. Deleting a post, article or magazine (or replacing its
file) also deletes the old file. `public_html/uploads/` only holds files
uploaded before this change.

### Admin sign-in protection

After 5 wrong passwords from the same connection (or 20 for the same
account) within 15 minutes, sign-in is paused until the 15 minutes pass.

### WhatsApp notifications on form submit

When the Contact form or either Careers form is submitted, the visitor's
browser opens WhatsApp with the details pre-filled, addressed to
`WHATSAPP_NOTIFY_NUMBER` in `js/api-config.js` — they just tap send once
inside WhatsApp. That destination number is **completely independent**
from whatever the visitor typed into the form's own "Phone" field, and
independent from the office phone numbers shown in the header/footer — it
only needs to be any real, WhatsApp-registered number of your choosing.

A fully silent, zero-tap send (message lands on your phone with no action
from the visitor) isn't possible through a webpage — that requires
WhatsApp's official Business API (Meta Cloud API, or a paid provider like
Twilio/Gupshup), which needs business verification and per-message cost.
This pre-filled-message approach is the practical, free equivalent.

### Security notes

- `uploads/.htaccess` blocks any uploaded file from ever being executed
  as a script, no matter its name or extension — this matters because
  the folder accepts files from anyone on the internet (resumes) as well
  as admins (post images).
- `api/config.php` is blocked from direct browser access via
  `api/.htaccess` — it's only ever loaded internally by the other PHP
  files.
- Admin passwords are hashed with PHP's `password_hash()` (bcrypt) — never
  stored or compared in plain text.
- Resume downloads require an active admin session (`api/download-resume.php`)
  rather than being plain public links.
- Delete `api/setup-admin.php` after first use (Step 6) — this is the
  single most important thing to remember post-deployment.

---

## Editing the header, footer, or disclaimer text

*(If your copy of this project has been refactored to use `js/partials.js`
for shared header/footer markup, edit that one file instead of each page.
Otherwise, the header/footer/disclaimer are repeated at the top and bottom
of every page — edit each one directly.)*

## Editing content

- **Team bios / logo / colours / experts:** edit directly in `team.html`,
  `assets/images/`, the `:root` tokens in `css/style.css`, and
  `js/experts.js`.
- **Rules & Thoughts posts:** use the admin panel at `/admin/` — including
  uploading a cover picture straight from your device — no code edits
  required.
- **Important Links:** use `/admin/links.html` — no code edits required.
- **Contact messages / Careers applications:** view at `/admin/messages.html`
  and `/admin/applications.html`, including resume downloads.
- **Floating WhatsApp/Email buttons:** configured at the top of
  `js/main.js` (`CONTACT.whatsappNumber`, `CONTACT.email`).
- **WhatsApp-on-submit number:** configured in `js/api-config.js`
  (`WHATSAPP_NOTIFY_NUMBER`) — can be a different number than the floating
  button above if you want.

## Notes

- The disclaimer overlay (Bar Council of India compliant) shows once per
  browser tab via `sessionStorage` and blurs the page until "I Agree" is
  clicked.
- Resume uploads accept PDF and DOCX only, up to 5MB, validated both by
  file extension and actual file content (not just the filename) in
  `api/careers.php`.
- Post cover-image uploads accept JPG/PNG/WEBP up to 4MB, validated the
  same way in `api/upload-image.php`.

