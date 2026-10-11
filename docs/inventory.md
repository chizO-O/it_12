# Phase 5: property and lot inventory

Implemented October 10, 2026, following the user's approval to begin the next phase after the completed Phase 4 workspace. This phase supports centralized property management and interactive lot inventory using the existing Laravel 12, Blade, Tailwind CSS v4, and vanilla JavaScript stack.

## Implemented behavior

- All six verified roles can browse `/inventory`, filter properties by Toril, Kidapawan, or Kapalong, and inspect individual lots. The default property filter includes only properties with Available lots; All properties includes empty properties and those without available lots.
- Properties open a paginated schematic parcel layout, restyled following the user's visual reference. Available is the default lot filter; all four supported statuses and All statuses are selectable. Counts come from current database records.
- The layout groups saved lots by block, with slanted SVG parcels, illustrative lanes, lot/area labels, a status legend, and zoom/reset controls. Green means Available, amber Reserved, red Sold, and purple Fully Paid. It appears on both the inventory page and the Field Planner management page, above the lot table.
- Each parcel opens a native HTML dialog containing the property reference, lot number, optional block number, area, selling price in Philippine pesos, location, and status. Field Planners can edit available lots from that dialog. A regular details-page link works without JavaScript or native dialog support. Shapes, sizes, lanes, and positions are explicitly schematic and do not represent a surveyed subdivision plan; no coordinates or geographical boundaries were added. Filters and pagination change which lots are shown and their schematic arrangement.
- Only verified Field Planners can access `/properties` and create, update, or delete property and available-lot records. Other roles, including Secretary/Admin, have read-only inventory access under the latest supplied role requirements.
- New lots start as Available. Forms prohibit submitted status and property-reference fields, and controllers explicitly select only writable lot details. Inventory CRUD provides no reservation, sale, or payment status controls.
- Reserved, Sold, and Fully Paid lots cannot be edited or deleted. Update/delete operations lock the parent property and the current lot, then recheck authorization and status within a transaction. A property containing any lots cannot be deleted; the existing foreign-key restriction remains intact.
- Nested route binding prevents accessing or mutating another property's lot by changing URL identifiers. Forms include CSRF protection, field validation, validation feedback, and escaped record text. Malformed old input is safely rendered after validation redirects.
- Sidebar and mobile navigation link to inventory for all roles; Field Planners also have a working management link. Native dialogs provide keyboard dismissal and close buttons; deletion forms request browser confirmation when JavaScript is available.

Field Planner authorization currently covers all property records. The supplied schema has no property assignment or ownership field; assignments are not invented in this phase. The Planner is responsible for entering information authorized and verified outside the application.

## Database changes and dependencies

Added `database/migrations/2026_10_10_110000_add_lot_identity_and_inventory_indexes.php`. It creates:

1. A unique expression index on property ID, normalized block number, and normalized lot number. Case and surrounding spaces do not distinguish identifiers; a missing or empty block is treated alike. The same lot number may exist in another block or property.
2. An index on property location.
3. An index on lot property ID and status.

Before adding indexes, the migration checks existing lots for duplicate identities and stops with an error if any exist. It does not automatically merge, renumber, update, or delete records. The original tables, columns, migrations, connection settings, and `laravel` PostgreSQL schema remain intact. Existing indexes and constraints remain in place.

No package dependencies were added. Models now use existing Laravel factories, decimal casts for area/price, and the supported lot-status enum. Display formatting does not perform transaction or payment calculations. Factories are used by isolated automated tests; no demonstration properties or lots were seeded into Supabase.

The new migration was applied only to the automated SQLite in-memory test database. To enable the database protections in development Supabase, review any existing duplicate identifiers and apply pending migrations using the existing connection:

```powershell
php artisan migrate
```

This command also applies any still-pending migrations from earlier phases. No Supabase migration, reset, or record write was performed while implementing Phase 5.

## Created files

- `app/Enums/PropertyLocation.php`, `app/Enums/LotStatus.php`
- `app/Policies/PropertyPolicy.php`, `app/Policies/LotPolicy.php`
- `app/Rules/UniqueLotNumber.php`
- `app/Http/Requests/SavePropertyRequest.php`, `app/Http/Requests/SaveLotRequest.php`
- `app/Http/Controllers/PropertyController.php`, `app/Http/Controllers/LotController.php`, `app/Http/Controllers/InventoryController.php`
- `database/migrations/2026_10_10_110000_add_lot_identity_and_inventory_indexes.php`
- `database/factories/PropertyFactory.php`, `database/factories/LotFactory.php`
- `resources/views/components/select-field.blade.php`, `resources/views/components/lot-status.blade.php`, `resources/views/components/lot-details.blade.php`
- `resources/views/components/lot-layout.blade.php`, `resources/views/components/lot-dialogs.blade.php`: shared schematic SVG layout and permission-aware lot dialogs.
- `resources/views/properties/index.blade.php`, `resources/views/properties/form.blade.php`, `resources/views/properties/show.blade.php`
- `resources/views/lots/form.blade.php`
- `resources/views/inventory/index.blade.php`, `resources/views/inventory/show.blade.php`, `resources/views/inventory/lot.blade.php`
- `resources/js/inventory.js`
- `tests/Feature/PropertyManagementTest.php`, `tests/Feature/InventoryTest.php`
- `docs/inventory.md`

