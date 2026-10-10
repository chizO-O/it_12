<?php

return [
    'navigation' => [
        'buyer' => [
            ['label' => 'Browse lots', 'description' => 'Explore properties in Toril, Kidapawan, and Kapalong.', 'route' => 'inventory.index'],
            ['label' => 'Messages', 'description' => 'Discuss a selected lot with your assigned Agent.', 'route' => null],
            ['label' => 'My reservations', 'description' => 'Follow your reservation and submitted buyer requirements.', 'route' => null],
            ['label' => 'Transactions', 'description' => 'Review your CTS documents and transaction progress.', 'route' => null],
            ['label' => 'Balances', 'description' => 'View your payment schedule and remaining balance.', 'route' => null],
        ],
        'agent' => [
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Inquiries', 'description' => 'Respond to buyer inquiries assigned to you.', 'route' => null],
            ['label' => 'Messages', 'description' => 'Keep conversations with your assigned buyers together.', 'route' => null],
            ['label' => 'Reservations', 'description' => 'Reserve available lots after confirming buyer interest.', 'route' => null],
            ['label' => 'My buyers', 'description' => 'Follow the progress of buyers assigned to you.', 'route' => null],
        ],
        'unit_manager' => [
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Reservation reviews', 'description' => 'Review buyer information and reservations.', 'route' => null],
            ['label' => 'Transactions', 'description' => 'Validate transactions and approve proposed terms.', 'route' => null],
            ['label' => 'CTS requests', 'description' => 'Initiate CTS processing for approved transactions.', 'route' => null],
        ],
        'field_planner' => [
            ['label' => 'Properties & lots', 'description' => 'Maintain authorized property details and lot inventory.', 'route' => 'properties.index'],
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Property verification', 'description' => 'Record verification of property and landowner information.', 'route' => null],
        ],
        'admin' => [
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Employees', 'description' => 'Create employee accounts and manage authorized roles.', 'route' => 'employees.index'],
            ['label' => 'Transactions', 'description' => 'Manage authorized transaction records.', 'route' => null],
            ['label' => 'CTS documents', 'description' => 'Upload externally prepared documents and manage their recorded statuses.', 'route' => null],
        ],
        'cashier' => [
            ['label' => 'Property inventory', 'description' => 'View properties and current lot availability.', 'route' => 'inventory.index'],
            ['label' => 'Payment records', 'description' => 'Record and verify actual payments and receipts.', 'route' => null],
            ['label' => 'Installment schedules', 'description' => 'Confirm paid installments and monitor amounts due.', 'route' => null],
        ],
    ],
];
