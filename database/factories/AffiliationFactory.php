<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AffiliationFactory extends Factory
{
    public function definition()
    {
        return [
            // Menggunakan company() digabung dengan teks 'University'
            'name' => $this->faker->unique()->company() . ' University',
            'kode_pt' => $this->faker->numerify('04####'),
        ];
    }
}
