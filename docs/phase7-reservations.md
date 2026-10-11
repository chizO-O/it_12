# Phase 7 — Lot Reservations and Buyer Information

Phase 7 extends the Phase 6 inquiry/chat module with an Agent-confirmed reservation workflow. Laravel Blade + Tailwind, Laravel session authentication, and the **same Supabase PostgreSQL** (`laravel` schema) are preserved. No direct Supabase client or second database is needed.

October 11, 2026 refinement: the Buyer's Contact Information now has summary/edit modes, and a separate private Agent assessment and audited factual-correction workflow has been added. See [the refinement report and ERD](phase7-buyer-information.md) for the current permissions, migration, changed files, tests, and setup. The original reservation approval rules described below remain intact.

## Business flow

1. A verified Agent opens a conversation already assigned to them, checks that the lot is **Available**, confirms the buyer's interest, and clicks **Reserve lot & inform Unit Manager**.
2. A single transaction creates a Pending reservation and marks the lot **Reserved**. Database partial unique indexes permit only one active reservation (`pending`/`approved`) for a lot and an inquiry. Active reservations use a PostgreSQL-compatible partial unique index, independently of interface checks.
3. The Buyer and verified Unit Managers receive database notifications. If no verified Unit Manager exists, verified Admins receive an alert to invite one. An Admin may view reservation metadata and buyer form but cannot approve or reject.
4. The Buyer enters their contact number and address as the **buyer_form** row in `buyer_requirements`. This is a textual buyer form, **not** a legal file upload.
5. A Unit Manager reviews and may approve (only after the buyer form exists) or reject (requires a reason). Approval leaves the lot Reserved, ready for Phase 8 CTS tracking. Rejection returns it to Available while retaining reservation history.
6. The assigned Agent may cancel **Pending** reservations. The Unit Manager may cancel Pending or Approved reservations with a reason. Cancellation returns the lot to Available. Other roles cannot alter reservation decisions.
7. A rejected/cancelled inquiry can be reserved again while the lot is Available.

## In scope

- `reservations` and `buyer_requirements` tables with foreign keys and database partial unique indexes.
- Role-restricted routes, controller transactions/locking, notifications, and a reservation list/detail screen.
- Form for buyer contact information; Manager review; Agent cancellation; read-only Admin overview.
- Existing lot map, lot detail dialogs, and Phase 6 conversation preserved.
- Existing Field Planner lot deletion is blocked for any lot with inquiry/reservation history, even after cancellation.
- Inquiry reassignment blocked while a reservation is active, to avoid orphaning the Agent responsible for the reservation.

## Intentionally NOT in this phase

- CTS PDF generation, legal signing, notarization, sale confirmation and changing lot status to **Sold**.
- Payment gateway, cashier verification, installment calculations, or **Fully Paid** status changes.
- Automatic reservation expiration: SGRE has not specified an approved expiry policy.
- Document upload: `buyer_requirements.file_path` is reserved for the later private-storage workflow, not used by these forms.
- A finalized purchase agreement or `transactions` table. An Approved reservation is not a legally completed sale.

## Setup

1. Back up your GroveMap project and development Supabase database.
2. Merge the patch files into your existing `grove_map` folder without overwriting `.env` or other local changes; review differences if Codex has modified files after Phase 6.
3. Verify that Phase 6 migrations are applied. Run `php artisan migrate:status`.
4. Inspect the new `2026_10_10_140000_create_reservations_and_buyer_requirements.php` migration, then run `php artisan migrate` using your **development** Supabase credentials.
5. Run `php artisan config:clear`, `php artisan test --compact`, and `npm.cmd run build` on your Windows machine.
6. Create and verify a `unit_manager` employee through the Admin's Employees screen; the Unit Manager is a separate employee role from the Agent.
7. Sign in as an Agent with a Toril inquiry. Open the conversation and confirm buyer interest, then reserve the lot.
8. Sign in as Buyer. Open **My reservations**, submit the buyer form.
9. Sign in as Unit Manager. Open **Reservation reviews**, inspect the form, then approve or reject.
10. Check that lot status, reservation history, role access, and notifications match the workflow. Verify the same data in Supabase SQL Editor.

## Local tests

New tests in `tests/Feature/ReservationWorkflowTest.php` cover Agent authorization, stock status synchronization, one-active-reservation guarantees, buyer details, Unit Manager approval/rejection, cancellations, history retention, and data privacy. Existing workspace navigation test was adjusted to include new working reservation links. These test suites require the standard Composer packages and PHP extensions available in the user's Windows Laravel installation.

**Important:** The code was packaged from the last shared Phase 6 source, not fetched from the user's live Windows working directory. Confirm merge compatibility before applying it.
