<?php

namespace App\Console\Commands;

use App\Http\Controllers\BaselineTopsisController;
use App\Http\Controllers\TopsisController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class BreakpointTest extends Command
{
    protected $signature = 'topsis:breakpoint
        {--arch=data-to-compute : Arsitektur yang diuji: sql-driven atau data-to-compute}
        {--keyword=machine : Kata kunci uji}
        {--scale=0 : Skala kardinalitas saat ini, dicatat apa adanya di CSV}
        {--out=topsis_breakpoint_results.csv : Nama file CSV output (relatif ke storage/app), di-APPEND}';

    protected $description = 'Uji breakpoint OOM: panggil satu arsitektur TOPSIS pada memory_limit tetap, catat OK bila selamat';

    public function handle(): int
    {
        $arch = $this->option('arch');
        $keyword = $this->option('keyword');
        $scale = $this->option('scale');
        $out = $this->option('out');

        $request = Request::create('/breakpoint', 'POST', ['keyword' => $keyword]);

        $start = microtime(true);

        // SENGAJA tanpa try/catch: Out-of-Memory adalah fatal error yang mematikan
        // proses PHP dan tidak bisa ditangkap. Kalau OOM terjadi di sini, proses ini
        // mati seketika -- tidak ada baris CSV yang ditulis dan tidak ada token
        // BREAKPOINT_OK yang dicetak. Orkestrator (run_breakpoint.ps1) yang
        // mendeteksi ketiadaan token tersebut dan mencatat baris OOM sendiri.
        if ($arch === 'data-to-compute') {
            $response = app(BaselineTopsisController::class)->generateSlrRecommendation($request);
        } else {
            $response = app(TopsisController::class)->generateSlrRecommendation($request);
        }

        // Titik ini hanya tercapai jika pemanggilan di atas SELAMAT (tidak OOM).
        $latencyMs = (microtime(true) - $start) * 1000;
        $peakMb = round(memory_get_peak_usage(true) / 1048576, 4);

        $data = json_decode($response->getContent(), true);
        $mTerfilter = $data['total_found'] ?? '';

        $outPath = storage_path('app/' . ltrim($out, '/'));
        $this->appendCsvRow($outPath, [
            'architecture' => $arch,
            'memory_limit' => ini_get('memory_limit'),
            'keyword' => $keyword,
            'scale' => $scale,
            'm_terfilter' => $mTerfilter,
            'status' => 'OK',
            'peak_mb' => $peakMb,
            'latency_ms' => round($latencyMs, 4),
        ]);

        $this->line('BREAKPOINT_OK');

        return self::SUCCESS;
    }

    private function appendCsvRow(string $path, array $row): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $isNew = !file_exists($path);

        $fh = fopen($path, 'a');
        if ($isNew) {
            fputcsv($fh, array_keys($row));
        }
        fputcsv($fh, array_values($row));
        fclose($fh);
    }
}
