<?php

namespace App\Console\Commands;

use App\Http\Controllers\BaselineTopsisController;
use App\Http\Controllers\TopsisController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class MeasureOnce extends Command
{
    protected $signature = 'topsis:measure
        {--arch=sql-driven : Arsitektur yang diukur: sql-driven atau data-to-compute}
        {--keyword=machine : Kata kunci uji}
        {--out=topsis_scaling_results.csv : Nama file CSV output (relatif ke storage/app), di-APPEND}
        {--label= : Label bebas untuk baris ini, mis. "warmup" atau "scale=1000000"}';

    protected $description = 'Ukur satu titik memori/latensi TOPSIS dalam SATU proses PHP segar (untuk uji O(1) vs O(n))';

    public function handle(): int
    {
        $arch = $this->option('arch');
        $keyword = $this->option('keyword');
        $label = (string) $this->option('label');

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $request = Request::create('/measure', 'POST', ['keyword' => $keyword]);

        $start = microtime(true);
        if ($arch === 'data-to-compute') {
            $response = app(BaselineTopsisController::class)->generateSlrRecommendation($request);
        } else {
            $response = app(TopsisController::class)->generateSlrRecommendation($request);
        }
        $latencyMs = (microtime(true) - $start) * 1000;

        $peakTrue = memory_get_peak_usage(true);
        $peakArena = memory_get_peak_usage(false);

        $data = json_decode($response->getContent(), true);

        if (($data['status'] ?? null) !== 'success') {
            $this->error('Pengukuran gagal: ' . ($data['message'] ?? 'unknown error'));
            return self::FAILURE;
        }

        $mTerfilter = $data['total_found'];
        $memMbTrue = round($peakTrue / 1048576, 4);
        $memMbArena = round($peakArena / 1048576, 4);
        $latencyMsRounded = round($latencyMs, 4);

        $outPath = storage_path('app/' . ltrim($this->option('out'), '/'));
        $this->appendCsvRow($outPath, [
            'architecture' => $arch,
            'keyword' => $keyword,
            'm_terfilter' => $mTerfilter,
            'mem_mb_true' => $memMbTrue,
            'mem_mb_arena' => $memMbArena,
            'latency_ms' => $latencyMsRounded,
            'label' => $label,
        ]);

        $this->info(sprintf(
            '[%s] keyword="%s" m=%d mem_true=%.2fMB mem_arena=%.2fMB latency=%.2fms label=%s',
            $arch,
            $keyword,
            $mTerfilter,
            $memMbTrue,
            $memMbArena,
            $latencyMsRounded,
            $label
        ));

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
