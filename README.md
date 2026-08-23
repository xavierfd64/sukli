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

1. Create a MySQL/MariaDB database and user.
2. Copy `.env.example` to `.env` and fill in `DB_*` (and set `APP_URL` to
   wherever you're serving from, e.g. `http://127.0.0.1:8000`).
3. Build the schema and seed demo data:
   ```
   php database/migrate.php --seed
   ```
4. Serve it with PHP's built-in server (uses `public/router.php`, which
   exists only so static assets work under `php -S`; Apache/.htaccess
   handles that in production instead):
   ```
   php -S 127.0.0.1:8000 -t public public/router.php
   ```
5. Log in at `/login` with the seeded Owner account:
   - Username: `owner`
   - Password: `Owner@12345`
   - **Change this password immediately** (Settings → Security) — it's a
     known default from `database/seed.sql`.

## Deploying to Z.com Shared Hosting

1. **Database**: create a MySQL database and user from your hosting
   control panel, then import the schema via phpMyAdmin:
   - Import `database/schema.sql`, then `database/seed.sql` (or skip the
     seed and create your own Owner account manually — see below).
   - If your hosting gives you SSH + PHP CLI, `php database/migrate.php
     --seed` does the same thing.
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
     `public_html/public`, `public_html/.env`, etc.). The root `.htaccess`
     included here rewrites every request into `public/` for you, and the
     per-directory `.htaccess` files block direct access to everything
     else.
4. **`.env`**: create `.env` from `.env.example` on the server (never commit
   real credentials) with your production `DB_*` values, `APP_ENV=production`,
   `APP_DEBUG=false`, and `APP_URL` set to your real domain.
5. **HTTPS**: enable HTTPS/SSL for the domain (most shared hosts offer free
   Let's Encrypt certificates) — session cookies are marked `Secure`
   automatically once the request comes in over HTTPS.
6. **First login & cleanup**:
   - Log in with the seeded Owner account and change the password right
     away (Settings → Security), or skip `seed.sql` entirely and insert
     your own `organizations` / `stores` / `users` rows (hash the password
     with `password_hash($pw, PASSWORD_DEFAULT)`).
   - Review Settings → Feature Management to enable/disable E-Load, GCash,
     and Utang for your store.
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
