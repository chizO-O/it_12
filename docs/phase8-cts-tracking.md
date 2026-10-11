# GroveMap Phase 8: CTS tracking — implementation guide

This update was written against the uploaded `grove_map (2).zip` project on October 11, 2026. It **preserves** existing reservations, agent assignments, messages, buyer contact records, buyer assessments, and the SVG subdivision inventory.

## What is included

- **Conversations navigation:** The Agent now has one Conversations link instead of two duplicate Inquiries/Messages links. The Buyer also sees Conversations. The `inquiries` and `messages` tables are unchanged.
- **Buyer contact and assessment:** The uploaded source already includes the requested read-only Buyer's Contact Information summary and optional prefilled editing while Pending Review, plus the Agent-owned Buyer Assessment. This release preserves those existing implementations and tests rather than duplicating them.
- **Phase 8:** Unit Manager initiates CTS after an approved reservation; Field Planner records verification of property/landowner details outside the app; Secretary/Admin uploads externally prepared PDF draft; Buyer acknowledges review or requests changes; Admin uploads executed/notarized PDF and records completion.
- **Notifications:** CTS-stage events are saved to the existing Laravel database notifications infrastructure. Real-time messaging and email are not required.
- **Private Supabase Storage:** Browser code never receives service-role credentials. Laravel checks user authorization before issuing a 60-second document link.

## Required Supabase configuration (manual, one-time)

1. In your existing Supabase project, open **Storage** and create a bucket named `grovemap-cts`. Keep **Public bucket OFF**. Do not upload CTS documents to a public bucket.
2. Add the following **only to Laravel's server-side `.env`** (not to Blade/JS, version control, screenshots, or shared ZIP files):

```dotenv
SUPABASE_URL=https://YOUR_PROJECT_REF.supabase.co
SUPABASE_SERVICE_ROLE_KEY=YOUR_PRIVATE_SERVICE_ROLE_KEY
SUPABASE_CTS_BUCKET=grovemap-cts
```

Get the URL and server-only service-role secret from your **own** Supabase project settings. The password for the PostgreSQL pooler is **not** the Storage service-role key. Never use the publishable/anon key for administrative document uploads. Restrict access to this `.env` and use HTTPS in production. The signed link expires in 60 seconds, but the viewer can share it during that period, so remind users that CTS data is confidential.

3. Run `php artisan config:clear` after configuring the variables.

## Apply patch to existing project

1. Back up both your GroveMap source and your **development** Supabase database. This is an additive migration, but do not apply unreviewed code directly to production.
2. Extract `GroveMap_Phase8_CTS_and_UI_Patch.zip` into the *same* `grove_map` directory as `artisan`, preserving folder paths. Review replacements if another developer or Codex has changed the files after the uploaded ZIP.
3. Examine pending migrations:

```powershell
php artisan migrate:status
```

4. Apply them to your development Supabase database:

```powershell
php artisan migrate
php artisan config:clear
php artisan route:list --name=cts
php artisan test --compact
npm.cmd run build
```

Do not use `migrate:fresh`, `db:wipe`, or manually alter Buyer/Agent roles in the database.

## Check the user workflow

1. Sign in as **Unit Manager**. Open an **Approved** reservation (not Pending Review) and select **Initiate CTS tracking**. The lot stays **Reserved**.
2. Sign in as **Field Planner**. Open **CTS property verification**, check property/owner details **outside** GroveMap, enter notes and record verification.
3. Sign in as **Secretary/Admin**. Open **CTS documents**, upload an externally prepared **PDF** draft (up to 10 MB). If Storage is not configured, the form will return an error rather than silently storing it somewhere else.
4. Sign in as the **Buyer** who reserved the lot. Open **CTS tracking**, read the private draft, then acknowledge review or request revisions. This is **not electronic signing**. Requested revisions return the record to Admin for a replacement draft.
5. Complete physical/authorized signing and notarization **outside GroveMap**. Admin uploads the executed/notarized PDF and records the date. CTS becomes **Completed**; the lot **remains Reserved** pending later sales/payment processing.
6. Try to open another Buyer's `/cts/{id}` and `/cts/{id}/documents/draft` links; both should be denied. A Cashier has no CTS access.
7. Return to **Agent** view. Its sidebar should show one **Conversations** item and preserve the Buyer/Agent message thread.
8. Verify Buyer's Contact Information remains read-only after submission, editable only while reservation is Pending Review. The Agent's separate assessment and contact correction flow are already present in the uploaded source.

## New tables and relationships

- `transactions`: one-to-one `reservation_id` (UNIQUE), payment terms intentionally nullable until Phase 9.
- `cts_records`: one-to-one `transaction_id` (UNIQUE), initiator/Field Planner/author IDs, draft/final private object paths, status, Buyer feedback and timestamps.
- A reservation now has one optional transaction; a transaction has one optional CTS record. Existing reservations and lot statuses are untouched by the new migration.
- The original approved reservation may not be cancelled through the standard Phase 7 action once CTS tracking has started; that requires a separate authorized reversal workflow not implemented in Phase 8.

CTS statuses:
`requested` → `property_verified` → `for_buyer_review` → (`revision_requested` → back to `for_buyer_review`)* or `for_signing` → `completed`.

## Security and scope limitations

- Supabase **PostgreSQL** remains the sole relational database via Laravel's `pgsql` connection. There is no second PostgreSQL server.
- Supabase **Storage** holds private PDF bytes; PostgreSQL holds document paths, statuses and ownership links.
- No automatic CTS generation, online payments, notarization, legal signing, or transfer of land title.
- A Buyer acknowledgment is not an electronic signature or legal contract acceptance.
- CTS completion does **not** mark a lot Sold or Fully Paid; do that only in a later verified transaction/payment workflow.
- Current implementation keeps the latest active draft path/version number rather than a complete versioned-document ledger. Superseded drafts may remain in private storage and should be covered by a future document retention policy.
- If a document uploads successfully but a concurrent request changes the CTS status before the database update, the uploaded file may be left unreferenced. Periodic private-storage cleanup/retention policy should address that before production.

## Verification performed during preparation

- PHP `php -l` syntax checks on new/modified PHP source files: passed.
- Source ZIP and patch ZIP archive integrity: verified.
- **Not executed here:** Laravel test suite, Vite build, local browser/PWA checks, real Supabase migrations, real private-bucket upload and signed-link roundtrip. The sandbox PHP installation lacks `pdo_sqlite`, `mbstring`, `dom`, and other extensions needed for the project's test environment.
