# GroveMap Phase 11: production readiness and acceptance

> Scope: Laravel 12, Blade, Tailwind v4, Supabase PostgreSQL in the `laravel` schema, and private Supabase CTS PDFs. This is a readiness aid, **not** proof that the site is secure or deployed.

## 1. Before any real deployment

- [ ] Back up Supabase data and confirm you can restore it. Document schema migrations and rollback plan.
- [ ] Use a private GitHub repository; never add `.env`, storage logs, personal data, keys, or Google OAuth secrets to source control.
- [ ] Test in a separate staging environment/project with fictional buyer and seller records. Do not test money collection against real customers.
- [ ] Choose a Laravel-compatible PHP host with a *public/* web root, HTTPS, persistent environment variables, and a scheduler.
- [ ] Configure production `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`.
- [ ] Preserve a valid `APP_KEY`. **Do not regenerate an already-used key on each deployment.**
- [ ] Configure Supabase **Session pooler**, port 5432, SSL required, DB_SCHEMA=laravel, and a real mail provider for resets/verification/invitations.
- [ ] Configure private `grovemap-cts` Storage bucket and server-only key. Do not expose signed URLs or Storage secrets in public JS.
- [ ] Configure Google OAuth redirect URI `https://YOUR_DOMAIN/auth/google/callback` on Google Cloud. Test password login and Google login.
- [ ] Configure trusted reverse proxies/HTTPS headers for your actual platform and verify HTTPS URLs/sessions, especially behind load balancers.
- [ ] Build frontend with `npm ci` and `npm run build`; never publish `public/hot`.
- [ ] Ensure only the **public/** directory is web-accessible and no `.env`, logs, backups, SQLite files, or credentials can be downloaded.
- [ ] Verify the `/up` health endpoint and application logs; plan monitoring and restore drills.
- [ ] Enable scheduler for `grovemap:payment-reminders`, daily 08:00 Asia/Manila, in **exactly one** running scheduler service.
- [ ] Consider data privacy policy, legitimate data collection, retention and access: age, marital status, nationality, salary and CTS documents are sensitive personal records and must be handled appropriately.

## 2. Safe commands on your development computer (PowerShell)

```powershell
php artisan config:clear
php artisan test --compact
php artisan route:list
php artisan schedule:list
npm ci
npm run build
```

Tests in GroveMap are guarded to use only SQLite `:memory:`. **Never** run `php artisan migrate:fresh` on Supabase.

## 3. Server checks *after* production environment variables are installed

```bash
php artisan migrate:status
php artisan migrate --force       # Only after a backup and migration review
php artisan optimize
php artisan grovemap:preflight --probe-storage
php artisan route:list
php artisan schedule:list
```

`grovemap:preflight` is read-only; its default run checks PostgreSQL and only probes the private bucket when `--probe-storage` is specified. It never prints keys. `--skip-database` is for troubleshooting, **not** final go-live signoff.

## 4. Manual role-by-role acceptance — staging with fictional records

1. Buyer: register, verify real email, log in with password and Google, reset password, log out, see only own records.
2. Field Planner: create lots, confirm layout and inventory, verify seller details, edit until CTS completion, confirm lock after completion.
3. Unit Manager: invite Agent, assign primary Agent to available lot, review reservation, confirm sale terms only after completed CTS.
4. Agent: receive only assigned-lot conversations, create a reservation, submit/edit Buyer Assessment, confirm CTS completion prevents edits.
5. Secretary/Admin: invite employees, open CTS after approved reservation, save agreement, privately upload draft and final externally signed PDF, confirm Buyer download access.
6. Cashier: verify payment with distinct reference; verify overpayment and duplicate reference rejected; balance and lot statuses update correctly.
7. Buyer: see CTS for own lot, request draft revision, view verified payment receipt, payment reminder, remaining balance; another Buyer cannot retrieve the document.
8. Notifications: timestamps in Asia/Manila, unread/read behavior and immediate payment receipt alerts.
9. Reports: Admin totals; Unit Manager sees only supervised Agents; Field Planner sees property-only reports; Buyer cannot open office reports.
10. Mobile: test phone portrait/landscape, tab order, keyboard labels, touch inputs, form errors; no exposed debug traces.
11. Negative tests: direct URL access to another Buyer's inquiry, CTS, reservation, sale, and private document must be rejected by server-side authorization.
12. Backup + restore: test database restore to a separate environment and CTS private PDF recovery.

## 5. Go / No-Go gates

GO only when: all automated tests pass, critical role/privacy checks pass, Vite assets compile, real email and Google OAuth work, CTS private upload/download works, payment reminder scheduler runs once daily, preflight has zero failures, backups are tested, and the deployment provider has HTTPS enabled. Do not rely on `php artisan test` alone.

NO-GO if: `APP_DEBUG=true`, secrets leak into HTML/logs/Git, CTS is publicly accessible, payment notifications fail, database schema mismatch, reminder scheduler absent, no email delivery, or backup untested.
