<?php
declare(strict_types=1);

/**
 * Mengembalikan grid default untuk permainan.
 *
 * Simbol:
 * - # rintangan
 * - . jalan
 * - X posisi awal pemain
 *
 * @return array<int, string>
 */
function getDefaultGridLines(): array
{
    return getGridLinesByLevel(1);
}

/**
 * Mengembalikan grid berdasarkan level.
 *
 * Level 1 menggunakan grid sederhana yang diberikan pada awal.
 *
 * @return array<int, string>
 */
function getGridLinesByLevel(int $level): array
{
    if ($level === 1) {
        return [
            '########',
            '#......#',
            '#.###..#',
            '#...#.##',
            '#X#....#',
            '########',
        ];
    }

    if ($level === 2) {
        return [
            '############',
            '#..........#',
            '#....#.....#',
            '#..........#',
            '#..#.......#',
            '#..........#',
            '#.......#..#',
            '#..........#',
            '#..........#',
            '#...X......#',
            '############',
        ];
    }

    return getGridLinesByLevel(2);
}

/**
 * Mengembalikan level maksimum yang tersedia.
 */
function getMaxLevel(): int
{
    return 2;
}

/**
 * Mengembalikan teks aturan permainan untuk ditampilkan.
 *
 * @return array<int, string>
 */
function getRules(): array
{
    return [
        'Pemain mulai dari X.',
        'Item tersembunyi berada di salah satu sel jalan (.).',
        'Pemain bergerak berurutan: naik A langkah, kanan B langkah, turun C langkah.',
        'Setiap langkah tidak boleh keluar grid dan tidak boleh menabrak rintangan (#).',
        'Berhasil jika posisi akhir pemain tepat berada di koordinat item.',
    ];
}

/**
 * Mengembalikan keterangan simbol untuk ditampilkan.
 *
 * @return array<int, string>
 */
function getLegend(): array
{
    return [
        '# = merepresentasikan rintangan (obstacle)',
        '. = merepresentasikan jalan yang bisa dilalui (clear path)',
        'X = merepresentasikan posisi awal pemain',
        '$ = merepresentasikan posisi pemain saat ini (sel jalan yang dilewati)',
        '* = mempresentasikan item tersembunyi (ditampilkan hanya jika berhasil ditemukan)',
    ];
}
