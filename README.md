# Hidden Item (CLI + Server Lokal) — PHP Native

Program ini adalah simulasi sederhana “mencari item tersembunyi” berbasis **grid** sesuai aturan pada soal.

## Konsep Game

### Peran Anda
- Anda adalah **pemain**.
- Posisi awal pemain ditandai dengan karakter `X` pada grid.

### Tujuan
- Ada sebuah **item** yang disembunyikan di salah satu sel jalan `.`.
- Anda dianggap **berhasil** jika setelah bergerak (A, B, C) posisi akhir Anda tepat berada di koordinat item.

### Aturan Gerak
Dari posisi `X`, pemain bergerak secara berurutan:
1. **Ke atas (Up/North)** sebanyak **A** langkah  
2. **Ke kanan (Right/East)** sebanyak **B** langkah  
3. **Ke bawah (Down/South)** sebanyak **C** langkah  

Setiap langkah harus:
- Tidak keluar dari grid
- Tidak menabrak `#` (rintangan)

### Arti Simbol di Grid
- `#` = rintangan (tidak bisa dilalui)
- `.` = jalan (item hanya boleh disembunyikan di sini)
- `X` = posisi awal pemain
- `$` = kemungkinan lokasi item (sel jalan `.` yang dilewati pemain) (opsional/bonus)
- `*` = item (ditampilkan hanya jika Anda berhasil menemukannya) (opsional)

## Output Program
Program menampilkan:
1. Grid (akan menampilkan `$` sebagai bonus, dan `*` hanya jika fitur item diaktifkan)
2. Koordinat akhir pemain dalam format `x,y` (1-based):
   - `x` = kolom (dari kiri ke kanan)
   - `y` = baris (dari atas ke bawah)
3. Status: ITEM DITEMUKAN / ITEM BELUM DITEMUKAN

Catatan: bonus `$` menandai **sel `.` yang dilewati pemain** saat bergerak dengan A/B/C.

## Struktur File
- `hidden-item.php` = logika permainan (tanpa tampilan)
- `config.php` = grid default + aturan/keterangan simbol
- `index.php` = entry point (CLI dan server lokal)

## Menjalankan (Command Line)

Masuk ke folder project:

```bash
cd d:\Pekerjaan\hidden-items-app
```

Jalankan dengan argumen A/B/C:

```bash
php index.php --a=1 --b=2 --c=1
```

Jika A/B/C tidak diberikan, program akan meminta input dari keyboard:

```bash
php index.php
```

Tampilkan bantuan:

```bash
php index.php --help
```

Tanpa menampilkan grid:

```bash
php index.php --a=1 --b=2 --c=1 --no-grid
```

Menentukan posisi item secara manual (opsional, untuk testing) dengan format `x,y`:

```bash
php index.php --a=1 --b=2 --c=1 --item=6,2
```

## Menjalankan (Server Lokal)

Program juga bisa dijalankan sebagai server lokal menggunakan PHP built-in server.

Start server:

```bash
php -S localhost:8000 index.php
```

Buka di browser:
- Halaman HTML (form input A/B/C + grid):
  - `http://localhost:8000/`
  - Contoh: `http://localhost:8000/?a=1&b=2&c=1`
  - Reset item (acak ulang): `http://localhost:8000/?reset=1`
- Endpoint API (JSON):
  - `http://localhost:8000/api?a=1&b=2&c=1`

## Mengganti Grid

Anda bisa membuat file `grid.txt` (1 baris = 1 baris grid), contoh:

```text
########
#......#
#.###..#
#...#.##
#X#....#
########
```

Lalu jalankan:

```bash
php index.php --grid-file=grid.txt --a=1 --b=2 --c=1
```

## Contoh Cara Membaca Hasil

Jika output koordinat berisi:
- `4,5`

Artinya posisi akhir pemain berada di:
- Kolom ke-4, baris ke-5 pada grid (menghitung mulai dari 1).
