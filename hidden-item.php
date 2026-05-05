<?php
declare(strict_types=1);

/**
 * Kumpulan fungsi logika (tanpa tampilan) untuk game Hidden Item.
 *
 * File ini sengaja tidak menjalankan apa pun secara otomatis.
 * Jalankan program dari index.php (CLI atau server lokal).
 */
/**
 * Penanda sederhana bahwa file logika sudah ter-include.
 */
function hiddenItemLogicLoaded(): bool
{
    return true;
}

/**
 * Mengubah baris-baris grid menjadi array 2D karakter dan memvalidasi bentuk/karakter.
 *
 * Karakter valid: '#', '.', 'X'
 * Grid harus persegi panjang dan memiliki tepat satu 'X'.
 *
 * @return array<int, array<int, string>>
 */
function parseGrid(array $lines): array
{
    $width = null;
    $startCount = 0;
    $grid = [];

    foreach ($lines as $rowIndex => $line) {
        $lineWidth = strlen($line);
        if ($width === null) {
            $width = $lineWidth;
        }

        if ($lineWidth !== $width) {
            throw new InvalidArgumentException('Grid harus berbentuk persegi panjang (panjang setiap baris harus sama).');
        }

        $row = [];
        for ($col = 0; $col < $width; $col++) {
            $ch = $line[$col];
            if ($ch !== '#' && $ch !== '.' && $ch !== 'X') {
                throw new InvalidArgumentException("Karakter tidak valid pada grid: '{$ch}'. Hanya boleh '#', '.', 'X'.");
            }
            if ($ch === 'X') {
                $startCount++;
            }
            $row[] = $ch;
        }
        $grid[] = $row;
    }

    if ($startCount !== 1) {
        throw new InvalidArgumentException("Grid harus memiliki tepat 1 posisi awal 'X'. Ditemukan: {$startCount}");
    }

    return $grid;
}

/**
 * Mencari posisi awal pemain (X).
 *
 * @param array<int, array<int, string>> $grid
 * @return array{0:int,1:int} [row, col]
 */
function findStartPosition(array $grid): array
{
    $height = count($grid);
    $width = count($grid[0]);

    for ($r = 0; $r < $height; $r++) {
        for ($c = 0; $c < $width; $c++) {
            if ($grid[$r][$c] === 'X') {
                return [$r, $c];
            }
        }
    }

    throw new RuntimeException("Posisi awal 'X' tidak ditemukan (validasi grid seharusnya mencegah ini).");
}

/**
 * Mengecek apakah sebuah sel bisa dilalui.
 * Pemain bisa berjalan di '.' dan 'X'.
 *
 * @param array<int, array<int, string>> $grid
 */
function isWalkable(array $grid, int $row, int $col): bool
{
    $ch = $grid[$row][$col];
    return $ch === '.' || $ch === 'X';
}

/**
 * Mengubah posisi grid menjadi key string yang unik.
 */
function encodePositionKey(int $row, int $col): string
{
    return $row . ':' . $col;
}

/**
 * Mengubah key posisi kembali menjadi [row, col].
 *
 * @return array{0:int,1:int}
 */
function decodePositionKey(string $key): array
{
    $parts = explode(':', $key, 2);
    return [(int)$parts[0], (int)$parts[1]];
}

/**
 * Mengambil semua posisi '.' di grid.
 *
 * @param array<int, array<int, string>> $grid
 * @return array<int, array{0:int,1:int}> Daftar [row, col]
 */
function listAllClearPathCells(array $grid): array
{
    $result = [];
    $height = count($grid);
    $width = count($grid[0]);

    for ($r = 0; $r < $height; $r++) {
        for ($c = 0; $c < $width; $c++) {
            if ($grid[$r][$c] === '.') {
                $result[] = [$r, $c];
            }
        }
    }

    return $result;
}

/**
 * Memilih posisi item secara acak dari sel '.'.
 *
 * @param array<int, array<int, string>> $grid
 * @return array{0:int,1:int} [row, col]
 */
