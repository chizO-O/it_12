# GroveMap Phase 7 Revision — Unit Manager-led Agent Assignments

This patch was prepared against the latest GroveMap code snapshot available in this conversation (the previous Phase 5–7 working copy). It is **not** a live sync to your Windows project. Review differences before overwriting any local files changed by Codex.

## Agreed workflow

- Secretary/Admin invites office roles only: Admin, Unit Manager, Field Planner, Cashier.
- Unit Manager invites Agents and supervises them; existing Agent accounts without a manager can be linked using **Manage Agent**.
- Unit Manager assigns **one primary Agent** to each Available lot. One Agent can be assigned to many lots. An assignment does not reserve the lot.
- Buyer inquiries route to the lot's assigned verified Agent. Unassigned lots can receive pending inquiries, which the Unit Manager sees as metadata only; once an Agent is assigned, the pending inquiry is routed to that Agent.
- If a conversation is already assigned to another Agent, lot reassignment is blocked instead of silently transferring private messages. A future explicit transfer workflow may be added later.
- Only the assigned Agent can reserve the lot after confirming buyer interest; only that Agent's supervising Unit Manager reviews it.
- CTS, sales transactions, and payments remain for later phases.

## Install

1. Back up your current GroveMap source and Supabase **development** database. Prefer a branch, and inspect changed files before copying.
2. If you already applied the prior Phase 7 Reservations Patch, use `GroveMap_Phase7_Revised_Delta.zip`; otherwise, use `GroveMap_Phase7_Revised_Combined.zip`, which includes the earlier Phase 7 files and this revision.
3. Extract ZIP file **contents** into the root of `grove_map` while preserving directory paths; review any conflicts before overwriting.
4. Inspect and apply database migrations on a non-production Supabase project first. The new additive migration is `2026_10_10_150000_add_agent_supervision_and_lot_assignment.php`.
5. Run these on Windows from the project root:

```powershell
php artisan migrate:status
php artisan migrate
php artisan config:clear
php artisan route:list
php artisan test --compact
npm.cmd run build
```

6. Test as Secretary/Admin: invite only office employees. As Unit Manager: open **Manage Agents**, claim the earlier Agent account if needed, then open **Lot assignments** and assign that Agent to Lot 2. As Buyer: open Lot 2 and submit an inquiry. As assigned Agent: verify the conversation and reserve the lot. As Unit Manager: review the reservation.
7. Test a lot with **no** Agent; it should save the inquiry as awaiting assignment rather than auto-picking an area Agent.

## Important transition notes

- The old `service_area` field is retained for backwards compatibility but is **no longer used to route new inquiries**.
- Existing Agent accounts are not converted or deleted. Agents created before the revision may have a null `unit_manager_id`; the manager can link them before assigning lots.
- Existing inquiry assignments are preserved; they are **not** automatically mapped to a lot assignment. Assign the same Agent to the lot when an older conversation exists.
- There is no `properties.unit_manager_id` added because responsibility for a whole development/property was not explicitly approved. Unit Managers can see unassigned lots and pending inquiries; the supervising manager is inferred from the assigned Agent once a reservation exists.
- No migrations or data updates were run on your Supabase database by the preparation environment.
- Automated tests and production frontend build could not be executed here because the portable snapshot has no Composer vendor directory and the local PHP lacks required extensions. PHP syntax validation passed; run the full suite on your Windows machine.
- Do not run `migrate:fresh` or `db:wipe` against your existing Supabase project.
