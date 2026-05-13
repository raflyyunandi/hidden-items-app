<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/hidden-item.php';

/**
 * Titik masuk aplikasi (CLI dan server lokal).
 */
function runApp(array $argv): void
{
    if (PHP_SAPI === 'cli') {
        runCli($argv);
        return;
    }

    runWeb();
}

/**
 * Menjalankan mode CLI.
 */
function runCli(array $argv): void
{
    try {
        $options = parseCliArguments($argv);

        $level = $options['level'] ?? 1;
        $gridLines = $options['gridFile'] !== null
            ? loadGridLinesFromFile($options['gridFile'])
            : getGridLinesByLevel($level);

        $grid = parseGrid($gridLines);
        [$startRow, $startCol] = findStartPosition($grid);

        $a = $options['a'] ?? readPositiveIntFromStdin('Masukkan A (naik): ');
        $b = $options['b'] ?? readPositiveIntFromStdin('Masukkan B (kanan): ');
        $c = $options['c'] ?? readPositiveIntFromStdin('Masukkan C (turun): ');

        [$itemRow, $itemCol] = $options['item'] !== null
            ? parseItemPosition($options['item'], $grid)
            : chooseRandomReachableItemPosition($grid, $startRow, $startCol);

        $possible = computePossibleItemPositions($grid, $startRow, $startCol, $a, $b, $c);
        [$finalRow, $finalCol] = computeFinalPlayerPosition($grid, $startRow, $startCol, $a, $b, $c);
        $isFound = ($finalRow === $itemRow && $finalCol === $itemCol);

        if (!$options['noGrid']) {
            $gridToPrint = markPossiblePositions($grid, $possible);
            if ($isFound) {
                $gridToPrint = markItemOnGrid($gridToPrint, $itemRow, $itemCol);
            }
            echo renderGrid($gridToPrint) . PHP_EOL;
        }

        $finalX = $finalCol + 1;
        $finalY = $finalRow + 1;
        echo "Koordinat akhir pemain (x,y): {$finalX},{$finalY}" . PHP_EOL;
        echo "Status: " . ($isFound ? 'ITEM DITEMUKAN' : 'ITEM BELUM DITEMUKAN') . PHP_EOL;
        echo PHP_EOL;

        if ($isFound) {
            $itemX = $itemCol + 1;
            $itemY = $itemRow + 1;
            echo "Item berada di: {$itemX},{$itemY}" . PHP_EOL;
        } else {
            echo "Item tetap tersembunyi." . PHP_EOL;
        }

        echo PHP_EOL;
        echo "Kemungkinan koordinat item (x,y):" . PHP_EOL;
        $possibleCoords = possibleSetToSortedCoordinates($possible);
        if (count($possibleCoords) === 0) {
            echo "- (tidak ada kemungkinan)" . PHP_EOL;
        } else {
            foreach ($possibleCoords as $pos) {
                echo "- {$pos['x']},{$pos['y']}" . PHP_EOL;
            }
        }

        echo PHP_EOL;
        echo "Aturan:" . PHP_EOL;
        foreach (getRules() as $rule) {
            echo "- {$rule}" . PHP_EOL;
        }
        echo PHP_EOL;

        echo "Keterangan:" . PHP_EOL;
        foreach (getLegend() as $legend) {
            echo "- {$legend}" . PHP_EOL;
        }
    } catch (Throwable $e) {
        $message = 'Error: ' . $e->getMessage() . PHP_EOL;
        if (defined('STDERR')) {
            fwrite(STDERR, $message);
        } else {
            echo $message;
        }
        exit(1);
    }
}

/**
 * Menjalankan mode web (server lokal via php -S).
 */
function runWeb(): void
{
    try {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        if ($path === '/api') {
            handleApi();
            return;
        }

        handleHtml();
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Error: ' . $e->getMessage();
    }
}

/**
 * Menghasilkan response JSON untuk endpoint /api.
 */
