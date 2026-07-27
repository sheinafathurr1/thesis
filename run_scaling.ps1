<#
.SYNOPSIS
    Orkestrasi eksperimen skala: seed data pada beberapa titik kardinalitas,
    lalu ukur memori/latensi TOPSIS SQL-Driven vs Data-to-Compute di tiap titik.

.DESCRIPTION
    Setiap pengukuran (php artisan topsis:measure) berjalan sebagai proses PHP
    yang benar-benar baru, karena memory_get_peak_usage() bersifat monoton
    dalam satu proses -- kalau dua arsitektur diukur dalam proses yang sama,
    pengukuran kedua akan tercemar oleh pengukuran pertama.

.NOTES
    Jalankan dari root proyek Laravel (folder yang berisi artisan).
    Analisis & grafik (regresi memori~m untuk H1, kurva memori/latensi)
    dilakukan terpisah lewat skrip Python (mis. plot_scaling.py), BUKAN di
    skrip ini.
#>

$ErrorActionPreference = 'Stop'

$scales = @(100000, 500000, 1000000, 2000000)
$keywords = @('machine', 'smart', 'blockchain')
$architectures = @('sql-driven', 'data-to-compute')
$csvOut = 'topsis_scaling_results.csv'
$repeat = 10
$warmupCount = 3

$csvPath = Join-Path $PSScriptRoot "storage/app/$csvOut"

if (Test-Path $csvPath) {
    Remove-Item $csvPath -Force
    Write-Host "CSV lama dihapus: $csvPath"
}

foreach ($scale in $scales) {
    $numAuthors = [int]($scale / 10)
    $env:SEED_PUBLICATIONS = $scale
    $env:SEED_AUTHORS = $numAuthors

    Write-Host "=== Seeding skala $scale publikasi, $numAuthors author ==="
    php artisan db:seed --class=BigDataSeeder --force

    foreach ($keyword in $keywords) {
        foreach ($arch in $architectures) {
            for ($i = 1; $i -le $repeat; $i++) {
                if ($i -le $warmupCount) {
                    $label = 'warmup'
                } else {
                    $label = "scale=$scale"
                }

                Write-Host "  [$arch] keyword=$keyword iterasi=$i/$repeat label=$label"
                php artisan topsis:measure --arch=$arch --keyword=$keyword --out=$csvOut --label=$label
            }
        }
    }
}

Write-Host "Selesai. Hasil ada di: $csvPath"
