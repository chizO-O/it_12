# GroveMap Phase 9 + Requested Phase 7/8 UI refinements

This patch is based on the user's `grove_map(1).zip`. It contains only source-code changes. It **does not contain** `.env`, database credentials, any database dump, local PDFs, or private files.

## What changed

- **Conversations:** Existing single Conversations navigation and inquiry/message database design are preserved; no duplicate link added.
- **Buyer's Contact Information:** Existing Buyer form → saved read-only view → Edit Information flow is preserved; editing is locked after Unit Manager approval.
- **Agent Buyer Assessment:** After first submission, show read-only details with Edit Information, instead of a permanently open form. Edits continue during Pending/Approved and CTS processing, but are blocked when CTS is Completed or the reservation is cancelled/rejected. Laravel rechecks authorization and takes a CTS row lock before saving.
- **Field Planner Landowner Verification:** Structured seller/landowner name, contact number, address, relationship to lot, optional verification remarks and verification checkbox. Saved submissions display as read-only with Edit Information; Field Planner edits through CTS processing, but not after Completed. Buyer cannot see confidential seller contacts. Existing verification notes and verification timestamps remain intact.
- **Phase 9 — Sales & Payment Tracking:** Unit Manager confirms the actual selling price and spot cash/0%-interest installment terms only after externally signed/notarized CTS is recorded. The lot becomes Sold. Schedules are generated with exact cent calculations. The Cashier records verified, off-platform payments with unique receipts and payment dates; the system rejects overpayments and recalculates the balance. When fully paid, transaction and lot become Fully Paid. Buyer sees their own schedule, payments and balance. Other roles have restricted access.
- **CTS Storage diagnostics:** When Storage rejects a PDF, Laravel logs an HTTP status/error code/message without logging the key, uploaded document or signed URL. Fixing the underlying Supabase bucket/key/network issue may still be necessary.

## Installation (Windows / VS Code)

1. **Back up your current application and database first.** Do not test changes directly on irreplaceable production records.
2. Unzip **GroveMap_All_Requested_UI_and_Phase9_Patch.zip** into your existing `grove_map` root, preserving directories, and review overwritten files.
3. Keep your existing `.env`. This patch does not provide, require you to share, or modify your keys. Supabase Storage's URL/key are still required **on your private server** for CTS PDF uploads.
4. Run:

```powershell
php artisan config:clear
php artisan migrate:status
php artisan migrate
php artisan view:clear
php artisan route:list --name=sales
php artisan test --compact
npm.cmd run build
```

Migrations are **additive** (new nullable CTS fields and Phase 9 payment tables). Do **NOT** run `migrate:fresh`, `db:wipe`, `migrate:refresh`, or anything that drops Supabase data.

Tests are forced to use `sqlite :memory:` in `phpunit.xml` and guarded in `tests/TestCase.php`.

## Suggested manual test path

1. Sign in as Agent. Submit Buyer Assessment. The form should disappear; Edit Information should reopen it. Save Changes/Cancel should work. An Approved reservation must still allow edits while CTS is not Completed.
2. Sign in as Field Planner. Open an initiated CTS and fill Landowner Verification Details. Submit; only summary and Edit Information should remain. Edit and save; verify the current CTS state is not reset. With Completed CTS, Edit must disappear and direct PATCH must be rejected.
3. Sign in as Secretary/Admin. Upload and complete CTS through the existing private Storage workflow. The CTS PDF upload error must be resolved before you can finish a real CTS; it is separate from Phase 9.
4. Sign in as Unit Manager. Open **Sales transactions**, view the completed CTS transaction, and confirm final actual agreed price/terms. The lot changes Reserved → Sold; the correct schedule appears. You cannot confirm a sale before CTS completion or confirm twice.
5. Sign in as Cashier. Open **Payment records**, verify a physical payment received outside the app, enter the amount/reference, and confirm. Duplicate reference and overpayment attempts must fail. Once all payments equal the agreed price, the lot changes Sold → Fully Paid.
6. Sign in as Buyer. Open **My payments** and confirm that the payment history/balance is visible for only their transaction. No payment creation controls should appear.
7. Verify desktop and mobile Conversations navigation; it should show only one Conversations link per menu.

## Design limits

- No payment gateway, financial scoring, loan application, commission split, CTS legal drafting, online signatures or automatic legal title transfer.
- Payment records are **cashier-verified** when created; the current feature does not implement voids, reversals, refunds or a two-person payment approval process. Those should be designed separately before production accounting use.
- Bills are generated once from final agreed terms; changing already confirmed terms requires a separate controlled amendment workflow.
- The final CTS remains a private PDF in Supabase Storage with temporary signed URLs.

## What I could verify here

- PHP syntax checks of changed code and migration files.
- ZIP archive validity and exclusion of `.env`/secrets/runtime files.

Laravel integration tests and live Supabase Storage could **not** be run in this sandbox (PHP SQLite/DOM extensions and real Supabase credentials are not available). Run the listed commands on your Windows machine and report any failures.
