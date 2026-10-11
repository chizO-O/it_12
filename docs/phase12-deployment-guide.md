# GroveMap Phase 12 — deployment guide (provider-neutral + Railway example)

GroveMap is **not automatically deployed by this patch**. These are deployment steps for your Laravel web app, while keeping **Supabase PostgreSQL and private CTS Storage** as external services. Confirm hosting provider before final commands.

## Recommended simple architecture

Browser over HTTPS -> Laravel PHP application (host) -> Supabase PostgreSQL Session pooler (5432, schema `laravel`) and private CTS Storage.

A single Laravel scheduler process runs the daily payment-reminder command. Add a Laravel queue worker if you start using queued mail or notifications; current database notifications are written synchronously.

## 1. Publish source safely

1. Create a private GitHub repository with the latest tested GroveMap code (not an old ZIP or generated temporary source).
2. Verify `.gitignore` excludes `.env`, `.env.production`, `node_modules`, `vendor`, `public/build`, `public/hot`, `storage/*.key`.
3. Scan staged Git diff and commit history for secrets and sensitive buyer/seller data; revoke exposed keys if necessary.
4. Select a host that supports PHP 8.2+, required extensions (`pdo_pgsql`, `mbstring`, `fileinfo`, `intl`, `openssl`), Composer, Node.js build, and HTTPS.

## 2. Production environment

Use `.env.production.example` as a **reference**, not as a file containing live keys in the repository. Configure values in the host's private variables/secrets UI. Maintain a stable `APP_KEY` across redeploys. Set `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, and `MAIL_MAILER=smtp` (real email provider).

Use the Supabase **Session pooler** endpoint from Dashboard -> Connect on port 5432. **Do not** use the transaction pooler (6543) without specifically adapting ORM prepared statements. SSL is required. Keep the configured `laravel` schema.

## 3. Platform example: Railway + your existing Supabase project

Official guide: https://docs.railway.com/guides/laravel

1. In Railway, create a new project -> Deploy from **private GitHub repository**. Railway can auto-detect Laravel and serve it with Caddy and PHP-FPM. Do **not** add a second Railway PostgreSQL service because GroveMap already uses Supabase.
2. Add host environment variables from `.env.production.example` into Railway's variables UI, with your private production values. Build assets with `npm ci && npm run build` as part of your deploy build; confirm Composer production dependencies also install.
3. Generate a Railway domain or bind your own domain; update `APP_URL` to its **https://** URL. Update Google OAuth's authorized redirect URI to match. Verify trusted reverse-proxy headers and secure session cookies.
4. **Before running migrations**, back up your existing Supabase database, and review `php artisan migrate:status`.
5. Run `php artisan migrate --force` **once** in a controlled deployment step. Run `php artisan optimize` after production env variables are present. Do not run `migrate:fresh`, `db:wipe`, seed destructive data, or `key:generate` on an existing live site.
6. Create a second **scheduler service** using the same code and DB variables. Use a long-running `php artisan schedule:work` command, and ensure only one instance is running. Alternatively use a daily cron task calling `php artisan grovemap:payment-reminders` at 00:00 UTC (08:00 Asia/Manila). **Choose one approach only.**
7. Add a worker service `php artisan queue:work` only if queued jobs are used. Keep it distinct from the web service.
8. In the web service console, run `php artisan grovemap:preflight --probe-storage` and check the exit code. Visit `/up`, then test log-in, CTS private PDF upload/retrieval, Buyer balances, and the payment alerts.

## 4. Recovery / rollback

- Keep previous application release and dependency lock files for rollback.
- **Database migrations are not always reversible safely.** Do not rollback blindly after records are written. Restore from a tested backup to a *separate* environment first.
- For a failed new deployment, roll back application release. If schema changed, restore only after assessing new writes; never automatically delete customer records.
- Keep Supabase Storage bucket and keys under a controlled account, monitor logs, and have a backup procedure for uploaded CTS PDFs.

## 5. Operational verification after go-live

- `https://YOUR_DOMAIN/up` healthy, main page renders, no debugging stack traces.
- Login, registration, real email delivery, Google login, role dashboards.
- CTS per-lot records private; Buyer cannot download another Buyer's contract.
- Cashier receipt leads immediately to a Buyer notification and correct balance.
- Scheduled reminder delivered at 08:00 PHT without duplicates.
- Mobile screens, SSL redirect, security headers, and response time acceptable.
- Monitor daily error logs and disk usage; establish maintenance and incident response owner.

### Important

**Do not run automated tests or destructive migrations against your live Supabase project.** Current GroveMap PHPUnit tests use in-memory SQLite by design. Use staging for end-to-end tests with fictional data.
