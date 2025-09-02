<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PaymentCard;
use App\Models\User;

class PaymentCardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Solo crear datos de prueba si hay usuarios
        $users = User::limit(5)->get();

        if ($users->isEmpty()) {
            $this->command->info('No users found. Please run UserSeeder first.');
            return;
        }

        foreach ($users as $user) {
            // Crear 2-3 tarjetas por usuario
            $cardCount = rand(2, 3);

            for ($i = 0; $i < $cardCount; $i++) {
                PaymentCard::create([
                    'user_id' => $user->id,
                    'card_fingerprint' => PaymentCard::generateFingerprint(
                        '4111111111111' . rand(100, 999),
                        rand(1, 12),
                        rand(2025, 2030)
                    ),
                    'last_four_digits' => str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'brand' => ['VISA', 'MASTERCARD', 'AMEX'][rand(0, 2)],
                    'card_type' => ['credit', 'debit'][rand(0, 1)],
                    'expiry_month_encrypted' => rand(1, 12),
                    'expiry_year_encrypted' => rand(2025, 2030),
                    'cardholder_name_encrypted' => $user->full_name,
                    'mitec_card_id' => 'mitec_' . uniqid(),
                    'mitec_merchant_used' => 'test_merchant_' . rand(1, 5),
                    'is_default' => $i === 0, // Primera tarjeta como default
                    'is_active' => true,
                    'created_ip' => '127.0.0.1',
                ]);
            }
        }

        $this->command->info('Payment cards seeded successfully!');
    }
}
