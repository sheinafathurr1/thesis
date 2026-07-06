<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            // C5: Kinerja Penulis Global (Simulasi H-Index 0-25)
            'scopus_hindex' => $this->faker->numberBetween(0, 25), 
            // C6: Kinerja Penulis Nasional (Simulasi skor SINTA 0-4000)
            'sinta_score_author' => $this->faker->numberBetween(0, 4000), 
        ];
    }
}
