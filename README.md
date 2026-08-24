# Sukli — A Store System

A simple, reliable store management system for Philippine sari-sari stores
and small retail businesses. This is the first release: a responsive web
application (desktop, tablet, mobile) covering POS, inventory, utang
(credit), income/expenses, E-Load and GCash recording, users/roles, reports,
feature management, and audit logging — built to run on ordinary PHP/MySQL
shared hosting (e.g. Z.com).

## Stack

- PHP 8.2+ (no framework, no Composer dependency — plain PDO/MVC-style code)
- MySQL 5.7+/MariaDB 10.3+
- Apache + `.htaccess` (mod_rewrite, mod_headers)
- Vanilla JS, hand-rolled CSS design system (no build step, no CDN dependency)

## Project Structure

```
index.php             Front controller — the whole app's single entry point
router.php            Dev-only helper for `php -S` (see Local Development)
.htaccess             Security headers + routes everything through index.php
assets/               CSS/JS, served directly (css/app.css, js/pos.js, ...)
app/
    Controllers/       Route handlers
    Core/              Router, Database (PDO), Auth, Session, Csrf, View, ...
    Middleware/        Auth / Role / Feature / CSRF gates
    Services/          FeatureService, AuditService, StockService, UtangService
    Views/             Plain-PHP templates (layouts, partials, per-module views)
config/                app.php, database.php (installer-generated + .env)
database/              schema.sql, seed.sql, migrate.php
routes/                web.php
storage/               installed.lock, logs/ (writable)
```

**Deliberately flat, on purpose.** There is no `/public` subfolder to point
a document root at — `index.php` sits at the project root because that's
what a normal shared-hosting upload (or an XAMPP `htdocs/yourfolder`)
serves directly with zero configuration. `app/`, `config/`, `database/`,
`routes/`, and `storage/` live alongside it (each with its own `.htaccess`
denying direct access as defense-in-depth), but nothing sensitive depends
on that `.htaccess` actually working: the one file with real secrets,
`config/installed.php`, is a plain PHP file that Apache/PHP always
*executes* rather than serves as text — so even on a host where `.htaccess`
overrides are restricted, requesting it directly just returns a blank page,
never the database password. See Security Notes below.

The only genuine requirement inherited from using clean URLs (`/login`,
`/pos`, etc. instead of `index.php?page=login`) is that Apache's
`mod_rewrite` is enabled — true of effectively all real PHP shared hosting
and XAMPP's default config, so this isn't something normal installs need to
touch.

## Local Development

The easiest path is the same installer wizard described below — just point
PHP's built-in server at the project root and open it in a browser:

1. Create a MySQL/MariaDB database and user (the installer creates the
   tables; it just needs a database that already exists and a user with
   privileges on it).
2. Serve it from the project root (`router.php` exists only so static
   assets work correctly under `php -S`; Apache/`.htaccess` handles that in
   production instead):
   ```
   php -S 127.0.0.1:8000 router.php
   ```
3. Open `http://127.0.0.1:8000/` — you'll land on the installer
   automatically. Follow the wizard (database details → Owner account →
   store info → Install).

If you'd rather skip the wizard and get demo data instantly (sample
products, a seeded `owner`/`Owner@12345` login), use the CLI path instead:
copy `.env.example` to `.env`, fill in `DB_*`, run
`php database/migrate.php --seed`, then create `storage/installed.lock`
yourself (any content) so the app doesn't redirect you to `/install`.

## Deploying to Z.com Shared Hosting (or any cPanel/XAMPP host)

Sukli installs like WordPress: upload the files, open the site, and a
setup wizard walks you through the rest. No document root to configure, no
SQL to import by hand, no config file to create or edit.

1. **Database**: create a MySQL database and a database user from your
   hosting control panel (phpMyAdmin isn't needed for setup — just note the
   host, database name, username, and password the wizard will ask for).
2. **Upload the files**: upload the entire contents of this project
   directly into your web-accessible folder (`public_html/`, `htdocs/`, or
   whatever your host calls it) — `index.php` should end up sitting right
   inside that folder, not in a subfolder. No document-root change, no
   virtual host edit, no `mod_rewrite`/`AllowOverride` troubleshooting.
