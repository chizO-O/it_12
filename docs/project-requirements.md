# GroveMap — Project Context and Development Requirements

You are assisting with the development of **GroveMap: A Web-Based Real Estate Management System for Sacred Grove Real Estate Marketing (SGRE)**.

Please follow the existing `ai-context.md` instructions and the project-specific requirements below throughout development.

## 1. Technology Stack

Use the following technologies:

- **Backend:** Laravel 12 (PHP)
- **Frontend:** Laravel Blade Templates
- **Styling:** Tailwind CSS v4 and custom CSS
- **JavaScript:** Vanilla JavaScript where needed
- **Database:** Supabase PostgreSQL
- **Document Storage:** Supabase Storage
- **Authentication:** Laravel authentication, with Google OAuth support
- **Platform:** Progressive Web Application (PWA)

Do not introduce Vue.js, React, Inertia.js, or another frontend framework unless I explicitly approve it.

Preserve the existing project structure, database connection, migrations, and implemented features.

The Supabase database uses the `laravel` PostgreSQL schema.

## 2. Primary System Objectives

GroveMap must address five core objectives:

1. Centralized Land and Property Management
2. Interactive Property and Lot Inventory Management
3. Centralized Buyer and Transaction Management
4. Payment and Installment Monitoring
5. Integrated Communication and Notification

Every major feature should support one or more of these objectives.

Do not introduce unnecessary modules unrelated to the documented problems.

## 3. System Users and Roles

The system has six user roles:

**Buyer**
- Register using a valid email or Google account
- View available properties and lots
- Communicate with assigned agents
- Submit buyer requirements
- Review CTS documents and transaction progress
- View payment schedules and remaining balances

**Agent**
- Respond to assigned buyer inquiries
- Communicate with potential buyers
- Confirm buyer interest
- Reserve available lots
- Notify the Unit Manager of reservations

**Unit Manager**
- Review buyer information and reservations
- Validate buyer transactions
- Approve proposed transaction terms
- Initiate CTS tracking

**Field Planner**
- Add and update authorized property information
- Maintain lot details and inventory
- Verify property information with landowners outside the application

**Secretary / Admin**
- Manage employee accounts and roles
- Manage authorized transaction records
- Upload and update CTS documents and statuses
- Oversee system operations

**Cashier**
- Record and verify actual payments
- Maintain payment records and receipts
- Confirm paid installments

Implement authorization on the backend using Laravel middleware, policies, or gates. Hiding buttons in Blade is not sufficient security.

## 4. Property and Lot Management

GroveMap operates across three locations:

- Toril
- Kidapawan
- Kapalong

Buyers select a location and view its available properties and lots.

Each lot should contain:

- Property reference
- Lot number
- Optional block number
- Area in square meters
- Selling price
- Current status

Supported lot statuses:

- Available
- Reserved
- Sold
- Fully Paid

A lot becomes Reserved when an authorized Agent confirms buyer interest and successfully creates a reservation.

The database must prevent simultaneous active reservations for the same lot.

GroveMap currently has no verified subdivision layout plans. Start with an interactive grid of lot cards. Do not generate fictional geographical boundaries or represent the grid as an accurate land survey.

## 5. CTS Tracking

GroveMap will only manage CTS document uploads and status monitoring.

The Unit Manager initiates CTS processing, the Field Planner verifies property and landowner information, and authorized office personnel prepare the documents outside the system.

The Admin uploads CTS documents and manages their recorded statuses.

The buyer may review authorized documents and monitor progress.

Do not implement automated legal contract generation, electronic notarization, or property ownership transfer.

## 6. Payment Management

GroveMap does not process online payments.

Buyers may choose an agreed spot-cash or installment payment arrangement.

- Spot cash creates one payment schedule entry.
- Installments create payment schedule entries based on the agreed payment terms.
- Only the Cashier can record and verify payments.
- Verified payments automatically update the remaining balance.
- Buyers can only view their own payment information.
- The system should support partial payments and correct monetary calculations.

The system must never mark payments as Paid simply because the buyer claims to have paid.

## 7. Authentication Requirements — Current Phase

Phase 3 is currently being implemented.

Before marking this phase complete, ensure:

- Buyer registration works using email and password.
- Email verification is supported.
- Google authentication is integrated securely.
- Public registration always creates a `buyer` account.
- Buyers cannot assign themselves employee roles.
- Employee accounts are created or authorized by the Admin.
- Login redirects users to the appropriate dashboard based on their role.
- Unauthorized access to other roles' routes is prevented on the server.
- Password reset and logout work correctly.
- Google-only accounts are supported without requiring a local password.
- The system handles duplicate emails and Google account linking safely.
- Existing Laravel user, property, and lot tables remain intact.

Do not recreate existing migrations or reset the database.

## 8. Excluded Features

Do not implement the following unless specifically requested:

- Payroll management
- Attendance management
- Commission computation and approval
- Online payment gateways
- Automatic CTS generation
- Advanced accounting modules
- Real geographical mapping without verified lot plans

## 9. Development Rules

Before modifying code, inspect the relevant existing files and dependencies.

Make small, maintainable changes.

Do not overwrite working functionality or perform unnecessary restructuring.

Do not introduce new dependencies without explaining their purpose.

Never expose Supabase database credentials, private keys, passwords, or other secrets in frontend code.

Use Laravel migrations for database changes and keep existing migration history intact.

Use appropriate validation, CSRF protection, authentication, authorization, and secure file access.

Do not run destructive commands such as `migrate:fresh` against the existing Supabase database.

## 10. Verification and Reporting

After completing each phase:

1. Explain what was implemented.
2. List the files created or modified.
3. Explain any migrations or dependencies added.
4. Run relevant tests and build checks.
5. Report any features that are incomplete or unverified.
6. Identify what requires manual configuration or testing.
7. Wait for my confirmation before beginning the next major phase.

**Current instruction:** Finish and verify Phase 3 without implementing unrelated features or beginning Phase 4 automatically.

Focus on correctness, simplicity, maintainability, and consistency with GroveMap's five system objectives.