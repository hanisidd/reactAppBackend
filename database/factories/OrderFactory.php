<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $paymentMethod = $this->faker->randomElement(['advance', 'advance', 'cod']);
        $paymentStatus = $paymentMethod === 'advance'
            ? $this->faker->randomElement(['paid', 'paid', 'pending', 'failed'])
            : $this->faker->randomElement(['pending', 'paid']);

        return [
            // order_number / user_id / product_id / totals set by seeder
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'customer_phone' => '03' . $this->faker->numerify('#########'),
            'shipping_address' => $this->faker->boolean(60) ? $this->faker->address() : null,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'preparing', 'delivered', 'cancelled']),
        ];
    }
}
