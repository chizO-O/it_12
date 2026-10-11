# GroveMap — Phase 6: Buyer–Agent Inquiries and Messages

## What this phase adds

- The Buyer sees **Chat with Agent** in the popup and detail page for each **Available** lot.
- The first message creates one inquiry per Buyer+lot, stored in the existing Supabase PostgreSQL `laravel` schema. Repeated clicks reopen that thread rather than duplicating it.
- Inquiries auto-route to an **email-verified Agent** with a matching **service area** (`Toril`, `Kidapawan`, or `Kapalong`). With more than one matching Agent, the least-assigned Agent is selected; ties go to the oldest account.
- If no matching Agent exists, the inquiry and initial message are still saved. The Admin receives a database notification and can assign an appropriate Agent from **Inquiry assignments**.
- Buyers and their currently assigned Agents can access the conversation. Admin can see routing metadata, but **cannot read chat messages**. Other buyers and employees cannot open the thread.
- Reply submission is server validated, CSRF-protected, and rate-limited. Incoming unread-message counts are shown in the inbox. The conversation page polls for new messages every 10 seconds (no separate realtime server required). Notifications are stored in the database, not emailed.
- No reservation, lot status, CTS, payment, or commission logic was added; these stay in later phases.

## Local setup and Supabase

This patch assumes the Laravel 12 + Blade + Tailwind v4 project with the Supabase PostgreSQL Session Pooler already configured via `DB_CONNECTION=pgsql` and `DB_SCHEMA=laravel`. **`pgsql` is Laravel's PostgreSQL driver; Supabase remains the actual database.** No second database is needed.

1. Back up your development project and Supabase data.
2. Apply only the changed files to your project, preserving the folder paths. If you have not yet applied the prior Phase 5 map-position patch, apply the **combined Phase 5+6 patch** instead.
3. Review the new migration `2026_10_10_130000_create_inquiries_and_messages.php`.
4. Run the migration in your local project:

   ```powershell
   php artisan migrate:status
   php artisan migrate
   php artisan migrate:status
   ```

5. Rebuild the assets and execute existing tests:

   ```powershell
   php artisan config:clear
   php artisan test --compact
   npm.cmd run build
   ```

6. As **Admin**, open **Employees** and assign your current Agent a **service area** corresponding to the property's location. Newly invited Agents can have their area selected when creating the employee account.
7. As **Buyer**, browse to a property, click an Available lot, write an inquiry, then press **Chat with Agent**.
8. As the matching **Agent**, open **Inquiries** and reply; test the Buyer account in a separate browser session.
9. As **Admin**, open **Inquiry assignments** to route any inquiry waiting for an Agent.

## Database objects introduced

Migration adds a nullable `users.service_area` value and three tables:

- `inquiries`: `buyer_id`, `lot_id`, optional `agent_id`, timestamps, unique Buyer+lot pair.
- `messages`: `inquiry_id`, `sender_id`, `body`, optional `read_at`, timestamps.
- `notifications`: Laravel's database notification table.

Agent `service_area` is a routing setting, not an authorization bypass. Changing an Agent's area does not automatically reassign historical inquiries; Admin can manually reassign them. Only the assigned Agent can view a given thread.

## Important limitations and checks

- The message display uses 10-second polling, **not WebSockets**. It requires internet access and an open conversation page.
- Notification emails are **not** sent in this phase. Alerts are available inside the inquiry inbox.
- Admin assignment requires a verified Agent whose service area matches the lot's property location.
- Previously created Agent accounts will have a blank service area until Admin sets one.
- Chat does not reserve a lot. Reserving a lot is Phase 7 and must enforce its own atomic reservation guarantees.
- SVG lot shapes remain illustrations, not legal survey boundaries.
- Test on Windows/your own development Supabase: this offline Linux environment did not have the required PHP `mbstring`, `dom`, `xmlwriter`, or SQLite driver, and the installed Node packages came from Windows (the Linux Rollup native module was unavailable). PHP lint and JavaScript syntax checks passed, but full PHPUnit and Vite build could not be executed here.
