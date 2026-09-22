<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Size;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $order => $code) {
                Size::firstOrCreate(['code' => $code], ['name' => $code, 'sort_order' => $order + 1, 'is_active' => true]);
            }
            foreach (['Rent', 'Electricity', 'Internet', 'Marketing', 'Packaging', 'Transport', 'Staff', 'Other'] as $name) {
                ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
            }
            foreach (['business_name' => 'Mo Fashion Store', 'currency' => 'TZS', 'timezone' => 'Africa/Dar_es_Salaam', 'negative_stock_allowed' => 'false'] as $key => $value) {
                SystemSetting::firstOrCreate(['key' => $key], [
                    'value' => $value, 'type' => $key === 'negative_stock_allowed' ? 'boolean' : 'string',
                ]);
            }
        });
    }
}
