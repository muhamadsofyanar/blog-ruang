<?php
// Versi aplikasi — dipisah dari config.php agar IKUT ditimpa saat self-update.
// APP_VERSION_BASE di-bump saat release (workflow GitHub). Instalasi DEV ditandai
// file marker `.dev-mode` di root (di-exclude dari artefak release) → menambahkan
// suffix -dev sehingga version_compare menganggapnya build pengembangan. Artefak
// release TIDAK memuat .dev-mode → APP_VERSION = versi bersih 'x.y.z'.
$__scribe_version_base = '1.0.63';
define('APP_VERSION', is_file(__DIR__ . '/.dev-mode')
    ? $__scribe_version_base . '-dev'
    : $__scribe_version_base);
unset($__scribe_version_base);
