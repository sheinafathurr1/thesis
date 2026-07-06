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
        // LANGKAH 1: Tangkap Bobot dari User dan Lakukan Normalisasi Bobot
        // =====================================================================
        $weights = [
            'c1' => $request->input('weight_c1', 1), // Kualitas Global (Scopus)
            'c2' => $request->input('weight_c2', 1), // Kualitas Nasional (SINTA)
            'c3' => $request->input('weight_c3', 1), // Jumlah Sitasi
            'c4' => $request->input('weight_c4', 1), // Kemutakhiran Tahun
            'c5' => $request->input('weight_c5', 1), // Kinerja Penulis Global
            'c6' => $request->input('weight_c6', 1), // Kinerja Penulis Nasional
        ];

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

        // =====================================================================
        // LANGKAH 4: QUERY AGREGASI - Minta MySQL Menghitung Matriks Dasar
        // =====================================================================
        // Mencari Sum of Squares (untuk pembagi normalisasi) serta nilai Max & Min
        $aggregateQuery = "
            SELECT
                COUNT(p.id) as total_records, 
                SUM(POW($c1_sql, 2)) as sum_c1, MAX($c1_sql) as max_c1, MIN($c1_sql) as min_c1,
                SUM(POW($c2_sql, 2)) as sum_c2, MAX($c2_sql) as max_c2, MIN($c2_sql) as min_c2,
                SUM(POW($c3_sql, 2)) as sum_c3, MAX($c3_sql) as max_c3, MIN($c3_sql) as min_c3,
                SUM(POW($c4_sql, 2)) as sum_c4, MAX($c4_sql) as max_c4, MIN($c4_sql) as min_c4,
                SUM(POW($c5_sql, 2)) as sum_c5, MAX($c5_sql) as max_c5, MIN($c5_sql) as min_c5,
                SUM(POW($c6_sql, 2)) as sum_c6, MAX($c6_sql) as max_c6, MIN($c6_sql) as min_c6
            FROM publications p
            JOIN authors a ON p.author_id = a.id
            $whereClause
        ";
        
        // Eksekusi hanya 1 query super cepat untuk mendapatkan 1 baris hasil
        $agg = DB::selectOne($aggregateQuery, $bindings);

        // Jika tidak ada data sama sekali atau keyword tidak ditemukan
        if (!$agg || $agg->sum_c1 === null) {
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
        $denom = [
            'c1' => $agg->sum_c1 > 0 ? sqrt($agg->sum_c1) : 1, 
            'c2' => $agg->sum_c2 > 0 ? sqrt($agg->sum_c2) : 1,
            'c3' => $agg->sum_c3 > 0 ? sqrt($agg->sum_c3) : 1, 
            'c4' => $agg->sum_c4 > 0 ? sqrt($agg->sum_c4) : 1,
            'c5' => $agg->sum_c5 > 0 ? sqrt($agg->sum_c5) : 1, 
            'c6' => $agg->sum_c6 > 0 ? sqrt($agg->sum_c6) : 1,
        ];

        // 5b. Tentukan Solusi Ideal Positif (A+) dan Negatif (A-)
        // Rumus: (Nilai Asli / Pembagi) * Bobot
        $idealPos = [
            'c1' => ($agg->max_c1 / $denom['c1']) * $w['c1'], 
            'c2' => ($agg->max_c2 / $denom['c2']) * $w['c2'],
            'c3' => ($agg->max_c3 / $denom['c3']) * $w['c3'], 
            'c4' => ($agg->max_c4 / $denom['c4']) * $w['c4'],
            'c5' => ($agg->max_c5 / $denom['c5']) * $w['c5'], 
            'c6' => ($agg->max_c6 / $denom['c6']) * $w['c6'],
        ];

        $idealNeg = [
            'c1' => ($agg->min_c1 / $denom['c1']) * $w['c1'], 
            'c2' => ($agg->min_c2 / $denom['c2']) * $w['c2'],
            'c3' => ($agg->min_c3 / $denom['c3']) * $w['c3'], 
            'c4' => ($agg->min_c4 / $denom['c4']) * $w['c4'],
            'c5' => ($agg->min_c5 / $denom['c5']) * $w['c5'], 
            'c6' => ($agg->min_c6 / $denom['c6']) * $w['c6'],
        ];

        // =====================================================================
        // LANGKAH 6: Susun Rumus Jarak (Euclidean) di dalam SQL
        // =====================================================================
        // Menyuntikkan array statis dari PHP ke dalam sintaks perhitungan dinamis MySQL
        $d_plus = "SQRT(
            POW((($c1_sql / {$denom['c1']}) * {$w['c1']}) - {$idealPos['c1']}, 2) +
            POW((($c2_sql / {$denom['c2']}) * {$w['c2']}) - {$idealPos['c2']}, 2) +
            POW((($c3_sql / {$denom['c3']}) * {$w['c3']}) - {$idealPos['c3']}, 2) +
            POW((($c4_sql / {$denom['c4']}) * {$w['c4']}) - {$idealPos['c4']}, 2) +
            POW((($c5_sql / {$denom['c5']}) * {$w['c5']}) - {$idealPos['c5']}, 2) +
            POW((($c6_sql / {$denom['c6']}) * {$w['c6']}) - {$idealPos['c6']}, 2)
        )";

        $d_minus = "SQRT(
            POW((($c1_sql / {$denom['c1']}) * {$w['c1']}) - {$idealNeg['c1']}, 2) +
            POW((($c2_sql / {$denom['c2']}) * {$w['c2']}) - {$idealNeg['c2']}, 2) +
            POW((($c3_sql / {$denom['c3']}) * {$w['c3']}) - {$idealNeg['c3']}, 2) +
            POW((($c4_sql / {$denom['c4']}) * {$w['c4']}) - {$idealNeg['c4']}, 2) +
            POW((($c5_sql / {$denom['c5']}) * {$w['c5']}) - {$idealNeg['c5']}, 2) +
            POW((($c6_sql / {$denom['c6']}) * {$w['c6']}) - {$idealNeg['c6']}, 2)
        )";

        // Nilai Preferensi (V)
        $v_score = "IF(($d_plus + $d_minus) > 0, $d_minus / ($d_plus + $d_minus), 0)";

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
            'user_weights' => $weights,
            'search_keyword' => $keyword,
            'total_found' => $agg->total_records, // Menambahkan total dokumen yang difilter
            'displayed_count' => count($formattedResults), // Menambahkan jumlah yang ditampilkan
            'recommendations' => $formattedResults
        ]);
    }
}