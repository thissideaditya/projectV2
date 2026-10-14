-- =====================================================================
-- ADA LAW Chambers — api/schema.sql
-- MySQL schema for Hostinger hosting.
--
-- HOW TO USE
-- 1. In Hostinger hPanel, create a MySQL database (Databases > MySQL
--    Databases) and note its name/user/password.
-- 2. Open phpMyAdmin for that database (hPanel > Databases > phpMyAdmin).
-- 3. Click the "SQL" tab, paste this entire file's contents, and run it.
-- 4. Put the same database name/user/password into api/config.php.
-- =====================================================================

CREATE TABLE IF NOT EXISTS admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS posts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  category      ENUM('rule', 'thought') NOT NULL,
  title         VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  excerpt       TEXT,
  content       LONGTEXT,
  cover_image   VARCHAR(500),
  status        ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category_status (category, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(190) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  phone         VARCHAR(40),
  subject       VARCHAR(255),
  message       TEXT NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS career_applications (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  application_type  ENUM('internship', 'associate') NOT NULL,
  full_name         VARCHAR(190) NOT NULL,
  email             VARCHAR(190) NOT NULL,
  phone             VARCHAR(40),
  -- internship-only fields (NULL for associate applications)
  college           VARCHAR(255),
  study_year        VARCHAR(60),
  duration          VARCHAR(60),
  -- associate-only fields (NULL for internship applications)
  bar_enrolment_no  VARCHAR(120),
  years_experience  VARCHAR(60),
  practice_area     VARCHAR(190),
  -- shared
  message           TEXT,
  resume_path       VARCHAR(500),
  resume_original_name VARCHAR(255),
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS important_links (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(255) NOT NULL,
  url           VARCHAR(500) NOT NULL,
  description   TEXT,
  display_order INT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS articles (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  description   TEXT,
  file_url      VARCHAR(500) NOT NULL,
  file_type     VARCHAR(20) NOT NULL,
  cover_image   VARCHAR(500),
  status        ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resources (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  category      VARCHAR(190) NOT NULL,
  subcategory   VARCHAR(190),
  title         VARCHAR(255) NOT NULL,
  url           VARCHAR(500) NOT NULL,
  description   TEXT,
  display_order INT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category, subcategory, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: if your database already exists from an earlier deploy, you only
-- need to run the CREATE TABLE blocks above that you don't already have
-- (e.g. just "articles" and "resources" if everything else already
-- exists) — every statement here uses IF NOT EXISTS so it's safe either
-- way to run the whole file again.

-- Sample published posts so Rules/Thoughts aren't empty on first load.
-- Safe to delete from phpMyAdmin once you've added your own.
INSERT INTO posts (category, title, slug, excerpt, content, cover_image, status) VALUES
('rule', 'Understanding Place of Supply Under GST', 'understanding-place-of-supply-under-gst',
 'A plain-language walkthrough of how place-of-supply rules decide whether a transaction attracts CGST/SGST or IGST.',
 'Place of supply determines which government has the right to tax a transaction under GST. Getting this wrong is one of the most common errors businesses make in return filing.',
 'https://picsum.photos/seed/rule-gst-1/800/500', 'published'),
('thought', 'Why GST Litigation Is Entering a New Phase', 'why-gst-litigation-entering-new-phase',
 'With GSTAT benches becoming operational, disputes that once sat in limbo finally have a forum.',
 'For years, taxpayers with GST disputes had no dedicated appellate tribunal to turn to. As GST Appellate Tribunal benches become operational, we expect a meaningful shift in how disputes are resolved.',
 'https://picsum.photos/seed/thought-1/800/500', 'published');

-- NOTE: no admin row is created here on purpose — passwords must be
-- hashed by PHP (password_hash), not pasted in as plain text via SQL.
-- Create your admin login by visiting api/setup-admin.php once after
-- deploying (see README.md for the full step-by-step).
