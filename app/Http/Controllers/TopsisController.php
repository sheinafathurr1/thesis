<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TopsisController extends Controller
{
    /**
     * Generate SLR Recommendation using SQL-Driven TOPSIS Engine.
     * Sangat optimal untuk Big Data (100k+ baris) karena komputasi matriks dilakukan di level Database.
     */
    public function generateSlrRecommendation(Request $request)
    {
        $startTime = microtime(true);
        if (!$request->filled('keyword')) {
        return response()->json([
            'status' => 'error',
            'message' => 'Kata kunci (keyword) wajib diisi untuk melakukan analisis TOPSIS.'
        ], 422); // 422 Unprocessable Entity
    }

        // =====================================================================
        // LANGKAH 1: Tangkap Kriteria Aktif, Arah, dan Bobot dari User
        // =====================================================================
        $allCriteria = ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'];

        // Kriteria aktif: default = keenam kriteria (kompatibel-mundur)
        $requestedCriteria = $request->input('criteria', $allCriteria);
        $criteria = is_array($requestedCriteria)
            ? array_values(array_intersect($requestedCriteria, $allCriteria))
            : $allCriteria;
        if (empty($criteria)) {
            $criteria = $allCriteria;
        }

        // Arah tiap kriteria: default = "benefit"
        $requestedDirections = $request->input('directions', []);
        $directions = [];
        foreach ($criteria as $c) {
            $dir = is_array($requestedDirections) ? ($requestedDirections[$c] ?? 'benefit') : 'benefit';
            $dir = strtolower($dir);
            $directions[$c] = in_array($dir, ['benefit', 'cost']) ? $dir : 'benefit';
        }

        // Bobot: dukung "weights" (objek, baru) ATAU weight_c1..weight_c6 (lama)
        $requestedWeights = $request->input('weights');
        $weights = [];
        foreach ($criteria as $c) {
            if (is_array($requestedWeights) && array_key_exists($c, $requestedWeights)) {
                $weights[$c] = (float) $requestedWeights[$c];
            } else {
                $weights[$c] = (float) $request->input("weight_{$c}", 1);
            }
        }

        // Normalisasi HANYA atas kriteria yang aktif
        $totalWeight = array_sum($weights);
        $w = [];

        // Mencegah error jika user memasukkan bobot 0 semua
        if ($totalWeight == 0) {
            return response()->json(['status' => 'error', 'message' => 'Total bobot tidak boleh nol.'], 400);
        }

        foreach ($weights as $k => $v) {
            $w[$k] = $v / $totalWeight;
        }

        // =====================================================================
        // LANGKAH 2: Persiapkan Filter Multi-Keyword & Parameter Binding
        // =====================================================================
        $keyword = $request->input('keyword'); // Tetap gunakan $keyword agar bawahnya tidak error
        $whereClause = "";
        $bindings = [];
        
        if (!empty($keyword)) {
            // Bersihkan input: ubah koma menjadi spasi, lalu hilangkan spasi ganda
            $cleanInput = trim(preg_replace('/[\s,]+/', ' ', $keyword));
            
            // Pecah string menjadi array kata (tokenization)
            $keywordsArray = explode(' ', $cleanInput);
            
            $conditions = [];
            foreach ($keywordsArray as $word) {
                $conditions[] = "p.title LIKE ?";
                $bindings[] = "%{$word}%";
            }
            
            // Gabungkan semua kondisi dengan AND
            // Artinya: Semua kata kunci WAJIB ada di dalam judul (meskipun urutannya acak)
            $whereClause = "WHERE " . implode(' AND ', $conditions);
        }

        // =====================================================================
        // LANGKAH 3: Terjemahkan Kriteria Kualitatif ke Raw SQL (Mapping)
        // =====================================================================
        // Mengubah string akreditasi/quartile menjadi angka matematika (Benefit)
        $c1_sql = "CASE p.scopus_quartile WHEN 'Q1' THEN 4 WHEN 'Q2' THEN 3 WHEN 'Q3' THEN 2 WHEN 'Q4' THEN 1 ELSE 0 END";
        $c2_sql = "CASE p.sinta_accreditation WHEN 'S1' THEN 6 WHEN 'S2' THEN 5 WHEN 'S3' THEN 4 WHEN 'S4' THEN 3 WHEN 'S5' THEN 2 WHEN 'S6' THEN 1 ELSE 0 END";
        $c3_sql = "p.citation_count";
        
        // Skor Kemutakhiran: 10 - (Tahun_Sekarang - Tahun_Terbit). Minimal skor adalah 1.
        $c4_sql = "GREATEST(10 - (YEAR(CURDATE()) - p.year), 1)";
        
        // Kinerja penulis, gunakan IFNULL agar tidak error jika relasi kosong
        $c5_sql = "IFNULL(a.scopus_hindex, 0)";
        $c6_sql = "IFNULL(a.sinta_score_author, 0)";

        $criteriaSql = [
            'c1' => $c1_sql, 'c2' => $c2_sql, 'c3' => $c3_sql,
            'c4' => $c4_sql, 'c5' => $c5_sql, 'c6' => $c6_sql,
        ];

        // =====================================================================
        // LANGKAH 4: QUERY AGREGASI - Minta MySQL Menghitung Matriks Dasar
        // =====================================================================
        // Mencari Sum of Squares (untuk pembagi normalisasi) serta nilai Max & Min,
        // hanya untuk kriteria yang aktif
        $aggregateParts = [];
        foreach ($criteria as $c) {
            $sql = $criteriaSql[$c];
            $aggregateParts[] = "SUM(POW($sql, 2)) as sum_$c, MAX($sql) as max_$c, MIN($sql) as min_$c";
        }
        $aggregateQuery = "
            SELECT
                COUNT(p.id) as total_records,
                " . implode(",\n                ", $aggregateParts) . "
            FROM publications p
            JOIN authors a ON p.author_id = a.id
            $whereClause
        ";

        // Eksekusi hanya 1 query super cepat untuk mendapatkan 1 baris hasil
        $agg = DB::selectOne($aggregateQuery, $bindings);

        // Jika tidak ada data sama sekali atau keyword tidak ditemukan
        if (!$agg || $agg->{"sum_{$criteria[0]}"} === null) {
            return response()->json([
                'status' => 'error', 
                'message' => $keyword ? "Tidak ada publikasi yang cocok dengan kata kunci: '{$keyword}'." : "Database publikasi kosong."
            ], 404);
        }

        // =====================================================================
        // LANGKAH 5: Komputasi Variabel Statis di PHP
        // =====================================================================
        // 5a. Hitung Denominator (Akar dari Sum of Squares)
        // Gunakan operator ternary untuk mencegah pembagian dengan 0
        $denom = [];
        foreach ($criteria as $c) {
            $sumSq = $agg->{"sum_$c"};
            $denom[$c] = $sumSq > 0 ? sqrt($sumSq) : 1;
        }

        // 5b. Tentukan Solusi Ideal Positif (A+) dan Negatif (A-)
        // Rumus: (Nilai Asli / Pembagi) * Bobot
        // Untuk kriteria "cost", peran MAX/MIN ditukar (A+ pakai MIN, A- pakai MAX)
        $idealPos = [];
        $idealNeg = [];
        foreach ($criteria as $c) {
            $normMax = ($agg->{"max_$c"} / $denom[$c]) * $w[$c];
            $normMin = ($agg->{"min_$c"} / $denom[$c]) * $w[$c];
            if ($directions[$c] === 'cost') {
                $idealPos[$c] = $normMin;
                $idealNeg[$c] = $normMax;
            } else {
                $idealPos[$c] = $normMax;
                $idealNeg[$c] = $normMin;
            }
        }

        // =====================================================================
        // LANGKAH 6: Susun Rumus Jarak (Euclidean) di dalam SQL
        // =====================================================================
        // Menyuntikkan array statis dari PHP ke dalam sintaks perhitungan dinamis MySQL,
        // hanya untuk kriteria yang aktif
        $dPlusTerms = [];
        $dMinusTerms = [];
        foreach ($criteria as $c) {
            $sql = $criteriaSql[$c];
            $dPlusTerms[] = "POW((($sql / {$denom[$c]}) * {$w[$c]}) - {$idealPos[$c]}, 2)";
            $dMinusTerms[] = "POW((($sql / {$denom[$c]}) * {$w[$c]}) - {$idealNeg[$c]}, 2)";
        }
        $d_plus = "SQRT(" . implode(" + ", $dPlusTerms) . ")";
        $d_minus = "SQRT(" . implode(" + ", $dMinusTerms) . ")";

        // Nilai Preferensi (V)
        $v_score = "IF(($d_plus + $d_minus) > 0, $d_minus / ($d_plus + $d_minus), 0.5)";

        // =====================================================================
        // LANGKAH 7: QUERY EKSEKUSI AKHIR - Sorting dan Pengambilan Data
        // =====================================================================
        // Database akan menghitung skor TOPSIS untuk tiap baris, lalu hanya mengirim 20 terbaik ke PHP
        $finalQuery = "
            SELECT 
                p.id, p.title, p.doi, p.scopus_quartile, p.sinta_accreditation, p.citation_count, p.year,
                a.scopus_hindex, a.sinta_score_author,
                ($v_score) as topsis_score
            FROM publications p
            JOIN authors a ON p.author_id = a.id
            $whereClause
            ORDER BY topsis_score DESC
            LIMIT 20
        ";

        // Bindings array dieksekusi lagi karena $whereClause digunakan kembali
        $results = DB::select($finalQuery, $bindings);

        // =====================================================================
        // LANGKAH 8: Formatting Data Response untuk Frontend
        // =====================================================================
        $formattedResults = array_map(function($row) {
            return [
                'publication_id' => $row->id,
                'title' => $row->title,
                'doi' => $row->doi,
                'metrics' => [
                    'scopus_quartile' => $row->scopus_quartile,
                    'sinta_accreditation' => $row->sinta_accreditation,
                    'citations' => $row->citation_count,
                    'year' => $row->year,
                    'author_scopus_hindex' => $row->scopus_hindex,
                    'author_sinta_score' => $row->sinta_score_author,
                ],
                'topsis_score' => round($row->topsis_score, 4)
            ];
        }, $results);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        // Kembalikan sebagai JSON
        return response()->json([
            'status' => 'success',
            'execution_time_ms' => $executionTime,
            'active_criteria' => $criteria,
            'directions_used' => $directions,
            'user_weights' => $weights,
            'search_keyword' => $keyword,
            'total_found' => $agg->total_records, // Menambahkan total dokumen yang difilter
            'displayed_count' => count($formattedResults), // Menambahkan jumlah yang ditampilkan
            'recommendations' => $formattedResults
        ]);
    }
}