<#
.SYNOPSIS
    Uji breakpoint OOM (H2): pada memory_limit TETAP, cari skala kardinalitas di
    mana masing-masing arsitektur (SQL-Driven vs Data-to-Compute) kehabisan memori.

.DESCRIPTION
    Setiap pengujian dijalankan sebagai proses PHP baru (php -d memory_limit=...)
    karena Out-of-Memory adalah fatal error yang mematikan proses dan TIDAK BISA
    ditangkap try/catch. Deteksi OOM dilakukan di sini (orkestrator), bukan di
    dalam kode PHP: kalau output proses anak tidak mengandung token
    "BREAKPOINT_OK", berarti proses itu mati (OOM) sebelum sempat menulis apa pun.

.NOTES
    Jalankan dari root proyek Laravel (folder yang berisi artisan):
        powershell -ExecutionPolicy Bypass -File .\run_breakpoint.ps1
#>

$ErrorActionPreference = 'Stop'

$memLimit = "256M"
$scales = @(100000, 500000, 1000000, 2000000, 5000000)
$keyword = "machine"
$out = "topsis_breakpoint_results.csv"
$architectures = @('sql-driven', 'data-to-compute')
$csvHeader = "architecture,memory_limit,keyword,scale,m_terfilter,status,peak_mb,latency_ms"

$csvPath = Join-Path $PSScriptRoot "storage/app/$out"

if (Test-Path $csvPath) {
    Remove-Item $csvPath -Force
    Write-Host "CSV lama dihapus: $csvPath" -ForegroundColor Yellow
}

foreach ($scale in $scales) {
    $numAuthors = [Math]::Max(1000, [int]($scale / 10))
    $env:SEED_PUBLICATIONS = $scale
    $env:SEED_AUTHORS = $numAuthors

    Write-Host "=== Seeding skala $scale publikasi, $numAuthors author ===" -ForegroundColor Cyan
    php artisan db:seed --class=BigDataSeeder --force

    foreach ($arch in $architectures) {
        Write-Host "  [$arch] scale=$scale memory_limit=$memLimit ..." -ForegroundColor White -NoNewline

        $output = & php -d memory_limit=$memLimit artisan topsis:breakpoint --arch=$arch --keyword=$keyword --scale=$scale --out=$out 2>&1 | Out-String

        if ($output -match 'BREAKPOINT_OK') {
            Write-Host " OK" -ForegroundColor Green
        } else {
            Write-Host " OOM" -ForegroundColor Red

            if (-not (Test-Path $csvPath)) {
                Add-Content -Path $csvPath -Value $csvHeader
            }

            $mApprox = [Math]::Floor($scale / 10)
            $oomLine = "$arch,$memLimit,$keyword,$scale,$mApprox,OOM,,"
            Add-Content -Path $csvPath -Value $oomLine
        }
    }
}

Write-Host "Selesai. Hasil ada di: $csvPath" -ForegroundColor Cyan
