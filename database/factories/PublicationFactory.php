<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PublicationFactory extends Factory
{
    public function definition()
    {
        // Simulasi probabilitas: 60% masuk Scopus, 80% masuk SINTA
        $isScopus = $this->faker->boolean(60); 
        $isSinta = $this->faker->boolean(80);  

        // Fallback: Jika apes keduanya false, kita paksa masuk SINTA agar datanya valid
        if (!$isScopus && !$isSinta) {
            $isSinta = true;
        }

        return [
            'title' => $this->faker->sentence(rand(6, 12)),
            // C4: Kemutakhiran (Tahun 2018 - 2026)
            'year' => $this->faker->numberBetween(2018, 2026), 
            // C3: Jumlah Sitasi (0 - 500)
            'citation_count' => $this->faker->numberBetween(0, 500), 
            //generate DOI
            'doi' => '10.' . $this->faker->numberBetween(1000, 9999) . '/' . \Illuminate\Support\Str::random(8),
            // C1: Kualitas Global (Null jika $isScopus false)
            'scopus_quartile' => $isScopus ? $this->faker->randomElement(['Q1', 'Q2', 'Q3', 'Q4']) : null, 
            // C2: Kualitas Nasional (Null jika $isSinta false)
            'sinta_accreditation' => $isSinta ? $this->faker->randomElement(['S1', 'S2', 'S3', 'S4', 'S5', 'S6']) : null, 
        ];
    }
}
