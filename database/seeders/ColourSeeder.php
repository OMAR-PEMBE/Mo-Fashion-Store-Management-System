<?php

namespace Database\Seeders;

use App\Models\Colour;
use Illuminate\Database\Seeder;

class ColourSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Black', 'White', 'Blue', 'Navy', 'Red', 'Pink', 'Green', 'Yellow', 'Brown', 'Beige', 'Grey', 'Purple'] as $name) {
            Colour::firstOrCreate(['code' => strtoupper($name)], ['name' => $name, 'is_active' => true]);
        }
    }
}
