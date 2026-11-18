<?php

namespace App\Enums;

// Seeded roles. The owner may create more later; only Owner has special rules (locked, all permissions).
enum RoleName: string
{
    case Owner = 'Owner';
    case Cashier = 'Cashier';
    case Supervisor = 'Supervisor';
    case Bartender = 'Bartender';
    case Staff = 'Staff';
    case InventoryManager = 'Inventory Manager';
}
