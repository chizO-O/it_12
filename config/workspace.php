<?php

// Role-specific navigation. Dashboard, Notifications and My Account are shared.
// Supporting actions (Agent assessments, CTS verification and sale confirmation)
// stay inside the corresponding workflow rather than being separate dashboard tabs.
return [
    'navigation' => [
        'buyer' => [
            ['label' => 'Property listings', 'description' => 'Browse available lots and contact the Agent assigned to a lot.', 'route' => 'inventory.index'],
            ['label' => 'Conversations', 'description' => 'Submit property inquiries and chat with the assigned Agent.', 'route' => 'inquiries.index'],
            ['label' => 'My reservations', 'description' => 'Submit contact details and track your own reservation.', 'route' => 'reservations.index'],
            ['label' => 'CTS (Contract to Sell)', 'description' => 'View and review Contract to Sell documents for your lots.', 'route' => 'cts.index'],
            ['label' => 'My payments', 'description' => 'See verified receipts, upcoming due dates, and outstanding balances.', 'route' => 'sales.index'],
        ],
        'agent' => [
            ['label' => 'Property listings', 'description' => 'View lots assigned for marketing and respond to property interest.', 'route' => 'inventory.index'],
            ['label' => 'Conversations', 'description' => 'Respond to buyer inquiries and record reservation interest.', 'route' => 'inquiries.index'],
            ['label' => 'Reservations', 'description' => 'Create reservations and record buyer assessments.', 'route' => 'reservations.index'],
        ],
        'admin' => [
            ['label' => 'Property listings', 'description' => 'Read-only property and lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Employee management', 'description' => 'Create and assign authorized office employee roles.', 'route' => 'employees.index'],
            ['label' => 'CTS (Contract to Sell)', 'description' => 'Prepare CTS documents for approved reservations.', 'route' => 'cts.index'],
            ['label' => 'Sales reports', 'description' => 'Review confirmed sales and verified collections.', 'route' => 'reports.index'],
        ],
        'unit_manager' => [
            ['label' => 'Property listings', 'description' => 'View available properties and lots.', 'route' => 'inventory.index'],
            ['label' => 'Manage Agents', 'description' => 'Invite Agents with a fixed Agent role and supervise your team.', 'route' => 'agents.index'],
            ['label' => 'Assign lots to Agents', 'description' => 'Assign each available lot to one Agent.', 'route' => 'assignments.index'],
            ['label' => 'Reservation reviews', 'description' => 'Approve or reject your Agents’ reservations.', 'route' => 'reservations.index'],
            ['label' => 'Sale confirmations', 'description' => 'Confirm final terms after CTS completion.', 'route' => 'sales.index'],
            ['label' => 'Sales reports', 'description' => 'Review sales for your supervised team.', 'route' => 'reports.index'],
        ],
        'field_planner' => [
            ['label' => 'Property management', 'description' => 'Create and maintain property and lot records.', 'route' => 'properties.index'],
            ['label' => 'Landowner verification', 'description' => 'Verify seller identity and ownership information for CTS.', 'route' => 'cts.index'],
        ],
        'cashier' => [
            ['label' => 'Transactions & payments', 'description' => 'View balances and record verified payments received.', 'route' => 'sales.index'],
        ],
    ],
];
