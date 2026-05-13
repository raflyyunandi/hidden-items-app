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

Catatan input:
- A, B, C **wajib diisi** dan harus angka bulat **>= 1**.

Setiap langkah harus:
- Tidak keluar dari grid
- Tidak menabrak `#` (rintangan)

### Catatan Penting (Agar Game Adil)
- Karena kemenangan ditentukan dari **posisi akhir**, maka item acak dipilih dari **posisi akhir yang memang bisa dicapai** oleh aturan gerak (naik → kanan → turun) dengan kombinasi A/B/C (>= 1).
- Ini mencegah kondisi item berada di sel `.` yang tidak mungkin menjadi posisi akhir (yang membuat permainan mustahil).

### Arti Simbol di Grid
- `#` = rintangan (tidak bisa dilalui)
- `.` = jalan (item hanya boleh disembunyikan di sini)
- `X` = posisi awal pemain
- `$` = kemungkinan lokasi item (sel jalan `.` yang dilewati pemain) (opsional/bonus)
- `*` = item (ditampilkan hanya jika Anda berhasil menemukannya) (opsional)

## Output Program
Program menampilkan:
1. Grid (bonus `$` untuk sel yang dilewati; `*` hanya muncul jika ITEM DITEMUKAN)
2. Koordinat akhir pemain dalam format `x,y` (1-based):
   - `x` = kolom (dari kiri ke kanan)
   - `y` = baris (dari atas ke bawah)
3. Status: ITEM DITEMUKAN / ITEM BELUM DITEMUKAN

Catatan: bonus `$` menandai **sel `.` yang dilewati pemain** saat bergerak dengan A/B/C. Pada tampilan web, daftar koordinat ini ditulis sebagai “History (Riwayat Koordinat)”.

## Struktur File
- `hidden-item.php` = logika permainan (tanpa tampilan)
- `config.php` = grid per level + aturan/keterangan simbol
- `index.php` = entry point (CLI dan server lokal)

## Menjalankan (Command Line)

Masuk ke folder project:

```bash
cd d:\Pekerjaan\hidden-items-app
```

Menentukan level:

```bash
php index.php --level=1 --a=1 --b=2 --c=1
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
  - Contoh: `http://localhost:8000/?level=1&a=1&b=2&c=1`
  - Reset item (acak ulang): `http://localhost:8000/?reset=1`
- Endpoint API (JSON):
  - `http://localhost:8000/api?level=1&a=1&b=2&c=1`

Catatan:
- Pada mode web, input A, B, C **wajib diisi** dan harus angka bulat >= 1.
- Tombol “Naik ke Level berikutnya” akan muncul jika statusnya **ITEM DITEMUKAN**.

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
