<?php

namespace App\Console\Commands;

use App\Http\Controllers\BaselineTopsisController;
use App\Http\Controllers\TopsisController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TopsisBenchmarkCommand extends Command
{
    protected $signature = 'topsis:benchmark
        {--keywords=Machine Learning,Blockchain,Smart City : Kata kunci uji, dipisah koma}
        {--repeat=30 : Jumlah pengulangan per pasangan arsitektur-keyword}
        {--warmup=3 : Jumlah pengulangan awal yang dibuang sebagai warm-up}
        {--output=topsis_benchmark_results.csv : Nama/path file CSV output (relatif ke storage/app)}';

    protected $description = 'Benchmark memori dan latensi TOPSIS SQL-Driven vs Data-to-Compute untuk BAB 4';

    public function handle(): int
    {
        $keywords = array_filter(array_map('trim', explode(',', $this->option('keywords'))));
        $repeat = (int) $this->option('repeat');
        $warmup = (int) $this->option('warmup');

        if ($warmup >= $repeat) {
            $this->error("--warmup ({$warmup}) harus lebih kecil dari --repeat ({$repeat}).");
            return self::FAILURE;
        }

        $architectures = [
            'sql-driven' => new TopsisController(),
            'data-to-compute' => new BaselineTopsisController(),
        ];

        $rows = [];

        foreach ($architectures as $archName => $controller) {
            foreach ($keywords as $keyword) {
                $this->info("Benchmarking [{$archName}] keyword=\"{$keyword}\"...");

                $memSamples = [];
                $latSamples = [];
                $mTerfilter = null;

                for ($i = 0; $i < $repeat; $i++) {
                    $request = Request::create('/', 'POST', ['keyword' => $keyword]);
                    $response = $controller->generateSlrRecommendation($request);
                    $data = json_decode($response->getContent(), true);

                    if (($data['status'] ?? null) !== 'success') {
                        $this->warn('  iterasi ' . ($i + 1) . ' gagal: ' . ($data['message'] ?? 'unknown error'));
                        continue;
                    }

                    if ($i >= $warmup) {
                        $memSamples[] = $data['peak_memory_bytes'];
                        $latSamples[] = $data['execution_time_ms'];
                        $mTerfilter = $data['total_found'];
                    }
                }

                if (empty($memSamples)) {
                    $this->warn("  Tidak ada sampel valid untuk [{$archName}] \"{$keyword}\", dilewati.");
                    continue;
                }

                $rows[] = [
                    'architecture' => $archName,
                    'keyword' => $keyword,
                    'm_terfilter' => $mTerfilter,
                    'mem_mb' => round($this->median($memSamples) / 1048576, 4),
                    'latency_ms' => round($this->median($latSamples), 4),
                ];
            }
        }

        $outputPath = storage_path('app/' . ltrim($this->option('output'), '/'));
        $this->writeCsv($outputPath, $rows);
        $rowCount = count($rows);
        $this->info("Selesai. {$rowCount} baris ditulis ke: {$outputPath}");

        return self::SUCCESS;
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        return $n % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2 : $values[$mid];
    }

    private function writeCsv(string $path, array $rows): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($path, 'w');
        fputcsv($fh, ['architecture', 'keyword', 'm_terfilter', 'mem_mb', 'latency_ms']);
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
    }
}
