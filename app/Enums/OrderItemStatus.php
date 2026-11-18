<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
