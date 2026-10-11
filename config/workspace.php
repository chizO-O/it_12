<?php

return [
    'navigation' => [
        'buyer' => [
            ['label' => 'Browse lots', 'description' => 'Explore properties in Toril, Kidapawan, and Kapalong.', 'route' => 'inventory.index'],
            ['label' => 'Conversations', 'description' => 'Discuss selected lots with your assigned Agent.', 'route' => 'inquiries.index'],
            ['label' => 'My reservations', 'description' => 'Track reserved lots and submit buyer contact information.', 'route' => 'reservations.index'],
            ['label' => 'CTS tracking', 'description' => 'Review your CTS documents and processing progress.', 'route' => 'cts.index'],
            ['label' => 'Balances', 'description' => 'View your payment schedule and remaining balance.', 'route' => null],
        ],
        'agent' => [
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Conversations', 'description' => 'Handle buyer inquiries and associated messages.', 'route' => 'inquiries.index'],
            ['label' => 'Reservations', 'description' => 'Confirm interest and track lot reservations.', 'route' => 'reservations.index'],
            ['label' => 'CTS tracking', 'description' => 'Monitor the CTS status for your assigned buyers.', 'route' => 'cts.index'],
        ],
        'unit_manager' => [
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Manage Agents', 'description' => 'Invite and supervise Agents.', 'route' => 'agents.index'],
            ['label' => 'Lot assignments', 'description' => 'Assign Agents before inquiries.', 'route' => 'assignments.index'],
            ['label' => 'Pending inquiries', 'description' => 'Check inquiries awaiting lot assignment.', 'route' => 'inquiries.index'],
            ['label' => 'Reservation reviews', 'description' => 'Review buyer information and reserved lots.', 'route' => 'reservations.index'],
            ['label' => 'CTS tracking', 'description' => 'Initiate CTS processing from approved reservations.', 'route' => 'cts.index'],
        ],
        'field_planner' => [
            ['label' => 'Properties & lots', 'description' => 'Maintain authorized property details and lot inventory.', 'route' => 'properties.index'],
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'CTS property verification', 'description' => 'Record property/owner verification for requested CTS.', 'route' => 'cts.index'],
        ],
        'admin' => [
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Employees', 'description' => 'Invite and manage office employee accounts.', 'route' => 'employees.index'],
            ['label' => 'Reservation overview', 'description' => 'Monitor reservation status without changing decisions.', 'route' => 'reservations.index'],
            ['label' => 'CTS documents', 'description' => 'Upload externally prepared CTS documents and track review.', 'route' => 'cts.index'],
        ],
        'cashier' => [
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Payment records', 'description' => 'Record and verify actual payments and receipts.', 'route' => null],
            ['label' => 'Installment schedules', 'description' => 'Confirm paid installments and monitor amounts due.', 'route' => null],
        ],
    ],
];
