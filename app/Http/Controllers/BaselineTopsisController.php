<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BaselineTopsisController extends Controller
{
    /**
     * Generate SLR Recommendation using classic Data-to-Compute TOPSIS.
     * Pembanding TopsisController: seluruh baris hasil filter ditarik ke memori
     * PHP, lalu matriks TOPSIS dihitung di aplikasi (bukan di MySQL).
     */
    public function generateSlrRecommendation(Request $request)
    {
        $startTime = microtime(true);
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $memStart = memory_get_usage(true);

        if (!$request->filled('keyword')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kata kunci (keyword) wajib diisi untuk melakukan analisis TOPSIS.'
            ], 422);
        }

        // =====================================================================
        // LANGKAH 1: Tangkap Kriteria Aktif, Arah, dan Bobot dari User
        // (identik dengan TopsisController agar hasil setara-numerik)
        // =====================================================================
        $allCriteria = ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'];

        $requestedCriteria = $request->input('criteria', $allCriteria);
        $criteria = is_array($requestedCriteria)
            ? array_values(array_intersect($requestedCriteria, $allCriteria))
            : $allCriteria;
        if (empty($criteria)) {
            $criteria = $allCriteria;
        }

        $requestedDirections = $request->input('directions', []);
        $directions = [];
        foreach ($criteria as $c) {
            $dir = is_array($requestedDirections) ? ($requestedDirections[$c] ?? 'benefit') : 'benefit';
            $dir = strtolower($dir);
            $directions[$c] = in_array($dir, ['benefit', 'cost']) ? $dir : 'benefit';
        }

        $requestedWeights = $request->input('weights');
        $weights = [];
        foreach ($criteria as $c) {
            if (is_array($requestedWeights) && array_key_exists($c, $requestedWeights)) {
                $weights[$c] = (float) $requestedWeights[$c];
            } else {
                $weights[$c] = (float) $request->input("weight_{$c}", 1);
            }
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight == 0) {
            return response()->json(['status' => 'error', 'message' => 'Total bobot tidak boleh nol.'], 400);
        }
        $w = [];
        foreach ($weights as $k => $v) {
            $w[$k] = $v / $totalWeight;
        }

        // =====================================================================
        // LANGKAH 2: Filter Multi-Keyword (identik dengan TopsisController)
        // =====================================================================
        $keyword = $request->input('keyword');
        $whereClause = "";
        $bindings = [];

        if (!empty($keyword)) {
            $cleanInput = trim(preg_replace('/[\s,]+/', ' ', $keyword));
            $keywordsArray = explode(' ', $cleanInput);

            $conditions = [];
            foreach ($keywordsArray as $word) {
                $conditions[] = "p.title LIKE ?";
                $bindings[] = "%{$word}%";
            }

            $whereClause = "WHERE " . implode(' AND ', $conditions);
        }

        // =====================================================================
        // LANGKAH 3: Tarik SELURUH Baris Mentah ke Memori PHP (Data-to-Compute)
        // =====================================================================
        $rawQuery = "
            SELECT
                p.id, p.title, p.doi, p.scopus_quartile, p.sinta_accreditation, p.citation_count, p.year,
                a.scopus_hindex, a.sinta_score_author
            FROM publications p
            JOIN authors a ON p.author_id = a.id
            $whereClause
        ";
        $rows = DB::select($rawQuery, $bindings);

        if (empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => $keyword ? "Tidak ada publikasi yang cocok dengan kata kunci: '{$keyword}'." : "Database publikasi kosong."
            ], 404);
        }

        // =====================================================================
        // LANGKAH 4: Petakan Kriteria Kualitatif ke Nilai Numerik (di PHP)
        // Pemetaan IDENTIK dengan versi SQL di TopsisController.
        // =====================================================================
        $quartileMap = ['Q1' => 4, 'Q2' => 3, 'Q3' => 2, 'Q4' => 1];
        $accreditationMap = ['S1' => 6, 'S2' => 5, 'S3' => 4, 'S4' => 3, 'S5' => 2, 'S6' => 1];
        $currentYear = (int) date('Y');

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[] = [
                'c1' => $quartileMap[$row->scopus_quartile] ?? 0,
                'c2' => $accreditationMap[$row->sinta_accreditation] ?? 0,
                'c3' => (float) $row->citation_count,
                'c4' => max(10 - ($currentYear - (int) $row->year), 1),
                'c5' => (float) ($row->scopus_hindex ?? 0),
                'c6' => (float) ($row->sinta_score_author ?? 0),
            ];
        }

        // =====================================================================
        // LANGKAH 5: Hitung Matriks Ternormalisasi-Berbobot di PHP
        // =====================================================================
        $denom = [];
        foreach ($criteria as $c) {
            $sumSq = 0.0;
            foreach ($matrix as $m) {
                $sumSq += $m[$c] ** 2;
            }
            $denom[$c] = $sumSq > 0 ? sqrt($sumSq) : 1;
        }

        $normalized = [];
        foreach ($matrix as $i => $m) {
            foreach ($criteria as $c) {
                $normalized[$i][$c] = ($m[$c] / $denom[$c]) * $w[$c];
            }
        }

        // =====================================================================
        // LANGKAH 6: Solusi Ideal Positif (A+) dan Negatif (A-)
        // Untuk kriteria "cost", peran MAX/MIN ditukar.
        // =====================================================================
        $idealPos = [];
        $idealNeg = [];
        foreach ($criteria as $c) {
            $values = array_column($normalized, $c);
            $max = max($values);
            $min = min($values);
            if ($directions[$c] === 'cost') {
                $idealPos[$c] = $min;
                $idealNeg[$c] = $max;
            } else {
                $idealPos[$c] = $max;
                $idealNeg[$c] = $min;
            }
        }

        // =====================================================================
        // LANGKAH 7: Jarak Euclidean dan Skor Preferensi (V)
        // =====================================================================
        $scored = [];
        foreach ($normalized as $i => $row) {
            $dPlusSq = 0.0;
            $dMinusSq = 0.0;
            foreach ($criteria as $c) {
                $dPlusSq += ($row[$c] - $idealPos[$c]) ** 2;
                $dMinusSq += ($row[$c] - $idealNeg[$c]) ** 2;
            }
            $dPlus = sqrt($dPlusSq);
            $dMinus = sqrt($dMinusSq);
            $score = ($dPlus + $dMinus) > 0 ? $dMinus / ($dPlus + $dMinus) : 0.5;

            $scored[] = [
                'row' => $rows[$i],
                'score' => $score,
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top20 = array_slice($scored, 0, 20);

        // =====================================================================
        // LANGKAH 8: Formatting Data Response untuk Frontend
        // =====================================================================
        $formattedResults = array_map(function ($item) {
            $row = $item['row'];
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
                'topsis_score' => round($item['score'], 4)
            ];
        }, $top20);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $peakMemory = memory_get_peak_usage(true);

        return response()->json([
            'status' => 'success',
            'architecture' => 'data-to-compute',
            'execution_time_ms' => $executionTime,
            'peak_memory_bytes' => $peakMemory,
            'peak_memory_mb' => round($peakMemory / 1048576, 2),
            'mem_delta_bytes' => $peakMemory - $memStart,
            'active_criteria' => $criteria,
            'directions_used' => $directions,
            'user_weights' => $weights,
            'search_keyword' => $keyword,
            'total_found' => count($rows),
            'displayed_count' => count($formattedResults),
            'recommendations' => $formattedResults
        ]);
    }
}