function handleApi(): void
{
    $level = getLevelFromRequest();
    $grid = parseGrid(getGridLinesByLevel($level));
    [$startRow, $startCol] = findStartPosition($grid);

    $a = getQueryPositiveIntOrNull('a');
    $b = getQueryPositiveIntOrNull('b');
    $c = getQueryPositiveIntOrNull('c');
    if ($a === null || $b === null || $c === null) {
        http_response_code(400);
        jsonResponse([
            'error' => 'Input A, B, C wajib diisi dan harus angka bulat >= 1.',
            'contoh' => '/api?level=1&a=3&b=1&c=1',
        ]);
        return;
    }

    $itemRaw = getQueryString('item');
    $reset = getQueryInt('reset', 0) === 1;
    [$itemRow, $itemCol] = resolveItemPositionForWeb($grid, $itemRaw, $reset, $level);

    $possible = computePossibleItemPositions($grid, $startRow, $startCol, $a, $b, $c);
    $history = computeMovementHistory($grid, $startRow, $startCol, $a, $b, $c);
    [$finalRow, $finalCol] = computeFinalPlayerPosition($grid, $startRow, $startCol, $a, $b, $c);
    $isFound = ($finalRow === $itemRow && $finalCol === $itemCol);

    $gridToShow = markPossiblePositions($grid, $possible);
    if ($isFound) {
        $gridToShow = markItemOnGrid($gridToShow, $itemRow, $itemCol);
    }

    $possibleCoords = historyToCoordinates($history);
    jsonResponse([
        'level' => $level,
        'input' => ['a' => $a, 'b' => $b, 'c' => $c],
        'koordinat_akhir_pemain' => ['x' => $finalCol + 1, 'y' => $finalRow + 1],
        'status' => [
            'berhasil' => $isFound,
            'item' => $isFound ? ['x' => $itemCol + 1, 'y' => $itemRow + 1] : null,
        ],
        'next_level' => ($isFound && $level < getMaxLevel()) ? ($level + 1) : null,
        'riwayat_koordinat' => $possibleCoords,
        'grid' => explode("\n", rtrim(renderGrid($gridToShow), "\n")),
        'aturan' => getRules(),
        'keterangan' => getLegend(),
    ]);
}

/**
 * Menampilkan halaman HTML sederhana.
 */
function handleHtml(): void
{
    $level = getLevelFromRequest();
    $grid = parseGrid(getGridLinesByLevel($level));
    [$startRow, $startCol] = findStartPosition($grid);
    $itemOptions = possibleSetToSortedCoordinates(computeReachableEndPositions($grid, $startRow, $startCol));

    $a = getQueryPositiveIntOrNull('a');
    $b = getQueryPositiveIntOrNull('b');
    $c = getQueryPositiveIntOrNull('c');

    $itemRaw = getQueryString('item');
    $reset = getQueryInt('reset', 0) === 1;
    [$itemRow, $itemCol] = resolveItemPositionForWeb($grid, $itemRaw, $reset, $level);

    if ($a === null || $b === null || $c === null) {
        $baseGridToShow = $grid;
        $statusText = 'Input A, B, dan C wajib diisi (angka bulat >= 1).';

        header('Content-Type: text/html; charset=utf-8');
        echo renderHtmlPage(
            $level,
            $a,
            $b,
            $c,
            $itemRaw,
            $statusText,
            $baseGridToShow,
            [],
            false,
            $itemOptions
        );
        return;
    }

    $possible = computePossibleItemPositions($grid, $startRow, $startCol, $a, $b, $c);
    $history = computeMovementHistory($grid, $startRow, $startCol, $a, $b, $c);
    [$finalRow, $finalCol] = computeFinalPlayerPosition($grid, $startRow, $startCol, $a, $b, $c);
    $isFound = ($finalRow === $itemRow && $finalCol === $itemCol);

    $gridToShow = markPossiblePositions($grid, $possible);
    if ($isFound) {
        $gridToShow = markItemOnGrid($gridToShow, $itemRow, $itemCol);
    }

    $statusText = $isFound ? 'ITEM DITEMUKAN' : 'ITEM BELUM DITEMUKAN';

    header('Content-Type: text/html; charset=utf-8');
    $possibleCoords = historyToCoordinates($history);
    echo renderHtmlPage($level, $a, $b, $c, $itemRaw, $statusText, $gridToShow, $possibleCoords, $isFound, $itemOptions);
}

/**
 * Mengambil level dari request (query string). Jika tidak valid, kembali ke level 1.
 */
function getLevelFromRequest(): int
{
    $level = getQueryInt('level', 1);
    if ($level < 1) {
        return 1;
    }
    $max = getMaxLevel();
    if ($level > $max) {
        return $max;
    }
    return $level;
}

/**
 * Menentukan posisi item untuk mode web.
 * Jika item diinput manual (query item=x,y) maka item mengikuti input tersebut.
 * Jika tidak ada input manual, item disimpan di cookie agar tidak berubah setiap refresh.
 *
 * @param array<int, array<int, string>> $grid
 * @return array{0:int,1:int} [row, col]
 */
