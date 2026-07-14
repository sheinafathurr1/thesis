<#
.SYNOPSIS
    Uji breakpoint OOM (H2): pada memory_limit TETAP, cari skala kardinalitas di
    mana masing-masing arsitektur (SQL-Driven vs Data-to-Compute) kehabisan memori.

.DESCRIPTION
    Seeding dan pengukuran punya kebutuhan memori yang BERLAWANAN, dan proses ini
    memisahkannya secara sengaja:
    - SEEDING jutaan baris butuh memori TINGGI/TAK TERBATAS. Kalau proses seed
      sendiri OOM, tabel akan diam-diam berhenti terisi separuh jalan (atau
      truncate berhasil tapi insert gagal), sehingga pengukuran berikutnya
      berjalan di atas tabel kosong/parsial dan menghasilkan angka palsu.
      Maka seeding dijalankan sebagai proses TERPISAH dengan
      `php -d memory_limit=-1 artisan db:seed ...`.
    - PENGUKURAN titik-patah justru butuh memori DIBATASI (256M) supaya baseline
      Data-to-Compute betul-betul bisa OOM pada skala tertentu. Ini proses PHP
      baru yang lain lagi, jadi limitnya tidak bentrok dengan proses seed.

    Setelah tiap seed, jumlah baris `publications` diverifikasi lewat
    `php artisan tinker --execute`. Kalau hasilnya < 1, skala itu DILEWATI
    (bukan diukur di atas tabel kosong).

    Deteksi OOM saat pengukuran tetap lewat ketiadaan token "BREAKPOINT_OK" pada
    output proses anak (OOM adalah fatal error PHP yang tak bisa ditangkap
    try/catch, jadi harus dideteksi di sini/orkestrator, bukan di dalam kode PHP).

.NOTES
    Jalankan dari root proyek Laravel (folder yang berisi artisan):
        powershell -ExecutionPolicy Bypass -File .\run_breakpoint.ps1

    SEBELUM menjalankan skrip penuh ini, disarankan menguji satu seed manual dulu:
        php -d memory_limit=-1 artisan db:seed --class=BigDataSeeder --force
        php artisan tinker --execute="echo DB::table('publications')->count();"
#>

$ErrorActionPreference = 'Stop'

$memLimit = "256M"
$scales = @(500000, 800000, 1000000, 1200000, 1500000)
$keyword = "machine"
$out = "topsis_breakpoint_results.csv"
$architectures = @('sql-driven', 'data-to-compute')
$csvHeader = "architecture,memory_limit,keyword,scale,m_terfilter,status,peak_mb,latency_ms"

$csvPath = Join-Path $PSScriptRoot "storage/app/$out"

function Get-PublicationCount {
    $countExpr = "echo DB::table('publications')->count();"
    $raw = & php artisan tinker --execute=$countExpr 2>&1 | Out-String
    $match = [regex]::Match($raw, '\d+')
    if ($match.Success) {
        return [int]$match.Value
    }
    return -1
}

if (Test-Path $csvPath) {
    Remove-Item $csvPath -Force
    Write-Host "CSV lama dihapus: $csvPath" -ForegroundColor Yellow
}

foreach ($scale in $scales) {
    $numAuthors = [Math]::Max(1000, [int]($scale / 10))
    $env:SEED_PUBLICATIONS = $scale
    $env:SEED_AUTHORS = $numAuthors

    Write-Host "=== Seeding skala $scale publikasi, $numAuthors author (memory_limit=-1) ===" -ForegroundColor Cyan
    php -d memory_limit=-1 artisan db:seed --class=BigDataSeeder --force

    $rowCount = Get-PublicationCount
    Write-Host "  Verifikasi: tabel publications berisi $rowCount baris." -ForegroundColor Cyan

    if ($rowCount -lt 1) {
        Write-Host "  SEED GAGAL pada skala $scale -- skala ini dilewati." -ForegroundColor Red
        continue
    }

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
