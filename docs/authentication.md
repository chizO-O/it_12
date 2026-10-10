# GroveMap authentication setup

GroveMap uses Laravel 12, Blade, Tailwind CSS 4, Fortify, and Socialite. Laravel owns application sessions and user authentication; Supabase remains the PostgreSQL database in the existing `laravel` schema. Phase 3 authentication, Phase 4 shared dashboards/navigation/profile name editing, and Phase 5 property/lot inventory are implemented. See [inventory setup](inventory.md) for its migration and role permissions. Messaging, reservations, CTS, payments, and PWA functionality remain later roadmap steps.

## Local setup

Keep your existing Supabase connection settings in `.env`. Use a development database when setting up this phase.

```powershell
composer install
npm.cmd install
php artisan migrate
php artisan grove:create-admin
```

The migration adds a unique, nullable Google identity and allows a nullable password for Google-only and invited employee accounts. Existing migrations and property/lot data are preserved. Rolling back this migration removes the Google identity column but deliberately keeps passwords nullable so existing accounts are not destroyed.

A second migration adds a unique index on `LOWER(email)` to prevent duplicate accounts that differ only in capitalization. It preserves stored email addresses and stops with an actionable error if existing case-insensitive duplicates are found. Resolve those records deliberately before rerunning the migration; it does not automatically merge or delete accounts. Login and password recovery also recognize existing mixed-case email addresses.

The Admin command asks for a name, email, and password entered privately twice. It requires at least 12 characters, sends an email verification notification, and refuses to run if an Admin already exists. No default Admin password is included. Create subsequent employees from the Admin dashboard.

Start Laravel and Vite in separate terminals:

```powershell
php artisan serve
```

```powershell
npm.cmd run dev
```

Open `http://localhost:8000`. Set `APP_NAME=GroveMap` and `APP_URL=http://localhost:8000` in `.env` so outgoing email links point to the running application. Run `php artisan config:clear` after changing environment configuration.

## Email

Laravel's default `MAIL_MAILER=log` writes messages, verification URLs, and password reset URLs to `storage/logs/laravel.log`; it does not deliver email. Use this for local development. Configure an SMTP or supported mail provider for actual delivery before employee onboarding or deployment. Keep secrets in `.env`.

Buyer registration sends a verification link. An invited employee receives a password reset link to set their first password; setting an unverified account's password sends a separate verification link. Unverified accounts can sign out or request another verification email but cannot enter private dashboards. If delivery fails while inviting an employee, the account remains visible and the Admin can resend its invitation. Reset links expire after 60 minutes; requests for the same account are throttled by Laravel's password broker.

## Google sign-in

Create a Google OAuth web application, configure its consent screen and permitted test users as appropriate, and add this exact authorized redirect URI for local development:

```text
http://localhost:8000/auth/google/callback
```

Set:

```dotenv
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

The Google button is hidden until credentials are configured. Redirect and callback endpoints show a useful error if configuration is missing. Socialite uses the browser session to validate OAuth state; stateless OAuth is not enabled. The callback accepts only verified Google email details. New Google users become verified Buyers with no local password; Google access tokens are not stored.

An email match alone never links or signs into an existing account. An existing Buyer or employee signs in with their GroveMap password, verifies their email, then uses **My account → Connect Google account**. Password confirmation and a matching Google email are required. Connecting Google preserves the role assigned by the Admin. Returning users are identified by the unique Google identity.

## Roles and authorization

| Stored role | Display name | Dashboard |
| --- | --- | --- |
| `buyer` | Buyer | `/buyer/dashboard` |
| `agent` | Agent | `/agent/dashboard` |
| `unit_manager` | Unit Manager | `/office/dashboard` |
| `field_planner` | Field Planner | `/office/dashboard` |
| `admin` | Secretary / Admin | `/office/dashboard` |
| `cashier` | Cashier | `/office/dashboard` |

Public email and Google registrations always receive `buyer`, regardless of submitted role or verification fields. Only verified Admins can view employee accounts, create an employee, resend an invitation, or update another employee's role. Admins cannot change their own role or change a Buyer through employee administration. This retains an active administrator and keeps public Buyers outside the employee role form. Employees set their own passwords through email links. Privileged attributes are excluded from the User model's mass-assignment allowlist.

Laravel's session authentication middleware rejects existing password-based sessions after their stored password changes. Passwordless Google accounts continue to use their provider identity.

Office users share a dashboard destination, but future business operations must each receive their specific authorization checks. Access to the Office dashboard alone does not authorize property changes, reservations, CTS processing, or payments. Planned tools are visibly marked as coming soon.

The three workspace groups share a Blade sidebar, a native expandable mobile menu, and mobile bottom navigation. Menu contents follow the exact role. Unimplemented tools have no working links. The My account form updates only the signed-in user's name; email addresses, passwords, verification state, Google identities, and roles cannot be changed through it.

## Verification

```powershell
php artisan test
npm.cmd run build
```

PHPUnit forces SQLite `:memory:` for its feature tests and checks that configuration before test database setup. If a cached configuration still points to another database, tests stop before resetting anything. Notification, queue, cache, and session settings are also isolated. Tests exercise registration, all six dashboard roles, invitations and role updates, password reset, signed verification links, logout, rate limiting, case-insensitive duplicate prevention, profile authorization, workspace navigation, and Google callback/linking security. Google callback payloads are mocked; OAuth state generation and missing-state rejection also exercise the actual Socialite provider without external requests.

Before declaring this phase ready for deployment, verify email delivery, a real Google sign-in, and migrations against a non-production Supabase/PostgreSQL database. The automated SQLite tests do not verify PostgreSQL-specific behavior or live Google credentials.
