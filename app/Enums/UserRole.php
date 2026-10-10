<?php

namespace App\Enums;

enum UserRole: string
{
    case Buyer = 'buyer';
    case Agent = 'agent';
    case UnitManager = 'unit_manager';
    case FieldPlanner = 'field_planner';
    case Admin = 'admin';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Buyer',
            self::Agent => 'Agent',
            self::UnitManager => 'Unit Manager',
            self::FieldPlanner => 'Field Planner',
            self::Admin => 'Secretary / Admin',
            self::Cashier => 'Cashier',
        };
    }

    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Buyer => 'buyer.dashboard',
            self::Agent => 'agent.dashboard',
            default => 'office.dashboard',
        };
    }

    public function workspaceLabel(): string
    {
        return match ($this) {
            self::Buyer => 'Buyer',
            self::Agent => 'Agent',
            default => 'Office',
        };
    }

    /** @return list<self> */
    public static function employees(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role !== self::Buyer));
    }
}