3. **HTTPS**: enable HTTPS/SSL for the domain (most shared hosts offer free
   Let's Encrypt certificates) — session cookies are marked `Secure`
   automatically once the request comes in over HTTPS.
4. **Open the site**: visiting the domain for the first time redirects
   automatically to `/install`. The wizard will:
   - Test your database connection before letting you continue.
   - Create all tables, default roles, and Feature Management defaults —
     no SQL file to import.
   - Walk you through creating the Owner account and basic store info.
   - Show a live progress checklist (each item reflects a real completed
     step, not a fake animation) and write `config/installed.php` +
     `storage/installed.lock` for you at the end — nothing to edit by hand.
   - Refuse to run again once installed — visiting `/install` afterward
     shows an "already installed" page instead of letting anyone
     reinstall or overwrite your data.
5. **After installing**: review Settings → Feature Management to
   enable/disable E-Load, GCash, and Utang for your store.
6. **Backups**: Settings → Backup & Restore can download a data-only SQL
   backup at any time. To restore, use your hosting's phpMyAdmin import
   tool (safer than executing an arbitrary uploaded SQL file from within
   the app itself).

The only thing that can stop this from being fully hands-off is a host that
disables `mod_rewrite`/`.htaccess` overrides entirely (very rare on real PHP
hosting) or won't let PHP write to `config/`/`storage/` — the installer's
first check flags exactly that, with a plain-English message, before you
enter any database details.

No Docker, no persistent Node.js process, no Redis, and no root/server admin
access is required at any point — everything runs through normal PHP/Apache
request handling.

## Security Notes

- Passwords are hashed with PHP's `password_hash()` (bcrypt via
  `PASSWORD_DEFAULT`); nothing is ever stored or logged in plaintext.
- All database access goes through PDO prepared statements
  (`app/Core/Database.php`) — no raw string-concatenated SQL.
- Every state-changing (`POST`) request is protected by a per-session CSRF
  token (`app/Core/Csrf.php`), verified before the request reaches a
  controller.
- Role-based access control (Owner / Manager / Cashier) is enforced
  server-side via route middleware (`app/Middleware/RoleMiddleware.php`),
  not just by hiding menu items.
- Login attempts are rate-limited per username (`app/Core/Auth.php`,
  configurable via `LOGIN_MAX_ATTEMPTS` / `LOGIN_LOCKOUT_MINUTES` in
  `.env`) and every attempt (success or failure) is recorded in
  `login_attempts` with IP address and user agent.
- Sessions use `HttpOnly`, `SameSite=Lax` cookies, are marked `Secure`
  automatically over HTTPS, and are regenerated on login.
- Output is escaped by default (`e()` helper, `htmlspecialchars`) wherever
  user-supplied data is rendered.
- Security headers (`X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`, etc.) are set in the project-root `.htaccess`.
- Every significant action (auth, sales, inventory changes, settings and
  feature-flag changes, utang activity, user management) is written to an
  append-only `audit_logs` table, viewable at Settings → Audit Log
  (Owner only).
- Disabling a feature (E-Load/GCash/Utang) in Feature Management hides it
  from navigation, POS, and the dashboard immediately, but never deletes
  historical data.
- The `/install` wizard locks itself out permanently once setup completes
  (`storage/installed.lock`) — every installer route re-checks that lock
  before doing anything, so it can't be used to re-run setup or overwrite
  existing data later. It writes its generated config only once, at the
  very end, and never echoes the database password back to the browser.
- The database credentials the installer writes (`config/installed.php`)
  are stored as a plain PHP file, not a `.env` text file — because
  `config/` now sits inside the same directory a web server serves
  directly (see Project Structure above), a `.env` there would be
  downloadable as plain text if `.htaccess` ever failed to apply (exactly
  the kind of hosting misconfiguration that's common in practice). A
  `<?php return [...]` file doesn't have that problem: any host that can
  run this app at all will execute it rather than serve its source, so
  requesting it directly always returns a blank response.

No system is "hackproof" — this follows a defense-in-depth approach
appropriate for a small-business shared-hosting deployment, not a
guarantee against all possible attacks.

## Default Roles

- **Owner** — full access: settings, feature management, users, reports,
  all records.
- **Manager** — most operational access; no settings/feature management.
- **Cashier** — POS and limited operational records only.

## What's Deliberately Not Built Yet

Per the project scope, the following are future phases and are not part of
this release: the central SaaS/licensing platform, the multi-branch owner
dashboard, the Android app, and BIR fiscal compliance. The database schema
(`organizations` → `stores` → everything else, with `store_id` on every
scoped table) is designed so those can be added later without a schema
rewrite.
