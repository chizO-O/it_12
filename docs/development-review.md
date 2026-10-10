# GroveMap requirements review and phase report

Review date: October 10, 2026 (Asia/Manila).

The supplied requirements are preserved in `project-requirements.md`. Its original current-phase instruction limits work to Phase 3. The user's subsequent chat instruction authorized correcting mismatches and continuing to the next stage; this review therefore covers Phase 3 corrections and Phase 4 dashboards/navigation. Phase 5 business functionality has not been implemented during this review.

Update: the user's later approval to begin the next phase authorized Phase 5. Property/lot management and interactive inventory are now implemented and verified in the [Phase 5 report](inventory.md), which lists its files, migration, permissions, tests, and remaining checks. The findings below preserve the earlier Phases 3 and 4 report.

## Findings and corrections

| Requirement | Result |
| --- | --- |
| Laravel 12, Blade, Tailwind CSS v4, existing Supabase PostgreSQL connection | Retained. No frontend framework or new dependency added in this review. PostgreSQL configuration still uses the `laravel` schema. |
| Six exact roles with backend authorization | Retained and tested. Office roles share a destination, with separate role-specific menus. |
| Public registrations always create Buyers | Retained for email and Google. Submitted privilege, Google identity, and verification fields are ignored. |
| Admin manages employee accounts and roles | Corrected: Admin can now update another employee's role. Self-demotion and changes to Buyer roles through employee administration are forbidden. |
| Duplicate emails and secure Google linking | Corrected capitalization handling for validation, login, and password recovery. A new database index enforces uniqueness. Google linking still requires an authenticated, verified account, password confirmation, matching email, and an unused provider identity. |
| Appropriate validation | Corrected malformed email input handling before Fortify string normalization; the login rate limiter also handles invalid input without a server error. |
| Preserve user, property, and lot tables and migration history | Preserved. Only additive migration files were introduced. Existing Property/Lot models and migrations were left intact. |
| Avoid resetting the existing Supabase database | No Supabase migration or reset was run. Test setup checks for SQLite `:memory:` before any test reset. |
| Excluded modules and secret protection | No payroll, attendance, commissions, gateways, legal-document generation, notarization, or geographical mapping introduced. No credentials added to frontend code. |

## Phase 3 corrections: files

Created:

- `app/Auth/CaseInsensitiveUserProvider.php`: extends Laravel's existing Eloquent provider so login and password recovery use the same case-insensitive email lookup while retaining Laravel password/token handling.
- `app/Rules/UniqueUserEmail.php`: shared case-insensitive validation for Buyer, employee, and first Admin registration.
- `app/Http/Middleware/NormalizeAccountEmail.php`: validates email input types and normalizes form input before authentication actions.
- `database/migrations/2026_10_10_100000_enforce_case_insensitive_user_emails.php`: adds the uniqueness index, without modifying existing account values.
- `tests/Feature/AccountEmailTest.php`: legacy email, duplicate prevention, migration preflight, and malformed-input coverage.

Modified:

- `app/Providers/AppServiceProvider.php`, `config/auth.php`: register and select the case-insensitive Eloquent provider.
- `app/Actions/Fortify/CreateNewUser.php`, `app/Console/Commands/CreateAdmin.php`: use shared email validation.
- `app/Providers/FortifyServiceProvider.php`: safe rate-limit key handling for invalid email input.
- `app/Http/Controllers/EmployeeController.php`, `routes/web.php`, `resources/views/employees/index.blade.php`: employee role update endpoint and form.
- `bootstrap/app.php`: attach email input normalization.
- `tests/Feature/EmployeeAccountsTest.php`: role update authorization and protected account attributes.
- `tests/TestCase.php`, `phpunit.xml`: test database guard and forced isolated service settings.

The original Phase 3 implementation retains Fortify (`^1.41`) for Laravel authentication routes and flows, and Socialite (`^5.31`) for Google OAuth. Both remain backend dependencies in `composer.json`/`composer.lock`. This review adds no packages.

## Phase 4 implementation: files

Created:

- `config/workspace.php`: shared role-specific menu and tool descriptions.
- `resources/views/components/workspace.blade.php`: shared desktop sidebar, mobile expandable menu, and bottom navigation.
- `resources/views/components/workspace-navigation.blade.php`: working route links and disabled future tools, with active-page indicators.
- `app/Http/Controllers/AccountController.php`: authenticated profile name update.
- `tests/Feature/WorkspaceTest.php`: all-role navigation, profile access, privilege-injection, and HTML escaping coverage.

Modified:

- `app/Enums/UserRole.php`: shared Buyer/Agent/Office workspace label.
- `app/Http/Controllers/DashboardController.php`: reads the same navigation configuration as the layout.
- `resources/views/components/layout.blade.php`: integrates the workspace header and mobile navigation spacing.
- `resources/views/dashboard.blade.php`: role-specific tool cards, including the active Admin employee tool.
- `resources/views/account.blade.php`: profile name editing and shared navigation.
- `resources/views/employees/index.blade.php`: shared workspace layout and responsive employee form/table.
- `resources/css/app.css`: workspace and mobile navigation styles.
- `routes/web.php`: profile update endpoint under authentication, session validation, and email verification.

No Phase 4 migrations or dependencies were added. No JavaScript framework is used; the expandable mobile menu uses native HTML `details`/`summary`.

Documentation created/updated: `docs/project-requirements.md`, `docs/development-review.md`, `docs/authentication.md`, and `README.md`.

## Verification and remaining work

- Phase 3 corrections: 54 tests passed before Phase 4 changes; production build and Pint checks passed.
- Phases 3 and 4 combined: 69 tests passed (555 assertions). Production build passed. Pint checks passed after formatting the route imports.
- Tests cover all six role destinations and access restrictions, email/password and mocked Google registration, signed verification, reset/logout, invitations and role changes, uniqueness enforcement, role navigation, and profile authorization.
- The new Supabase migration has not been applied. Run migrations against a non-production Supabase database and verify the index and Google identity column there. The existing configured Supabase database was not reset or edited.
- Google client credentials and a matching callback URI must be configured, and a real OAuth sign-in must be tested.
- Actual email delivery requires SMTP or another configured mail service. The default `log` mailer does not deliver messages.
- Desktop and mobile layouts compile and render in feature tests. Actual browser/device layout, keyboard interaction, and screen-reader testing remain manual checks.
- Property/lot management, inquiry assignment, chat, reservations, CTS uploads, payments, and PWA installation remain later roadmap phases. Their labels in navigation are deliberately disabled placeholders.

Phase 5 is the next business module: property and lot management, location filtering for Toril/Kidapawan/Kapalong, and an interactive card grid. Any editable lot status must respect the specified Agent reservation, transaction, and Cashier payment workflows; inventory CRUD must not bypass them.
