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
        'A, B, dan C wajib diisi dan harus angka bulat >= 1.',
        'Agar permainan selalu bisa diselesaikan, item acak dipilih dari posisi akhir yang dapat dicapai dengan kombinasi A/B/C (>= 1).',
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
        '# = rintangan (obstacle)',
        '. = jalan yang bisa dilalui (clear path)',
        'X = posisi awal pemain',
        '$ = kemungkinan lokasi item (sel jalan \'.\' yang dilewati) (bonus)',
        '* = item (opsional, ditampilkan hanya jika ITEM DITEMUKAN)',
    ];
}