function resolveItemPositionForWeb(array $grid, ?string $itemRaw, bool $reset, int $level): array
{
    if ($itemRaw !== null) {
        return parseItemPosition($itemRaw, $grid);
    }

    if ($reset) {
        clearHiddenItemCookie(getItemCookieName($level));
    }

    [$startRow, $startCol] = findStartPosition($grid);
    $reachableEnd = computeReachableEndPositions($grid, $startRow, $startCol);
    if (count($reachableEnd) === 0) {
        throw new RuntimeException("Tidak ada posisi akhir '.' yang dapat dicapai pada level ini.");
    }

    $cookieRaw = getCookieString(getItemCookieName($level));
    if ($cookieRaw !== null) {
        $pos = parseItemCookie($cookieRaw, $grid);
        if ($pos !== null) {
            $key = encodePositionKey($pos[0], $pos[1]);
            if (isset($reachableEnd[$key])) {
                return $pos;
            }
        }
    }

    $keys = array_keys($reachableEnd);
    $key = $keys[random_int(0, count($keys) - 1)];
    $pos = decodePositionKey($key);
    setHiddenItemCookie(getItemCookieName($level), $pos[0], $pos[1]);
    return $pos;
}

/**
 * Membuat nama cookie item berdasarkan level.
 */
function getItemCookieName(int $level): string
{
    return 'hidden_item_pos_l' . (int)$level;
}

/**
 * Mengambil string dari cookie. Jika tidak ada, mengembalikan null.
 */
function getCookieString(string $name): ?string
{
    if (!isset($_COOKIE[$name])) {
        return null;
    }
    $raw = $_COOKIE[$name];
    if (!is_string($raw)) {
        return null;
    }
    $raw = trim($raw);
    return $raw === '' ? null : $raw;
}

/**
 * Memproses nilai cookie item menjadi posisi [row, col].
 * Cookie disimpan dalam format "row:col" (0-based).
 *
 * @param array<int, array<int, string>> $grid
 * @return array{0:int,1:int}|null
 */
function parseItemCookie(string $raw, array $grid): ?array
{
    $parts = explode(':', $raw, 2);
    if (count($parts) !== 2) {
        return null;
    }

    $rowRaw = trim($parts[0]);
    $colRaw = trim($parts[1]);
    if ($rowRaw === '' || $colRaw === '' || !ctype_digit($rowRaw) || !ctype_digit($colRaw)) {
        return null;
    }

    $row = (int)$rowRaw;
    $col = (int)$colRaw;
    $height = count($grid);
    $width = count($grid[0]);

    if ($row < 0 || $row >= $height || $col < 0 || $col >= $width) {
        return null;
    }
    if ($grid[$row][$col] !== '.') {
        return null;
    }

    return [$row, $col];
}

/**
 * Menyimpan posisi item ke cookie agar konsisten saat refresh.
 */
