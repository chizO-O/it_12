# GroveMap — Phase 10: Notifications, Reminders, Reporting, Dashboard Summaries

## Included

- **Notifications** link (all six roles) with unread count. It combines the existing Inquiry, Reservation and CTS database alerts with new Sale/Payment alerts. Clicking "View details" marks **only your own** notification read, then opens the relevant detail screen. Authorization on that screen still applies.
- Sales confirmation alerts for the Buyer, assigned Agent, supervising Unit Manager, and verified Cashiers.
- Verified-payment updates to the Buyer, Agent, and supervising Unit Manager. Messages avoid exposing bank details or personally sensitive information.
- **Payment reminders** for unpaid installment entries: once when an installment is within three calendar days of its due date, and once when overdue. Stored in the existing `notifications` table. A small additional `payment_reminder_deliveries` table prevents duplicates on repeated runs; the reminder algorithm applies verified receipts in schedule order. There are no SMS or email messages.
- **Reports** for Admin, Unit Manager, Cashier, and Field Planner. Admin receives organization-wide inventory/sales/collection totals; Unit Manager receives figures for currently supervised Agents and their assigned lots; Cashier sees organization-wide financial totals, and Field Planner only sees property counts. Buyer/Agent cannot access reports.
- Role-aware dashboard counters and one central notification navigation item per sidebar/menu.
- Conversations-only "mark as read" does not clear other notification categories.

## Installation

Back up the existing project and database, then **merge the patch** into your current local GroveMap folder (`grove_map/`), keeping the same relative paths. Preserve your existing `.env` and any recent application edits. The patch includes the previous form-errors and Spot Cash test fixes for compatibility.

Run in PowerShell:

```powershell
php artisan config:clear
php artisan migrate:status
php artisan migrate
php artisan view:clear
php artisan route:list --name=notifications
php artisan route:list --name=reports
php artisan test --filter=Phase10NotificationsReportsTest
php artisan test --compact
npm.cmd run build
```

**Never run `migrate:fresh`, `db:wipe`, or Supabase reset commands.** Migrations affect your existing PostgreSQL connection; make a backup first. All tests should run in the project's isolated SQLite in-memory test configuration.

## Payment reminder scheduler

The reminder command is registered in `app/Console/Commands/SendPaymentReminders.php` and automatically discovered by Laravel. The project schedules it **daily at 8:00 AM Asia/Manila** in `routes/console.php`.

To run reminders once manually for testing (creates actual in-app reminders for outstanding test transactions):

```powershell
php artisan grovemap:payment-reminders
```

For **local development**, keep this running in another terminal if you want automatic daily execution:

```powershell
php artisan schedule:work
```

For a deployed server, configure a scheduler (cron or platform job) to run `php artisan schedule:run` **every minute**. The scheduled command will only fire at its configured Philippine local time.

A reminder is not a payment charge, automatic collection, or guarantee that payment is late: it reflects due dates and verified receipts recorded in GroveMap. Upcoming/overdue reminders are intentionally sent **once per schedule entry per category**, not every day. The job applies verified payments to the oldest scheduled installment first, even when receipts are partial.

## Manual test

1. **Buyer:** open `Notifications`; confirm reservation and CTS alerts are listed. Try marking one as read and opening its linked page.
2. **Unit Manager:** confirm a sale as usual. Confirm new sale alerts are visible to the Buyer, Agent, Manager and verified Cashiers.
3. **Cashier:** record a verified payment. Confirm Buyer and Agent receive an update.
4. **Buyer with an installment transaction:** verify an unpaid installment due within the next three days (in a disposable test transaction), then run `php artisan grovemap:payment-reminders`. It should generate one alert. Running it a second time should not duplicate it.
5. **Admin:** open `Reports`; compare lot counts, sales value, verified collections, and remaining balance with actual database records.
6. **Unit Manager:** ensure `Reports` shows only Agents currently supervised by this manager; ensure Buyers and Agents are rejected with `403`.
7. **Field Planner:** `Reports` shows property totals but not payment values.
8. **All roles:** ensure sidebar and mobile navigation include `Notifications`; unread badge clears after reading.

## Scope constraints

- No changes to reservation approval, CTS settlement, lot statuses, sale confirmation logic, or payment verification rules.
- No commissions, payroll, loan scoring, payment gateways, external messaging, or online payment collection.
- CTS documents remain in private Supabase Storage; this update does not alter their credential handling.
- Report totals are operational summaries of recorded entries, not externally audited financial statements.