function chooseRandomItemPosition(array $grid): array
{
    $cells = listAllClearPathCells($grid);
    if (count($cells) === 0) {
        throw new RuntimeException("Grid tidak memiliki sel '.' untuk menyembunyikan item.");
    }

    $index = random_int(0, count($cells) - 1);
    return $cells[$index];
}

/**
 * Memproses input posisi item dengan format "x,y" (1-based).
 * Posisi harus berada di dalam grid dan harus pada sel '.'.
 *
 * @param array<int, array<int, string>> $grid
 * @return array{0:int,1:int} [row, col]
 */
function parseItemPosition(string $raw, array $grid): array
{
    $raw = trim($raw);
    if ($raw === '') {
        throw new InvalidArgumentException("Nilai --item tidak boleh kosong. Contoh: --item=6,2");
    }

    $parts = explode(',', $raw, 2);
    if (count($parts) !== 2) {
        throw new InvalidArgumentException("Format --item harus 'x,y'. Contoh: --item=6,2");
    }

    $xRaw = trim($parts[0]);
    $yRaw = trim($parts[1]);
    if ($xRaw === '' || $yRaw === '' || !ctype_digit($xRaw) || !ctype_digit($yRaw)) {
        throw new InvalidArgumentException("Format --item harus 'x,y' dengan angka >= 1. Contoh: --item=6,2");
    }

    $x = (int)$xRaw;
    $y = (int)$yRaw;
    if ($x < 1 || $y < 1) {
        throw new InvalidArgumentException("Nilai --item harus >= 1. Contoh: --item=6,2");
    }

    $row = $y - 1;
    $col = $x - 1;
    $height = count($grid);
    $width = count($grid[0]);

    if ($row < 0 || $row >= $height || $col < 0 || $col >= $width) {
        throw new InvalidArgumentException("Posisi item ({$x},{$y}) berada di luar grid.");
    }
    if ($grid[$row][$col] !== '.') {
        throw new InvalidArgumentException("Posisi item ({$x},{$y}) harus berada di sel '.' (jalan).");
    }

    return [$row, $col];
}

/**
 * Memindahkan pemain sesuai urutan langkah:
 * naik sebanyak A langkah, kanan sebanyak B langkah, lalu turun sebanyak C langkah.
 *
 * Kemenangan ditentukan dari posisi akhir, bukan dari sel yang dilewati.
 *
 * @param array<int, array<int, string>> $grid
 * @return array{0:int,1:int} Posisi akhir [row, col]
 */
function computeFinalPlayerPosition(array $grid, int $startRow, int $startCol, int $a, int $b, int $c): array
{
    $row = $startRow;
    $col = $startCol;

    [$row, $col] = moveAndValidate($grid, $row, $col, -1, 0, $a, 'naik');
    [$row, $col] = moveAndValidate($grid, $row, $col, 0, 1, $b, 'kanan');
    [$row, $col] = moveAndValidate($grid, $row, $col, 1, 0, $c, 'turun');

    return [$row, $col];
}

/**
 * Menghitung daftar koordinat kemungkinan item berada.
 *
 * Definisi "kemungkinan" yang dipakai:
 * - semua sel '.' yang dilewati pemain selama bergerak (A, B, C).
 *
 * @param array<int, array<int, string>> $grid
 * @return array<string, true> Set keyed by "row:col"
 */
function computePossibleItemPositions(array $grid, int $startRow, int $startCol, int $a, int $b, int $c): array
{
    $possible = [];

    $row = $startRow;
    $col = $startCol;

    [$row, $col] = moveAndCollect($grid, $row, $col, -1, 0, $a, $possible, 'naik');
    [$row, $col] = moveAndCollect($grid, $row, $col, 0, 1, $b, $possible, 'kanan');
    [$row, $col] = moveAndCollect($grid, $row, $col, 1, 0, $c, $possible, 'turun');

    return $possible;
}

