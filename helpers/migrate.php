<?php
// ════════════════════════════════════════════════════════════════════════
// Migration runner. Pola Averion: pemecah SQL SADAR komentar & string literal
// (menggantikan explode(';') naif). Larangan keras: TIDAK ada ';' di komentar
// file migration (aturan tetap berlaku sebagai disiplin, walau runner ini sudah
// tahan). Tracking versi disimpan di tabel `settings` (bukan tabel terpisah) →
// skema client tetap 12 tabel domain.
// ════════════════════════════════════════════════════════════════════════

/**
 * Pecah file SQL migrasi menjadi statement, SADAR komentar (-- , #, blok) &
 * string literal (' " `, quote ganda). Komentar di-strip sebelum eksekusi.
 */
function _scribeSplitMigrationSql(string $sql): array
{
    $statements = [];
    $current    = '';
    $inString   = false;
    $stringChar = '';
    $len        = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($inString) {
            $current .= $ch;
            if ($ch === '\\') {
                if ($i + 1 < $len) { $current .= $sql[++$i]; }
            } elseif ($ch === $stringChar) {
                if ($i + 1 < $len && $sql[$i + 1] === $stringChar) {
                    $current .= $sql[++$i]; // quote ganda ('' atau "")
                } else {
                    $inString = false;
                }
            }
        } elseif ($ch === "'" || $ch === '"' || $ch === '`') {
            $inString = true; $stringChar = $ch; $current .= $ch;
        } elseif ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; } // skip komentar --
        } elseif ($ch === '#') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; } // skip komentar #
        } elseif ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
            $i += 2;
            while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) { $i++; }
            $i += 1; // hentikan tepat di '/'; loop for menaikkan 1 lagi
        } elseif ($ch === ';') {
            $stmt = trim($current);
            if ($stmt !== '') { $statements[] = $stmt; }
            $current = '';
        } else {
            $current .= $ch;
        }
    }
    $stmt = trim($current);
    if ($stmt !== '') { $statements[] = $stmt; }
    return $statements;
}

/** Versi dari nama file migrasi: "1.0.0.sql" / "update-1.0.1.sql" → "1.0.0". */
function _scribeMigrationVersion(string $file): string
{
    $base = basename($file, '.sql');
    if (str_starts_with($base, 'update-')) {
        $base = substr($base, 7);
    }
    return $base;
}

/** Daftar versi migrasi yang sudah diterapkan (dari settings; [] bila belum ada). */
function scribeAppliedMigrations(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'applied_migrations' LIMIT 1");
        $stmt->execute();
        $raw = $stmt->fetchColumn();
        if ($raw === false) return [];
        $arr = json_decode((string) $raw, true);
        return is_array($arr) ? $arr : [];
    } catch (Throwable $e) {
        return []; // tabel settings belum ada (fresh install)
    }
}

/**
 * Jalankan semua migrasi pending di $dir (urut version_compare). Idempotent:
 * versi yang sudah tercatat dilewati; DDL memakai IF NOT EXISTS sebagai jaring.
 *
 * @return array{applied:array,skipped:array,errors:array}
 */
function scribeRunMigrations(PDO $pdo, string $dir): array
{
    $applied = scribeAppliedMigrations($pdo);
    $files   = glob(rtrim($dir, '/\\') . '/*.sql') ?: [];

    // Urutkan berdasarkan versi (bukan alfabet mentah).
    usort($files, fn($a, $b) => version_compare(_scribeMigrationVersion($a), _scribeMigrationVersion($b)));

    $result = ['applied' => [], 'skipped' => [], 'errors' => []];

    foreach ($files as $file) {
        $version = _scribeMigrationVersion($file);
        if (in_array($version, $applied, true)) {
            $result['skipped'][] = $version;
            continue;
        }

        $sql = (string) file_get_contents($file);
        $statements = _scribeSplitMigrationSql($sql);

        try {
            foreach ($statements as $stmt) {
                if (trim($stmt) === '') continue;
                $pdo->exec($stmt);
            }
        } catch (Throwable $e) {
            $result['errors'][$version] = $e->getMessage();
            error_log('[migrate] gagal ' . $version . ': ' . $e->getMessage());
            break; // hentikan — jangan lanjut migrasi berikutnya bila satu gagal
        }

        $applied[] = $version;
        $result['applied'][] = $version;

        // Rekam progress ke settings (settings sudah pasti ada setelah 1.0.0).
        setSetting('applied_migrations', json_encode(array_values(array_unique($applied))), 'system');
        setSetting('schema_version', $version, 'system');
    }

    return $result;
}
