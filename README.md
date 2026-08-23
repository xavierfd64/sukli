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
/public              Web root — index.php (front controller), assets/
/app
    Controllers/      Route handlers
    Core/             Router, Database (PDO), Auth, Session, Csrf, View, ...
    Middleware/        Auth / Role / Feature / CSRF gates
    Services/          FeatureService, AuditService, StockService, UtangService
    Views/             Plain-PHP templates (layouts, partials, per-module views)
/config               app.php, database.php (read .env)
/database             schema.sql, seed.sql, migrate.php
/routes               web.php
/storage/logs         Writable log directory (outside the web root)
```

`app/`, `config/`, `database/`, `routes/`, and `storage/` are **not** web
accessible — only `public/` is meant to be served. Each of those directories
also ships its own `.htaccess` (`Require all denied`) as defense-in-depth,
and the project root `.htaccess` transparently rewrites every request into
`public/` for hosts where the document root can't be pointed at a
subdirectory.

## Local Development

The easiest path is the same installer wizard described below — just point
PHP's built-in server at the project and open `/install` in a browser:

1. Create a MySQL/MariaDB database and user (the installer creates the
   tables; it just needs a database that already exists and a user with
   privileges on it).
2. Serve it (uses `public/router.php`, which exists only so static assets
   work under `php -S`; Apache/.htaccess handles that in production
   instead):
   ```
   php -S 127.0.0.1:8000 -t public public/router.php
   ```
3. Open `http://127.0.0.1:8000/` — you'll land on the installer
   automatically. Follow the wizard (database details → Owner account →
   store info → Install).

If you'd rather skip the wizard and get demo data instantly (sample
products, a seeded `owner`/`Owner@12345` login), use the CLI path instead:
copy `.env.example` to `.env`, fill in `DB_*`, run
`php database/migrate.php --seed`, then create `storage/installed.lock`
yourself (any content) so the app doesn't redirect you to `/install`.

## Deploying to Z.com Shared Hosting

Sukli installs like WordPress: upload the files, open the site, and a
setup wizard walks you through the rest — no manual SQL import, no manual
`.env` editing.

1. **Database**: create a MySQL database and a database user from your
   hosting control panel (phpMyAdmin isn't needed for setup — just note the
   host, database name, username, and password the wizard will ask for).
2. **Upload the files**: upload the whole project (not just `public/`) to
   your hosting account, e.g. to a directory above `public_html/`.
3. **Document root**:
   - If your host lets you set the domain's document root to a specific
     folder, point it at this project's `public/` directory. This is the
     cleanest setup — nothing outside `public/` is reachable over HTTP at
     all.
   - If you're stuck with `public_html/` as the fixed document root (common
     on cheap shared hosting), upload the project so that `public_html/`
     itself **is** this repo's root (i.e. `public_html/app`,
     `public_html/public`, etc.). The root `.htaccess` included here
     rewrites every request into `public/` for you, and the per-directory
     `.htaccess` files block direct access to everything else.
   - Either way, make sure the project root and the `storage/` folder are
     writable by PHP — the installer needs to create `.env` and
     `storage/installed.lock`.
4. **HTTPS**: enable HTTPS/SSL for the domain (most shared hosts offer free
   Let's Encrypt certificates) — session cookies are marked `Secure`
   automatically once the request comes in over HTTPS.
5. **Open the site**: visiting the domain for the first time redirects
   automatically to `/install`. The wizard will:
   - Test your database connection before letting you continue.
   - Create all tables, default roles, and Feature Management defaults —
     no SQL file to import.
   - Walk you through creating the Owner account and basic store info.
   - Show a live progress checklist (each item reflects a real completed
     step, not a fake animation) and write `.env` + a `storage/installed.lock`
     file for you at the end.
   - Refuse to run again once installed — visiting `/install` afterward
     shows an "already installed" page instead of letting anyone
     reinstall or overwrite your data.
6. **After installing**: review Settings → Feature Management to
   enable/disable E-Load, GCash, and Utang for your store.
7. **Backups**: Settings → Backup & Restore can download a data-only SQL
   backup at any time. To restore, use your hosting's phpMyAdmin import
   tool (safer than executing an arbitrary uploaded SQL file from within
   the app itself).

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
  `Referrer-Policy`, etc.) are set in `public/.htaccess`.
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
  existing data later. It writes `.env` only once, at the very end, and
  never echoes the database password back to the browser.

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
