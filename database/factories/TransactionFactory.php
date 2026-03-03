<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => $this->faker->uuid(),
            'user_id' => \App\Models\User::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 100000),
            'currency' => 'XOF',
            'status' => \App\PaymentStatus::PENDING,
            'gateway_type' => \App\GatewayType::CINETPAY,
            'gateway_payment_id' => $this->faker->optional()->uuid(),
            'return_url' => $this->faker->url(),
            'notify_url' => $this->faker->url(),
            'metadata' => [],
            'verified_at' => null,
        ];
    }

    /**
     * State for CinetPay gateway transactions
     * 
     * Creates a transaction configured for CinetPay gateway.
     * Sets appropriate currency (XOF) and gateway type.
     * 
     * @return static
     */
    public function cinetpay(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway_type' => \App\GatewayType::CINETPAY,
            'currency' => 'XOF',
        ]);
    }

    /**
     * State for Tranzak gateway transactions
     * 
     * Creates a transaction configured for Tranzak gateway.
     * Sets appropriate currency (XAF) and gateway type.
     * 
     * @return static
     */
    public function tranzak(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway_type' => \App\GatewayType::TRANZAK,
            'currency' => 'XAF',
        ]);
    }
}