## Modified files

- `app/Models/Property.php`, `app/Models/Lot.php`: factories, area/price casts, default Available status, status enum.
- `routes/web.php`: protected inventory and scoped property/lot routes.
- `config/workspace.php`: role-specific working inventory/management navigation.
- `resources/views/components/workspace-navigation.blade.php`: active states include child routes.
- `resources/views/components/workspace.blade.php`, `resources/css/app.css`: mobile inventory link, flexible button widths, status-class sources, and dialog backdrop.
- `resources/views/components/form-field.blade.php`: safely render malformed old input.
- `resources/js/app.js`: load inventory interactions.
- `tests/Feature/WorkspaceTest.php`: updated working-route expectations.
- `README.md`, `docs/authentication.md`, `docs/development-review.md`: current phase and setup/report links.
- `public/build/manifest.json` and generated CSS/JS assets: rebuilt production output.

## Verification and remaining checks

- `php artisan test --compact`: **103 tests passed, 1,058 assertions** after the schematic-layout update, including the existing authentication/workspace suite and 34 Phase 5 cases.
- Tests cover all-role read access, Planner-only CRUD, guest/verification restrictions, required fields and decimal bounds, status/parent forgery, protected records, late status changes, duplicate validation and database enforcement, migration duplicate preflight, nested binding, filters, pagination, unique dialog IDs, empty inventory, and HTML escaping.
- `npm.cmd run build`: production assets compile successfully.
- Laravel Pint formatting was applied to the Phase 5 PHP files and checked.
- SQLite exercises migrations and application behavior. It does not prove PostgreSQL locking or concurrent request behavior. Verify the expression index and parent/lot locks against a non-production Supabase database; the late-status-change tests simulate a change between initial authorization and the transactional recheck.
- Headless Chrome checks using unsaved illustrative models confirmed 20 clickable parcels, four status colors, zoom/reset behavior, dialog opening and Escape dismissal, no page overflow at 1280px desktop or 390px mobile, and horizontal scrolling within the mobile layout. Desktop and mobile screenshots were visually inspected. These checks did not read or write Supabase records. Real device, screen-reader, deletion-confirmation, and full signed-in browser checks remain manual.
- Previous deployment checks remain: actual mail delivery, real Google OAuth, and earlier pending migrations. See `authentication.md`.

Agent inquiries/chat, buyer assignment, reservations, CTS tracking, verified payments, and PWA installation remain future phases. The database guarantee against simultaneous active reservations belongs to the reservation phase; lot-identity uniqueness does not implement that workflow. Phase 6 is the next roadmap stage, and requires the user's confirmation before work begins.


## Schematic SVG layout persistence (follow-up to Phase 5)

The subdivision-style layout was already based on Laravel's `lots` table. This follow-up makes **lot positions** optionally persistent in Supabase as well. New migration: `2026_10_10_120000_add_schematic_layout_positions_to_lots.php`.

- Added nullable `lots.grid_row` (1–40) and `lots.grid_column` (1–12). Existing lots remain unchanged and still render automatically.
- Field Planner can set **both** fields in Add/Edit Lot. A unique PostgreSQL index prevents two explicitly placed lots in the same property/block from having the same row and column. Empty/block variants are normalized just like lot identifiers. Invalid or duplicate coordinates are rejected in the form too.
- Rendering reads **every** lot's position in the selected property to calculate the schematic grid, then shows the filtered/paginated lots at those positions. Manually pinned cells stay in place when changing inventory status/page. Unpinned lots fill unused cells automatically; they may shift when new lots are added or identifiers change.
- Lot number, area, price, and status remain sourced from live Eloquent `Lot` records (ultimately Supabase PostgreSQL) on each page request. The status colors and details dialog are unchanged. The SVG links and dialogs now expose `data-lot-id` and `data-property-id` to ease attaching Phase 6 inquiries to the chosen lot.
- No reservation, buyer-agent messaging, or payment status updates are implemented here. Phase 6 should add an authorized inquiry/chat route linked to `lots.id`, replacing a future UI CTA without creating fake conversations.
- The drawing is **schematic**, not a surveyed/legal representation. No geographical coordinate or boundary data is claimed.

After copying the updated files into your existing project, back up your development Supabase database and run `php artisan migrate`. This is additive; **never** use `migrate:fresh`. Then test saving a position as Field Planner, refreshing the Buyer inventory, and filtering statuses; check that a second lot cannot use the same location in a block. Run `php artisan test --compact` and `npm run build` on the Windows development machine.
