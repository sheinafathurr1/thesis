<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Affiliation;
use App\Models\Author;
use App\Models\Publication;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. Buat satu kampus utama sebagai jangkar data
        $mainCampus = Affiliation::create([
            'name' => 'Telkom University',
            'kode_pt' => '041057'
        ]);

        // 2. Buat 4 kampus dummy lainnya
        $otherCampuses = Affiliation::factory(4)->create();
        $campuses = $otherCampuses->push($mainCampus);

        // 3. Looping untuk membuat relasi Author dan Publikasinya
        foreach ($campuses as $campus) {
            // Buat 10 author untuk setiap kampus
            Author::factory(10)->create([
                'affiliation_id' => $campus->id
            ])->each(function ($author) {
                // Setiap author menghasilkan 5-15 paper
                Publication::factory(rand(5, 15))->create([
                    'author_id' => $author->id
                ]);
            });
        }
    }
}
