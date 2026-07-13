<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BigDataSeeder extends Seeder
{
    // Ubah konstanta ini untuk mengganti skala data uji, mis. {10000, 50000, 100000, 500000, 1000000}
    const NUM_AUTHORS = 5000;
    const NUM_PUBLICATIONS = 50000;
    const NUM_AFFILIATIONS = 100;

    // Parameter distribusi log-normal untuk citation_count (heavy-tailed, meniru sitasi asli)
    const CITATION_MU = 2.5;
    const CITATION_SIGMA = 1.6;
    const CITATION_CAP = 5000;

    /**
     * Run the database seeds.
     * Meng-generate Author, Affiliation, dan Publikasi untuk Stress Test.
     */
    public function run()
    {
        $this->command->info('Memulai Stress Test Seeder...');

        // Matikan pengecekan Foreign Key sementara agar proses truncate aman
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('publications')->truncate();
        DB::table('authors')->truncate();
        DB::table('affiliations')->truncate();

        // ==========================================
        // 1. GENERATE AFFILIATIONS
        // ==========================================
        $this->command->info('Menyiapkan ' . self::NUM_AFFILIATIONS . ' data Affiliations...');
        $affiliations = [];
        for ($i = 1; $i <= self::NUM_AFFILIATIONS; $i++) {
            $affiliations[] = [
                'id' => $i,
                'name' => 'Affiliation ' . $i,
                'kode_pt' => 'PT' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($affiliations, 1000) as $chunk) {
            DB::table('affiliations')->insert($chunk);
        }

        // ==========================================
        // 2. GENERATE AUTHORS
        // ==========================================
        $this->command->info('Menyiapkan ' . self::NUM_AUTHORS . ' data Authors...');
        $authors = [];
        for ($i = 1; $i <= self::NUM_AUTHORS; $i++) {
            $authors[] = [
                'id' => $i,
                'name' => 'Author ' . $i,
                'affiliation_id' => rand(1, self::NUM_AFFILIATIONS),
                'scopus_hindex' => rand(0, 40),
                'sinta_score_author' => rand(0, 4000),
            ];
        }

        // Insert per 1000 baris agar RAM tidak jebol
        foreach (array_chunk($authors, 1000) as $chunk) {
            DB::table('authors')->insert($chunk);
        }

        // ==========================================
        // 3. GENERATE PUBLICATIONS
        // ==========================================
        $this->command->info('Menyiapkan ' . self::NUM_PUBLICATIONS . ' data Publications (Mohon tunggu sebentar)...');
        $publications = [];

        $quartiles = ['Q1', 'Q2', 'Q3', 'Q4', null];
        $sintas = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', null];

        // Sengaja kita siapkan beberapa keyword spesifik agar pencarian nanti ada hasilnya
        $keywords = ['Smart City', 'Machine Learning', 'Blockchain', 'Internet of Things', 'Algorithm', 'Data Mining', 'Cyber Security', 'Network', 'Software Engineering', 'Artificial Intelligence'];

        for ($i = 1; $i <= self::NUM_PUBLICATIONS; $i++) {
            $randomKeyword = $keywords[array_rand($keywords)];

            $publications[] = [
                'id' => $i,
                'author_id' => rand(1, self::NUM_AUTHORS),
                'title' => 'Analysis of ' . $randomKeyword . ' using ' . Str::random(5) . ' framework',
                'doi' => '10.' . rand(1000, 9999) . '/' . Str::random(8),
                'scopus_quartile' => $quartiles[array_rand($quartiles)],
                'sinta_accreditation' => $sintas[array_rand($sintas)],
                'citation_count' => $this->generateLogNormalCitation(),
                'year' => rand(2018, 2026),
            ];
        }

        // Insert per 1000 baris
        foreach (array_chunk($publications, 1000) as $chunk) {
            DB::table('publications')->insert($chunk);
        }

        // Nyalakan kembali Foreign Key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('SUKSES! ' . self::NUM_PUBLICATIONS . ' Publikasi, ' . self::NUM_AUTHORS . ' Author, dan ' . self::NUM_AFFILIATIONS . ' Affiliation berhasil ditambahkan.');
    }

    /**
     * Sampel citation_count dari distribusi log-normal (heavy-tailed) via Box-Muller,
     * agar lebih realistis dibanding distribusi seragam.
     */
    private function generateLogNormalCitation(): int
    {
        // Box-Muller: dua uniform (0,1] -> satu sampel normal standar
        $u1 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        $u2 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        $citation = (int) round(exp(self::CITATION_MU + self::CITATION_SIGMA * $z));

        return max(0, min($citation, self::CITATION_CAP));
    }
}