function setHiddenItemCookie(string $cookieName, int $row, int $col): void
{
    $value = $row . ':' . $col;
    setcookie($cookieName, $value, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Menghapus cookie item.
 */
function clearHiddenItemCookie(string $cookieName): void
{
    setcookie($cookieName, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Memproses argumen CLI menjadi array opsi yang terstruktur.
 *
 * Opsi yang didukung:
 * - --grid-file=PATH : memuat grid dari file teks (1 baris = 1 baris grid)
 * - --a=N            : jumlah langkah naik (Up)
 * - --b=N            : jumlah langkah kanan (Right)
 * - --c=N            : jumlah langkah turun (Down)
 * - --item=x,y       : menentukan posisi item secara manual (x=kolom, y=baris | 1-based)
 * - --no-grid        : tidak menampilkan grid
 */
function parseCliArguments(array $argv): array
{
    $options = [
        'gridFile' => null,
        'a' => null,
        'b' => null,
        'c' => null,
        'item' => null,
        'level' => null,
        'noGrid' => false,
    ];

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        if (startsWith($arg, '--grid-file=')) {
            $options['gridFile'] = substr($arg, strlen('--grid-file='));
            continue;
        }

        if (startsWith($arg, '--a=')) {
            $options['a'] = parsePositiveInt(substr($arg, strlen('--a=')), '--a');
            continue;
        }

        if (startsWith($arg, '--b=')) {
            $options['b'] = parsePositiveInt(substr($arg, strlen('--b=')), '--b');
            continue;
        }

        if (startsWith($arg, '--c=')) {
            $options['c'] = parsePositiveInt(substr($arg, strlen('--c=')), '--c');
            continue;
        }

        if (startsWith($arg, '--item=')) {
            $options['item'] = substr($arg, strlen('--item='));
            continue;
        }

        if (startsWith($arg, '--level=')) {
            $options['level'] = parseNonNegativeInt(substr($arg, strlen('--level=')), '--level');
            if ($options['level'] < 1) {
                throw new InvalidArgumentException("Nilai --level harus >= 1.");
            }
            if ($options['level'] > getMaxLevel()) {
                $options['level'] = getMaxLevel();
            }
            continue;
        }

        if ($arg === '--no-grid') {
            $options['noGrid'] = true;
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            echo "Hidden Item (CLI)" . PHP_EOL;
            echo "Cara pakai:" . PHP_EOL;
            echo "  php index.php [--level=N] [--grid-file=PATH] [--a=N] [--b=N] [--c=N] [--item=x,y] [--no-grid]" . PHP_EOL;
            echo PHP_EOL;
            exit(0);
        }

        throw new InvalidArgumentException("Argumen tidak dikenali: {$arg}. Gunakan --help untuk bantuan.");
    }

    return $options;
}

/**
 * Memproses nilai opsi CLI menjadi bilangan bulat >= 0.
 */
function parseNonNegativeInt(string $raw, string $optionName): int
{
    if ($raw === '' || !ctype_digit($raw)) {
        throw new InvalidArgumentException("Nilai {$optionName} harus berupa angka bulat >= 0.");
    }
    return (int)$raw;
}

/**
 * Memproses nilai opsi CLI menjadi bilangan bulat >= 1.
 */
function parsePositiveInt(string $raw, string $optionName): int
{
    if ($raw === '' || !ctype_digit($raw)) {
        throw new InvalidArgumentException("Nilai {$optionName} harus berupa angka bulat >= 1.");
    }
    $value = (int)$raw;
    if ($value < 1) {
        throw new InvalidArgumentException("Nilai {$optionName} harus berupa angka bulat >= 1.");
    }
    return $value;
}

/**
 * Membaca bilangan bulat >= 0 dari STDIN dengan prompt.
 */
function readNonNegativeIntFromStdin(string $prompt): int
{
    while (true) {
        echo $prompt;
        $line = fgets(STDIN);
        if ($line === false) {
            throw new RuntimeException('Gagal membaca input.');
        }

        $line = trim($line);
        if ($line !== '' && ctype_digit($line)) {
            return (int)$line;
        }

        echo "Input harus berupa angka bulat >= 0." . PHP_EOL;
    }
}

/**
 * Membaca bilangan bulat >= 1 dari STDIN dengan prompt.
 */
function readPositiveIntFromStdin(string $prompt): int
{
    while (true) {
        echo $prompt;
        $line = fgets(STDIN);
        if ($line === false) {
            throw new RuntimeException('Gagal membaca input.');
        }

        $line = trim($line);
        if ($line !== '' && ctype_digit($line)) {
            $value = (int)$line;
            if ($value >= 1) {
                return $value;
            }
        }

        echo "Input harus berupa angka bulat >= 1." . PHP_EOL;
    }
}

/**
 * Mengecek apakah $text diawali dengan $prefix (kompatibel PHP 7).
 */
function startsWith(string $text, string $prefix): bool
{
    if ($prefix === '') {
        return true;
    }
    return strncmp($text, $prefix, strlen($prefix)) === 0;
}

/**
 * Memuat grid dari file teks.
 * Baris kosong diabaikan, akhiran baris dinormalisasi.
 *
 * @return array<int, string>
 */
function loadGridLinesFromFile(string $path): array
{
    if (!is_file($path)) {
        throw new InvalidArgumentException("File grid tidak ditemukan: {$path}");
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Gagal membaca file grid: {$path}");
    }

    $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    $lines = explode("\n", $contents);

    $result = [];
    foreach ($lines as $line) {
        $trimmed = rtrim($line, "\n");
        if ($trimmed === '') {
            continue;
        }
        $result[] = $trimmed;
    }

    if (count($result) === 0) {
        throw new InvalidArgumentException("File grid kosong atau tidak berisi baris yang valid: {$path}");
    }

    return $result;
}

/**
 * Mengambil bilangan bulat >= 0 dari query string. Jika tidak ada, pakai default.
 */
function getQueryInt(string $name, int $default): int
{
    if (!isset($_GET[$name])) {
        return $default;
    }

    $raw = $_GET[$name];
    if (!is_string($raw)) {
        return $default;
    }

    $raw = trim($raw);
    if ($raw === '' || !ctype_digit($raw)) {
        return $default;
    }

    return (int)$raw;
}

/**
 * Mengambil bilangan bulat >= 0 dari query string.
 * Jika tidak ada atau tidak valid, mengembalikan null.
 */
function getQueryNonNegativeIntOrNull(string $name): ?int
{
    if (!isset($_GET[$name])) {
        return null;
    }

    $raw = $_GET[$name];
    if (!is_string($raw)) {
        return null;
    }

    $raw = trim($raw);
    if ($raw === '' || !ctype_digit($raw)) {
        return null;
    }

    return (int)$raw;
}

/**
 * Mengambil bilangan bulat >= 1 dari query string.
 * Jika tidak ada atau tidak valid, mengembalikan null.
 */
function getQueryPositiveIntOrNull(string $name): ?int
{
    if (!isset($_GET[$name])) {
        return null;
    }

    $raw = $_GET[$name];
    if (!is_string($raw)) {
        return null;
    }

    $raw = trim($raw);
    if ($raw === '' || !ctype_digit($raw)) {
        return null;
    }

    $value = (int)$raw;
    return $value >= 1 ? $value : null;
}

/**
 * Mengambil string dari query string. Jika tidak ada, mengembalikan null.
 */
function getQueryString(string $name): ?string
{
    if (!isset($_GET[$name])) {
        return null;
    }
    $raw = $_GET[$name];
    if (!is_string($raw)) {
        return null;
    }
    $raw = trim($raw);
    return $raw === '' ? null : $raw;
}

/**
 * Mengirim response JSON yang rapi.
 *
 * @param array<string, mixed> $data
 */
function jsonResponse(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/**
 * Membuat HTML sederhana untuk mode web.
 */
function renderHtmlPage(int $level, ?int $a, ?int $b, ?int $c, ?string $itemRaw, string $statusText, array $grid, array $possibleCoords, bool $isFound, array $itemOptions): string
{
    $apiUrl = '/api?level=' . urlencode((string)$level) . '&a=' . urlencode((string)$a) . '&b=' . urlencode((string)$b) . '&c=' . urlencode((string)$c);
    if ($itemRaw !== null) {
        $apiUrl .= '&item=' . urlencode($itemRaw);
    }

    $rulesHtml = '';
    foreach (getRules() as $rule) {
        $rulesHtml .= '<li>' . htmlspecialchars($rule, ENT_QUOTES, 'UTF-8') . '</li>';
    }

    $legendHtml = '';
    foreach (getLegend() as $legend) {
        $legendHtml .= '<li>' . htmlspecialchars($legend, ENT_QUOTES, 'UTF-8') . '</li>';
    }

    $possibleText = '';
    if (count($possibleCoords) === 0) {
        $possibleText = '(belum ada)';
    } else {
        $parts = [];
        foreach ($possibleCoords as $pos) {
            $parts[] = $pos['x'] . ',' . $pos['y'];
        }
        $possibleText = implode(' -> ', $parts);
    }

    $resetUrl = '/?level=' . urlencode((string)$level) . '&reset=1';
    $nextLevelHtml = '';
    if ($isFound && $level < getMaxLevel()) {
        $nextLevelUrl = '/?level=' . urlencode((string)($level + 1)) . '&reset=1';
        $nextLevelHtml = '<p><a href="' . htmlspecialchars($nextLevelUrl, ENT_QUOTES, 'UTF-8') . '">Naik ke Level ' . (int)($level + 1) . '</a></p>';
    }

    $itemSelectHtml = '<select name="item">';
    $itemSelectHtml .= '<option value=""' . ($itemRaw === null ? ' selected' : '') . '>Acak (otomatis)</option>';
    foreach ($itemOptions as $pos) {
        $value = $pos['x'] . ',' . $pos['y'];
        $selected = ($itemRaw === $value) ? ' selected' : '';
        $itemSelectHtml .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $itemSelectHtml .= '</select>';

    $gridHtml = renderGridAsTableHtml($grid);

    return '<!doctype html>'
        . '<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Hidden Item</title>'
        . '<style>'
        . 'body{font-family:Arial,Helvetica,sans-serif;margin:24px}'
        . 'input{width:80px}'
        . 'select{max-width:180px}'
        . '.small{font-size:12px;margin:6px 0}'
        . '.status{font-size:12px;display:inline-block;padding:4px 8px;border:1px solid #ddd;border-radius:8px;background:#fafafa}'
        . '.grid{border-collapse:collapse;margin-top:8px}'
        . '.grid td{width:34px;height:34px;border:1px solid #444;text-align:center;vertical-align:middle;font-family:Consolas,monospace;font-size:18px}'
        . '.cell-wall{background:#333;color:#ddd}'
        . '.cell-path{background:#f7f7f7;color:#111}'
        . '.cell-start{background:#1e88e5;color:#fff;font-weight:bold}'
        . '.cell-possible{background:#ffe082;color:#111;font-weight:bold}'
        . '.cell-item{background:#43a047;color:#fff;font-weight:bold}'
        . '</style>'
        . '</head><body>'
        . '<h2>Hidden Item (Server Lokal)</h2>'
        . '<p>Level: <strong>' . (int)$level . '</strong></p>'
        . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Reset item (acak ulang)</a> | <a href="' . htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') . '">Lihat JSON (/api)</a></p>'
        . '<h3>Input</h3>'
        . '<form method="get" action="/">'
        . '<input type="hidden" name="level" value="' . (int)$level . '">'
        . '<label>A (naik): <input name="a" type="number" min="1" required value="' . htmlspecialchars($a === null ? '' : (string)$a, ENT_QUOTES, 'UTF-8') . '"></label> '
        . '<label>B (kanan): <input name="b" type="number" min="1" required value="' . htmlspecialchars($b === null ? '' : (string)$b, ENT_QUOTES, 'UTF-8') . '"></label> '
        . '<label>C (turun): <input name="c" type="number" min="1" required value="' . htmlspecialchars($c === null ? '' : (string)$c, ENT_QUOTES, 'UTF-8') . '"></label> '
        . '<label>Item (pilih): ' . $itemSelectHtml . '</label> '
        . '<button type="submit">Jalankan</button>'
        . '</form>'
        . '<h3>Status</h3><p class="small"><span class="status">' . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</span></p>'
        . $nextLevelHtml
        . '<h3>History (Riwayat Koordinat)</h3><p class="small">' . htmlspecialchars($possibleText, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<h3>Grid</h3>' . $gridHtml
        . '<h3>Aturan</h3><ol>' . $rulesHtml . '</ol>'
        . '<h3>Keterangan</h3><ul>' . $legendHtml . '</ul>'
        . '</body></html>';
}

/**
 * Mengubah grid menjadi HTML table dengan kotak yang lebih besar.
 *
 * @param array<int, array<int, string>> $grid
 */
function renderGridAsTableHtml(array $grid): string
{
    $html = '<table class="grid" aria-label="Grid permainan">';
    foreach ($grid as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $class = 'cell-path';
            if ($cell === '#') {
                $class = 'cell-wall';
            } elseif ($cell === 'X') {
                $class = 'cell-start';
            } elseif ($cell === '$') {
                $class = 'cell-possible';
            } elseif ($cell === '*') {
                $class = 'cell-item';
            }
            $html .= '<td class="' . $class . '">' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}

/**
 * Mengubah set posisi (key "row:col") menjadi daftar koordinat x,y (1-based) yang sudah diurutkan.
 *
 * @param array<string, true> $possible
 * @return array<int, array{x:int,y:int}>
 */
function possibleSetToSortedCoordinates(array $possible): array
{
    $coords = [];
    foreach ($possible as $key => $_) {
        [$row, $col] = decodePositionKey($key);
        $coords[] = ['x' => $col + 1, 'y' => $row + 1];
    }

    usort($coords, static function (array $p1, array $p2): int {
        if ($p1['y'] !== $p2['y']) {
            return $p1['y'] <=> $p2['y'];
        }
        return $p1['x'] <=> $p2['x'];
    });

    return $coords;
}

/**
 * Mengubah riwayat [row, col] menjadi daftar koordinat x,y (1-based) berurutan.
 *
 * @param array<int, array{0:int,1:int}> $history
 * @return array<int, array{x:int,y:int}>
 */
function historyToCoordinates(array $history): array
{
    $coords = [];
    foreach ($history as $pos) {
        $coords[] = ['x' => $pos[1] + 1, 'y' => $pos[0] + 1];
    }
    return $coords;
}

runApp($argv ?? []);
