<?php
// Definisi kebutuhan server installer (sumber tunggal untuk view + guard).
// Setiap check: ['label', 'detail', 'pass'].

if (!function_exists('scribe_required_checks')) {
    function scribe_required_checks(): array
    {
        $root      = dirname(__DIR__);       // installer/ → root project
        $instDir   = __DIR__;
        $uploads   = $root . '/uploads';
        $cache     = $root . '/cache';

        return [
            ['label' => 'PHP versi 8.0+', 'detail' => '(Anda: PHP ' . PHP_VERSION . ')',
             'pass'  => version_compare(PHP_VERSION, '8.0.0', '>=')],
            ['label' => 'Ekstensi PDO MySQL', 'detail' => '(pdo_mysql)',
             'pass'  => extension_loaded('pdo') && extension_loaded('pdo_mysql')],
            ['label' => 'Ekstensi Sodium', 'detail' => '(verifikasi lisensi Ed25519)',
             'pass'  => extension_loaded('sodium')],
            ['label' => 'Ekstensi cURL', 'detail' => '(komunikasi license server + AI gateway)',
             'pass'  => extension_loaded('curl')],
            ['label' => 'Ekstensi GD', 'detail' => '(cover fallback + varian gambar)',
             'pass'  => extension_loaded('gd')],
            ['label' => 'Ekstensi mbstring', 'detail' => '',
             'pass'  => extension_loaded('mbstring')],
            ['label' => 'Ekstensi JSON', 'detail' => '',
             'pass'  => extension_loaded('json')],
            ['label' => 'Folder root bisa ditulis', 'detail' => '(membuat config.php)',
             'pass'  => is_writable(getenv('AVERION_RUNTIME_DIR') ?: $root)],
            ['label' => 'Folder installer/ bisa ditulis', 'detail' => '(menulis install.lock)',
             'pass'  => is_writable(getenv('AVERION_RUNTIME_DIR') ?: $instDir)],
            ['label' => 'Folder uploads/ bisa ditulis', 'detail' => '',
             'pass'  => (is_dir($uploads) && is_writable($uploads)) || @mkdir($uploads, 0755, true)],
            ['label' => 'Folder cache/ bisa ditulis', 'detail' => '',
             'pass'  => (is_dir($cache) && is_writable($cache)) || @mkdir($cache, 0755, true)],
        ];
    }
}

if (!function_exists('scribe_recommended_checks')) {
    function scribe_recommended_checks(): array
    {
        return [
            ['label' => 'Ekstensi Zip', 'detail' => '(fitur self-update)', 'pass' => extension_loaded('zip')],
            ['label' => 'Ekstensi fileinfo', 'detail' => '(validasi upload)', 'pass' => extension_loaded('fileinfo')],
        ];
    }
}

if (!function_exists('scribe_required_all_pass')) {
    function scribe_required_all_pass(): bool
    {
        foreach (scribe_required_checks() as $c) {
            if (!$c['pass']) return false;
        }
        return true;
    }
}
