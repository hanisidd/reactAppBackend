<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $type = $this->faker->randomElement(['digital', 'digital', 'physical']); // weight toward digital
        $isDigital = $type === 'digital';

        return [
            // category_id / timestamps are set by the seeder per-row for bulk inserts
            'type' => $type,
            'title' => ucfirst($this->faker->words(3, true)),
            'description' => '<p>' . $this->faker->paragraph(4) . '</p>',
            'price' => $this->faker->randomFloat(2, 200, 50000),
            'quantity' => $this->faker->numberBetween(0, 500),
            'status' => $this->faker->randomElement(['active', 'active', 'active', 'inactive']),
            'file_path' => null,
            'file_original_name' => $isDigital ? $this->faker->word() . '.pdf' : null,
            'file_size' => $isDigital ? (string) $this->faker->numberBetween(100000, 40000000) : null,
        ];
    }
}
