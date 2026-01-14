<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

final class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $promotions = [
            [
                'name' => 'Launch Special',
                'description' => 'Special discount for early adopters during launch period.',
                'code' => 'NEWLAUNCH2026',
                'value_type' => 'percentage',
                'value_amount' => 10000,
                'valid_from' => now(),
                'valid_to' => now()->addMinutes(15),
                'max_uses' => 1,
                'is_active' => true,
            ],
            // [
            //     'name' => 'Friend Referral',
            //     'description' => 'Discount for users referred by existing customers.',
            //     'code' => 'FRIEND10',
            //     'value_type' => 'percentage',
            //     'value_amount' => 10,
            //     'valid_from' => now(),
            //     'valid_to' => null,
            //     'max_uses' => null,
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'First Month Free',
            //     'description' => 'Get your first month completely free.',
            //     'code' => 'FIRSTFREE',
            //     'value_type' => 'flat',
            //     'value_amount' => 4900, // $49 in cents
            //     'valid_from' => now(),
            //     'valid_to' => now()->addMonths(6),
            //     'max_uses' => 50,
            //     'is_active' => true,
            // ],
        ];

        foreach ($promotions as $promotion) {
            Promotion::updateOrCreate(
                ['code' => $promotion['code']],
                $promotion
            );
        }
    }
}
