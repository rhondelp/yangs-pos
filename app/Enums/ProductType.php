<?php

namespace App\Enums;

enum ProductType: string
{
    case Drink = 'drink';
    case Food = 'food';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
