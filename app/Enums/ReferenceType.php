<?php

namespace App\Enums;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Size;

enum ReferenceType: string
{
    case Categories = 'categories';
    case Sizes = 'sizes';
    case Colours = 'colours';

    public function model(): string
    {
        return match ($this) {
            self::Categories => Category::class,
            self::Sizes => Size::class,
            self::Colours => Colour::class,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function singular(): string
    {
        return match ($this) {
            self::Categories => 'category',
            self::Sizes => 'size',
            self::Colours => 'colour',
        };
    }

    public function identifier(): string
    {
        return $this === self::Categories ? 'slug' : 'code';
    }
}
