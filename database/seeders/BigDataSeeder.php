<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BigDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Meng-generate 5.000 Author dan 50.000 Publikasi untuk Stress Test.
     */
    public function run()
    {
        $this->command->info('Memulai Stress Test Seeder...');

        // Matikan pengecekan Foreign Key sementara agar proses truncate aman
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('publications')->truncate();
        DB::table('authors')->truncate();

        // ==========================================
        // 1. GENERATE 5.000 AUTHORS
        // ==========================================
        $this->command->info('Menyiapkan 5.000 data Authors...');
        $authors = [];
        for ($i = 1; $i <= 5000; $i++) {
            $authors[] = [
                'id' => $i,
                'name' => 'Author ' . $i,
                'affiliation_id' => rand(1, 100), // <-- TAMBAHKAN BARIS INI
                'scopus_hindex' => rand(0, 40),
                'sinta_score_author' => rand(0, 4000),
            ];
        }
        
        // Insert per 1000 baris agar RAM tidak jebol
        foreach (array_chunk($authors, 1000) as $chunk) {
            DB::table('authors')->insert($chunk);
        }

        // ==========================================
        // 2. GENERATE 50.000 PUBLICATIONS
        // ==========================================
        $this->command->info('Menyiapkan 50.000 data Publications (Mohon tunggu sebentar)...');
        $publications = [];
        
        $quartiles = ['Q1', 'Q2', 'Q3', 'Q4', null];
        $sintas = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', null];
        
        // Sengaja kita siapkan beberapa keyword spesifik agar pencarian nanti ada hasilnya
        $keywords = ['Smart City', 'Machine Learning', 'Blockchain', 'Internet of Things', 'Algorithm', 'Data Mining', 'Cyber Security', 'Network', 'Software Engineering', 'Artificial Intelligence'];

        for ($i = 1; $i <= 50000; $i++) {
            $randomKeyword = $keywords[array_rand($keywords)];
            
            $publications[] = [
                'id' => $i,
                'author_id' => rand(1, 5000),
                'title' => 'Analysis of ' . $randomKeyword . ' using ' . Str::random(5) . ' framework',
                'doi' => '10.' . rand(1000, 9999) . '/' . Str::random(8),
                'scopus_quartile' => $quartiles[array_rand($quartiles)],
                'sinta_accreditation' => $sintas[array_rand($sintas)],
                'citation_count' => rand(0, 800),
                'year' => rand(2018, 2026),
            ];
        }

        // Insert per 1000 baris
        foreach (array_chunk($publications, 1000) as $chunk) {
            DB::table('publications')->insert($chunk);
        }

        // Nyalakan kembali Foreign Key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('SUKSES! 50.000 Publikasi dan 5.000 Author berhasil ditambahkan.');
    }
}