/**
 * Bergerak langkah demi langkah dalam satu garis lurus sambil memvalidasi batas/rintangan.
 *
 * @param array<int, array<int, string>> $grid
 * @return array{0:int,1:int} Posisi baru [row, col]
 */
function moveAndValidate(array $grid, int $fromRow, int $fromCol, int $dr, int $dc, int $steps, string $phase): array
{
    $height = count($grid);
    $width = count($grid[0]);

    $r = $fromRow;
    $c = $fromCol;

    for ($i = 1; $i <= $steps; $i++) {
        $nr = $r + $dr;
        $nc = $c + $dc;

        if ($nr < 0 || $nr >= $height || $nc < 0 || $nc >= $width) {
            throw new RuntimeException("Gerakan {$phase} gagal pada langkah ke-{$i}: keluar dari grid.");
        }
        if (!isWalkable($grid, $nr, $nc)) {
            throw new RuntimeException("Gerakan {$phase} gagal pada langkah ke-{$i}: menabrak rintangan '#'.");
        }

        $r = $nr;
        $c = $nc;
    }

    return [$r, $c];
}

/**
 * Bergerak langkah demi langkah dalam satu garis lurus sambil memvalidasi batas/rintangan.
 * Selama bergerak, semua sel '.' yang diinjak akan dimasukkan ke daftar kandidat item.
 *
 * @param array<int, array<int, string>> $grid
 * @param array<string, true> $collector Set keyed by "row:col"
 * @return array{0:int,1:int} Posisi baru [row, col]
 */
function moveAndCollect(array $grid, int $fromRow, int $fromCol, int $dr, int $dc, int $steps, array &$collector, string $phase): array
{
    $height = count($grid);
    $width = count($grid[0]);

    $r = $fromRow;
    $c = $fromCol;

    for ($i = 1; $i <= $steps; $i++) {
        $nr = $r + $dr;
        $nc = $c + $dc;

        if ($nr < 0 || $nr >= $height || $nc < 0 || $nc >= $width) {
            throw new RuntimeException("Gerakan {$phase} gagal pada langkah ke-{$i}: keluar dari grid.");
        }
        if (!isWalkable($grid, $nr, $nc)) {
            throw new RuntimeException("Gerakan {$phase} gagal pada langkah ke-{$i}: menabrak rintangan '#'.");
        }

        $r = $nr;
        $c = $nc;

        if ($grid[$r][$c] === '.') {
            $collector[encodePositionKey($r, $c)] = true;
        }
    }

    return [$r, $c];
}

/**
 * Mengembalikan grid baru, dengan lokasi kemungkinan item ditandai simbol '$'.
 *
 * @param array<int, array<int, string>> $grid
 * @param array<string, true> $possible Set keyed by "row:col"
 * @return array<int, array<int, string>>
 */
function markPossiblePositions(array $grid, array $possible): array
{
    $marked = $grid;
    foreach ($possible as $key => $_) {
        [$r, $c] = decodePositionKey($key);
        if ($marked[$r][$c] === '.') {
            $marked[$r][$c] = '$';
        }
    }
    return $marked;
}

/**
 * Menandai posisi item pada grid menggunakan simbol '*'.
 * Umumnya dipakai saat item ditemukan.
 *
 * @param array<int, array<int, string>> $grid
 * @return array<int, array<int, string>>
 */
function markItemOnGrid(array $grid, int $itemRow, int $itemCol): array
{
    $marked = $grid;
    if ($marked[$itemRow][$itemCol] === '.' || $marked[$itemRow][$itemCol] === '$') {
        $marked[$itemRow][$itemCol] = '*';
    }
    return $marked;
}

/**
 * Mengubah grid menjadi string agar bisa ditampilkan.
 *
 * @param array<int, array<int, string>> $grid
 */
function renderGrid(array $grid): string
{
    $lines = [];
    foreach ($grid as $row) {
        $lines[] = implode('', $row);
    }
    return implode(PHP_EOL, $lines) . PHP_EOL;
}
